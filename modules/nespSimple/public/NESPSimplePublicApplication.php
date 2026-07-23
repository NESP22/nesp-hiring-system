<?php

/**
 * Standalone public NESP application and questionnaire support.
 *
 * This class deliberately does not start or read a PHP session. Public form
 * integrity is protected by a short-lived signed token, while candidate and
 * job duplicates are serialized with a database advisory lock.
 */
class NESPSimplePublicApplication
{
    const FORM_TOKEN_VERSION = 1;
    const FORM_TOKEN_TTL_SECONDS = 14400;
    const MINIMUM_FORM_AGE_SECONDS = 2;
    const SIGNING_KEY_ENV = 'NESP_SIMPLE_APPLICATION_SIGNING_KEY';
    const SOURCE_LABEL = 'NESP Simple Public Application';

    public static function allowedJobs()
    {
        return array(
            41001 => 'Part-Time Customer Service Representative',
            41002 => 'Weekend Staff Portrait & Team Photographer - Youth Sports',
            41003 => 'Freelance/Contract Youth Sports Photographer',
            41005 => 'Weekend Table Greeter / Field Assistant'
        );
    }

    public static function signingKeyFromEnvironment()
    {
        $key = trim((string) getenv(self::SIGNING_KEY_ENV));
        return strlen($key) >= 32 ? $key : '';
    }

    public static function createFormToken(
        $jobOrderID,
        $signingKey,
        $issuedAt = null,
        $nonce = null,
        $questionnaireFingerprint = ''
    )
    {
        $jobOrderID = (int) $jobOrderID;
        $signingKey = (string) $signingKey;
        if (!isset(self::allowedJobs()[$jobOrderID]) || strlen($signingKey) < 32)
        {
            return '';
        }

        $payload = array(
            'v' => self::FORM_TOKEN_VERSION,
            'j' => $jobOrderID,
            'iat' => $issuedAt === null ? time() : (int) $issuedAt,
            'n' => $nonce === null ? self::base64UrlEncode(random_bytes(18)) : (string) $nonce,
            'q' => (string) $questionnaireFingerprint
        );
        $encodedPayload = self::base64UrlEncode(json_encode($payload));
        $signature = self::base64UrlEncode(
            hash_hmac('sha256', $encodedPayload, $signingKey, true)
        );
        return $encodedPayload . '.' . $signature;
    }

    public static function validateFormToken(
        $token,
        $jobOrderID,
        $signingKey,
        $now = null,
        $questionnaireFingerprint = ''
    )
    {
        $token = trim((string) $token);
        $jobOrderID = (int) $jobOrderID;
        $signingKey = (string) $signingKey;
        $now = $now === null ? time() : (int) $now;

        if ($token === '' || strlen($signingKey) < 32 || substr_count($token, '.') !== 1)
        {
            return array('ok' => false, 'reason' => 'invalid');
        }

        list($encodedPayload, $providedSignature) = explode('.', $token, 2);
        $expectedSignature = self::base64UrlEncode(
            hash_hmac('sha256', $encodedPayload, $signingKey, true)
        );
        if (!hash_equals($expectedSignature, $providedSignature))
        {
            return array('ok' => false, 'reason' => 'invalid');
        }

        $decodedPayload = self::base64UrlDecode($encodedPayload);
        $payload = json_decode($decodedPayload, true);
        if (!is_array($payload)
            || !isset($payload['v'], $payload['j'], $payload['iat'], $payload['n'])
            || (int) $payload['v'] !== self::FORM_TOKEN_VERSION
            || (int) $payload['j'] !== $jobOrderID
            || !isset(self::allowedJobs()[$jobOrderID])
            || !is_string($payload['n'])
            || strlen($payload['n']) < 12
            || !isset($payload['q'])
            || !hash_equals((string) $payload['q'], (string) $questionnaireFingerprint))
        {
            return array('ok' => false, 'reason' => 'invalid');
        }

        $age = $now - (int) $payload['iat'];
        if ($age < self::MINIMUM_FORM_AGE_SECONDS)
        {
            return array('ok' => false, 'reason' => 'too_fast');
        }
        if ($age > self::FORM_TOKEN_TTL_SECONDS)
        {
            return array('ok' => false, 'reason' => 'expired');
        }

        return array('ok' => true, 'reason' => 'valid', 'payload' => $payload);
    }

    public static function validatePublicPost(
        $post,
        $signingKey,
        $now = null,
        $questionnaireFingerprint = ''
    )
    {
        $post = is_array($post) ? $post : array();
        $jobOrderID = isset($post['jobOrderID']) ? (int) $post['jobOrderID'] : 0;
        if (trim((string) (isset($post['companyWebsite']) ? $post['companyWebsite'] : '')) !== '')
        {
            return array('ok' => false, 'reason' => 'unavailable');
        }

        return self::validateFormToken(
            isset($post['formToken']) ? $post['formToken'] : '',
            $jobOrderID,
            $signingKey,
            $now,
            $questionnaireFingerprint
        );
    }

    public static function questionnaireDefinitionForJob($jobOrderID, $workflow = null)
    {
        $jobOrderID = (int) $jobOrderID;
        $jobs = self::allowedJobs();
        if (!isset($jobs[$jobOrderID]))
        {
            return array();
        }

        $set = NESPWorkflow::getQuestionnaireSetForRole($jobs[$jobOrderID]);
        $definition = array(
            'jobOrderID' => $jobOrderID,
            'roleTitle' => $jobs[$jobOrderID],
            'questionSetKey' => $set['key'],
            'questionSetLabel' => $set['label'],
            'intro' => NESPWorkflow::getQuestionnaireIntroForSet($set['key']),
            'questions' => NESPWorkflow::getQuestionnaireQuestionsForSet($set['key']),
            'questionSetVersionID' => 0,
            'questionSetVersion' => 1
        );

        // NESPWorkflow keeps its role resolver private, but it is the canonical
        // published-set path used by issued questionnaires. Keep this isolated
        // adapter until that resolver is exposed as a public read-only API.
        if ($workflow instanceof NESPWorkflow)
        {
            $resolver = new ReflectionMethod($workflow, 'getPublishedQuestionSetVersionForRole');
            $published = $resolver->invoke($workflow, $jobs[$jobOrderID], $jobOrderID);
            if (is_array($published) && !empty($published['questions']))
            {
                $definition['questionSetKey'] = (string) $published['set_key'];
                $definition['questionSetLabel'] = (string) $published['display_name'];
                $definition['intro'] = trim((string) $published['description']) !== ''
                    ? (string) $published['description']
                    : NESPWorkflow::getQuestionnaireIntroForSet($published['set_key']);
                $definition['questions'] = $published['questions'];
                $definition['questionSetVersionID'] = (int) $published['question_set_version_id'];
                $definition['questionSetVersion'] = (int) $published['version_number'];
            }
        }

        $definition['fingerprint'] = self::questionnaireDefinitionFingerprint($definition);
        return $definition;
    }

    public static function questionnaireDefinitionFingerprint($definition)
    {
        if (!is_array($definition) || empty($definition['questions']))
        {
            return '';
        }

        return hash('sha256', json_encode(array(
            'jobOrderID' => (int) $definition['jobOrderID'],
            'questionSetKey' => (string) $definition['questionSetKey'],
            'questionSetVersionID' => isset($definition['questionSetVersionID'])
                ? (int) $definition['questionSetVersionID'] : 0,
            'questionSetVersion' => isset($definition['questionSetVersion'])
                ? (int) $definition['questionSetVersion'] : 1,
            'questions' => NESPWorkflow::normalizeQuestionnaireSnapshotQuestions(
                $definition['questions']
            )
        )));
    }

    public static function validateContact($post)
    {
        $post = is_array($post) ? $post : array();
        $contact = array(
            'firstName' => self::cleanText(isset($post['firstName']) ? $post['firstName'] : '', 80),
            'lastName' => self::cleanText(isset($post['lastName']) ? $post['lastName'] : '', 80),
            'email' => '',
            'phone' => self::cleanText(isset($post['phone']) ? $post['phone'] : '', 40),
            'city' => self::cleanText(isset($post['city']) ? $post['city'] : '', 80),
            'state' => strtoupper(self::cleanText(isset($post['state']) ? $post['state'] : '', 32)),
            'zip' => self::cleanText(isset($post['zip']) ? $post['zip'] : '', 20)
        );
        $errors = array();

        if ($contact['firstName'] === '')
        {
            $errors['firstName'] = 'Enter your first name.';
        }
        if ($contact['lastName'] === '')
        {
            $errors['lastName'] = 'Enter your last name.';
        }

        $emailValidation = NESPWorkflow::validateApplicantContactEmail(
            isset($post['email']) ? $post['email'] : ''
        );
        if (empty($emailValidation['ok']))
        {
            $errors['email'] = $emailValidation['error'];
        }
        else
        {
            $contact['email'] = $emailValidation['email'];
        }

        return array('ok' => count($errors) === 0, 'contact' => $contact, 'errors' => $errors);
    }

    public static function inspectOptionalResume($files, $field = 'resume')
    {
        if (!isset($files[$field]) || !is_array($files[$field]))
        {
            return array('hasUpload' => false, 'error' => '');
        }

        $file = $files[$field];
        $error = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE)
        {
            return array('hasUpload' => false, 'error' => '');
        }
        if ($error !== UPLOAD_ERR_OK)
        {
            return array('hasUpload' => false, 'error' => 'The resume upload did not finish. Try the file again or submit without it.');
        }

        $validationError = BoardApplicantIntake::validateResumeUpload($file);
        return array(
            'hasUpload' => $validationError === '',
            'error' => $validationError
        );
    }

    public static function extractQuestionnaireToken($invitationCopy)
    {
        $invitationCopy = html_entity_decode((string) $invitationCopy, ENT_QUOTES, 'UTF-8');
        if (!preg_match('/[?&]t=([A-Za-z0-9_-]{32,256})/', $invitationCopy, $matches))
        {
            return '';
        }
        return NESPWorkflow::normalizeQuestionnaireToken($matches[1]);
    }

    public static function persist($post, $files)
    {
        $jobOrderID = isset($post['jobOrderID']) ? (int) $post['jobOrderID'] : 0;
        $db = DatabaseConnection::getInstance();
        $workflow = new NESPWorkflow($db);
        $definition = self::questionnaireDefinitionForJob($jobOrderID, $workflow);
        if (empty($definition))
        {
            return self::failure('Choose one of the available NESP positions.');
        }

        $contactValidation = self::validateContact($post);
        $resume = self::inspectOptionalResume($files);
        if (empty($contactValidation['ok']) || $resume['error'] !== '')
        {
            return array(
                'ok' => false,
                'error' => 'Review the highlighted fields and try again.',
                'contactErrors' => $contactValidation['errors'],
                'resumeError' => $resume['error']
            );
        }

        $contact = $contactValidation['contact'];
        $lockName = self::submissionLockName($contact['email'], $jobOrderID);
        if (!self::acquireSubmissionLock($db, $lockName))
        {
            return self::failure('Your application is already being processed. Wait a moment and try once more.');
        }

        $transactionStarted = false;
        try
        {
            $jobOrders = new JobOrders();
            $jobOrder = $jobOrders->get($jobOrderID);
            if (empty($jobOrder)
                || empty($jobOrder['public'])
                || !empty($jobOrder['isAdminHidden'])
                || (isset($jobOrder['status']) && strtolower((string) $jobOrder['status']) === 'closed'))
            {
                return self::failure('This position is not currently accepting applications.');
            }

            $users = new Users();
            $automatedUser = $users->getAutomatedUser();
            if (empty($automatedUser['userID']))
            {
                return self::failure('Applications are temporarily unavailable. Please try again later.');
            }
            $actorUserID = (int) $automatedUser['userID'];

            $candidates = new Candidates();
            $pipelines = new Pipelines();
            $transactionStarted = $db->beginTransaction();
            if (!$transactionStarted)
            {
                return self::failure('Applications are temporarily busy. Please try again.');
            }

            $candidateMatch = NESPCareerApplicationSupport::resolveCandidateEmailMatch(
                $candidates,
                $contact['email']
            );
            if ($candidateMatch['status'] === 'inactive')
            {
                $db->rollbackTransaction();
                $transactionStarted = false;
                return self::failure('An earlier application uses this email address. Please contact the NESP hiring team for help.');
            }
            if ($candidateMatch['status'] === 'invalid')
            {
                throw new RuntimeException('Existing candidate lookup failed.');
            }

            $candidateID = $candidateMatch['status'] === 'active'
                ? (int) $candidateMatch['candidateID']
                : 0;
            if ($candidateID > 0)
            {
                $existingPipeline = $pipelines->get($candidateID, $jobOrderID);
                if (!empty($existingPipeline['candidateJobOrderID']))
                {
                    $db->rollbackTransaction();
                    $transactionStarted = false;
                    return array(
                        'ok' => true,
                        'duplicate' => true,
                        'jobTitle' => $definition['roleTitle'],
                        'resumeWarning' => '',
                        'questionnairePath' => ''
                    );
                }
            }
            else
            {
                $candidateID = $candidates->add(
                    $contact['firstName'],
                    '',
                    $contact['lastName'],
                    $contact['email'],
                    '',
                    '',
                    $contact['phone'],
                    '',
                    '',
                    '',
                    $contact['city'],
                    $contact['state'],
                    $contact['zip'],
                    self::SOURCE_LABEL,
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    'Submitted through the standalone NESP application and questionnaire.',
                    '',
                    '',
                    $actorUserID,
                    $actorUserID
                );
                if ((int) $candidateID <= 0)
                {
                    throw new RuntimeException('Candidate creation failed.');
                }
            }

            $pipelineResult = NESPCareerApplicationSupport::ensureCandidateJobOrderLink(
                $pipelines,
                $candidateID,
                $jobOrderID,
                $actorUserID
            );
            if (empty($pipelineResult['success']))
            {
                throw new RuntimeException('Candidate job link failed.');
            }

            $summary = 'Public NESP application and role-specific questionnaire submitted for human review.';
            $prepared = $workflow->prepareQuestionnaireRecordForHumanReview(
                $candidateID,
                $jobOrderID,
                $actorUserID,
                $summary,
                'needs_review'
            );
            if (empty($prepared['ok']) || empty($prepared['questionnaire_id']))
            {
                throw new RuntimeException('Questionnaire preparation failed.');
            }
            if (empty($prepared['link_generated']))
            {
                throw new RuntimeException('An active questionnaire already exists for this application.');
            }

            $questionnaireToken = self::extractQuestionnaireToken(
                isset($prepared['one_time_invitation_copy'])
                    ? $prepared['one_time_invitation_copy']
                    : ''
            );
            if ($questionnaireToken === '')
            {
                throw new RuntimeException('Questionnaire token extraction failed.');
            }

            $issued = $workflow->getQuestionnaireDetail((int) $prepared['questionnaire_id']);
            $issuedDefinition = array(
                'jobOrderID' => $jobOrderID,
                'roleTitle' => isset($issued['role_title']) ? $issued['role_title'] : '',
                'questionSetKey' => isset($issued['question_set_key']) ? $issued['question_set_key'] : '',
                'questionSetVersionID' => isset($issued['question_set_version_id'])
                    ? (int) $issued['question_set_version_id'] : 0,
                'questionSetVersion' => isset($issued['question_set_version'])
                    ? (int) $issued['question_set_version'] : 1,
                'questions' => isset($issued['questions']) ? $issued['questions'] : array()
            );
            if (empty($issued)
                || !hash_equals(
                    $definition['fingerprint'],
                    self::questionnaireDefinitionFingerprint($issuedDefinition)
                ))
            {
                throw new RuntimeException('Published questionnaire changed during submission.');
            }

            if (!$db->commitTransaction())
            {
                throw new RuntimeException('Application commit failed.');
            }
            $transactionStarted = false;

            $resumeWarning = '';
            if (!empty($resume['hasUpload']))
            {
                if (!is_uploaded_file($files['resume']['tmp_name']))
                {
                    $resumeWarning = 'Your application was received, but the resume could not be verified as an uploaded file.';
                }
                else
                {
                    $attachmentCreator = new AttachmentCreator();
                    $created = $attachmentCreator->createFromUpload(
                        DATA_ITEM_CANDIDATE,
                        $candidateID,
                        'resume',
                        false,
                        true
                    );
                    if (!$created)
                    {
                        $resumeWarning = 'Your application was received, but the resume could not be attached.';
                    }
                }
            }

            return array(
                'ok' => true,
                'duplicate' => false,
                'jobTitle' => $definition['roleTitle'],
                'resumeWarning' => $resumeWarning,
                'questionnairePath' => self::questionnairePath($questionnaireToken)
            );
        }
        catch (Throwable $error)
        {
            if ($transactionStarted)
            {
                $db->rollbackTransaction();
            }
            error_log('NESP simple public application failed: ' . get_class($error));
            return self::failure('We could not save the application. No duplicate was created. Please try again.');
        }
        finally
        {
            self::releaseSubmissionLock($db, $lockName);
        }
    }

    public static function submissionLockName($email, $jobOrderID)
    {
        return 'nesp_simple_' . substr(hash('sha256', strtolower(trim((string) $email)) . '|' . (int) $jobOrderID), 0, 40);
    }

    public static function questionnairePath($token)
    {
        $token = NESPWorkflow::normalizeQuestionnaireToken($token);
        if ($token === '')
        {
            return '';
        }
        return '/modules/nesp/screeningQuestionnaire.php?t=' . rawurlencode($token);
    }

    public static function acquireSubmissionLock($db, $lockName)
    {
        $row = $db->getAssoc(sprintf(
            'SELECT GET_LOCK(%s, 5) AS acquired',
            $db->makeQueryString($lockName)
        ));
        return !empty($row) && (int) $row['acquired'] === 1;
    }

    public static function releaseSubmissionLock($db, $lockName)
    {
        $db->getAssoc(sprintf(
            'SELECT RELEASE_LOCK(%s) AS released',
            $db->makeQueryString($lockName)
        ));
    }

    private static function failure($message)
    {
        return array(
            'ok' => false,
            'error' => (string) $message,
            'contactErrors' => array(),
            'resumeError' => ''
        );
    }

    private static function cleanText($value, $maximumLength)
    {
        $value = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $value));
        return substr($value, 0, (int) $maximumLength);
    }

    private static function base64UrlEncode($value)
    {
        return rtrim(strtr(base64_encode((string) $value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode($value)
    {
        $value = strtr((string) $value, '-_', '+/');
        $padding = strlen($value) % 4;
        if ($padding > 0)
        {
            $value .= str_repeat('=', 4 - $padding);
        }
        $decoded = base64_decode($value, true);
        return $decoded === false ? '' : $decoded;
    }
}
