<?php
namespace OpenCATS\Tests\IntegrationTests;

class NESPWorkflowSchemaTest extends DatabaseTestCase
{
    public function testNESPWorkflowTablesAreCreated()
    {
        $expectedTables = array(
            'nesp_feature_flag',
            'nesp_workflow_stage',
            'nesp_candidate_workflow',
            'nesp_interviewer_profile',
            'nesp_interviewer_candidate_grant',
            'nesp_interviewer_role_rule',
            'nesp_interviewer_job_role',
            'nesp_interviewer_availability',
            'nesp_interviewer_availability_override',
            'nesp_interviewer_blackout',
            'nesp_interview_slot',
            'nesp_interview',
            'nesp_scorecard_template',
            'nesp_scorecard_response',
            'nesp_integration_status',
            'nesp_google_calendar_connection',
            'nesp_recruiting_campaign_control',
            'nesp_vapi_phone_screen',
            'nesp_vapi_phone_screen_setting',
            'nesp_vapi_availability_block',
            'nesp_vapi_blackout_date',
            'nesp_vapi_scheduling_activity',
            'nesp_vapi_webhook_event',
            'nesp_question_set',
            'nesp_question_set_version',
            'nesp_question_set_question',
            'nesp_question_set_role_match',
            'nesp_screening_questionnaire',
            'nesp_screening_questionnaire_answer',
            'nesp_screening_questionnaire_activity',
            'nesp_zoom_interview',
            'nesp_ai_candidate_review',
            'nesp_audit_event',
            'nesp_session_security_event',
            'nesp_staffing_schedule_history',
            'nesp_staffing_import_batch',
            'nesp_staffing_import_row',
            'nesp_staffing_import_issue',
            'nesp_historical_job_staffing',
            'nesp_staffing_forecast',
            'nesp_staffing_recommendation'
        );

        foreach ($expectedTables as $table)
        {
            $this->assertSame(1, $this->countMatchingTables($table), $table . ' table is missing.');
        }
    }

    public function testNESPWorkflowSeedDataIsSafeByDefault()
    {
        $this->assertSame(11, $this->countRows('nesp_feature_flag'));
        $this->assertSame(0, $this->countRowsWhere('nesp_feature_flag', 'is_enabled = 1'));
        $this->assertSame(17, $this->countRows('nesp_workflow_stage'));
        $this->assertSame(5, $this->countRowsWhere('nesp_integration_status', "status_key = 'disabled'"));
        $this->assertSame(0, $this->countRows('nesp_interviewer_profile'));
        $this->assertSame(0, $this->countRows('nesp_google_calendar_connection'));
        $this->assertSame(0, $this->countRows('nesp_interviewer_role_rule'));
        $this->assertSame(0, $this->countRows('nesp_interviewer_job_role'));
        $this->assertSame(0, $this->countRows('nesp_interviewer_availability'));
        $this->assertSame(0, $this->countRows('nesp_interviewer_availability_override'));
        $this->assertSame(0, $this->countRows('nesp_interviewer_blackout'));
        $this->assertSame(0, $this->countRows('nesp_interview_slot'));
        $this->assertSame(0, $this->countRows('nesp_candidate_workflow'));
        $this->assertSame(0, $this->countRows('nesp_recruiting_campaign_control'));
        $this->assertSame(0, $this->countRows('nesp_question_set'));
        $this->assertSame(0, $this->countRows('nesp_question_set_version'));
        $this->assertSame(1, $this->countRowsWhere('nesp_scorecard_template', "template_key = 'nesp_standard_interview' AND is_enabled = 0"));
        $this->assertSame(11, $this->countRowsWhere('nesp_feature_flag', "flag_key LIKE 'NESP_%'"));
        $this->assertSame(1, $this->countRowsWhere('nesp_feature_flag', "flag_key = 'NESP_INTERVIEWER_AVAILABILITY_ENABLED' AND is_enabled = 0"));
        $this->assertSame(1, $this->countRowsWhere('nesp_feature_flag', "flag_key = 'NESP_INTERVIEWER_ZOOM_LINKS_ENABLED' AND is_enabled = 0"));
        $this->assertSame(1, $this->countRowsWhere('nesp_feature_flag', "flag_key = 'NESP_GOOGLE_CALENDAR_FREEBUSY_ENABLED' AND is_enabled = 0"));
        $this->assertSame(1, $this->countRowsWhere('nesp_integration_status', "integration_key = 'google_calendar_freebusy' AND status_key = 'disabled'"));
        $this->assertSame(1, $this->countRowsWhere('nesp_vapi_phone_screen_setting', "setting_key = 'timezone' AND setting_value = 'America/New_York'"));
        $this->assertSame(1, $this->countRowsWhere('nesp_vapi_phone_screen_setting', "setting_key = 'slot_minutes' AND setting_value = '15'"));
        $this->assertSame(1, $this->countRowsWhere('nesp_vapi_phone_screen_setting', "setting_key = 'call_duration_minutes' AND setting_value = '10'"));
        $this->assertSame(1, $this->countRowsWhere('nesp_vapi_phone_screen_setting', "setting_key = 'buffer_minutes' AND setting_value = '5'"));
        $this->assertSame(1, $this->countRowsWhere('nesp_vapi_phone_screen_setting', "setting_key = 'min_booking_notice_minutes' AND setting_value = '120'"));
        $this->assertSame(1, $this->countRowsWhere('nesp_vapi_availability_block', "weekday = 1 AND start_time = '09:00:00' AND end_time = '18:00:00'"));
        $this->assertSame(1, $this->countRowsWhere('nesp_vapi_availability_block', "weekday = 6 AND start_time = '09:00:00' AND end_time = '13:00:00'"));
        $this->assertSame(0, $this->countRows('nesp_screening_questionnaire'));
        $this->assertSame(0, $this->countRows('nesp_screening_questionnaire_answer'));
        $this->assertSame(0, $this->countRows('nesp_screening_questionnaire_activity'));
    }

    public function testNESPPhase2ColumnsArePresent()
    {
        $this->assertSame(1, $this->countMatchingColumns('nesp_candidate_workflow', 'waiting_on_key'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_candidate_workflow', 'summary'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_candidate_workflow', 'next_action_label'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_candidate_workflow', 'due_at'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_interviewer_profile', 'can_add_notes'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_interviewer_profile', 'can_submit_scorecard'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_interviewer_profile', 'account_state_key'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_interviewer_profile', 'availability_status_key'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_interviewer_profile', 'max_interviews_per_day'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_interviewer_profile', 'max_interviews_per_week'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_interviewer_profile', 'min_notice_minutes'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_interviewer_profile', 'email_warning'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_interviewer_profile', 'default_zoom_join_url'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_interviewer_job_role', 'joborder_id'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_interviewer_availability_override', 'override_type_key'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_interviewer_blackout', 'private_reason'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_scorecard_response', 'locked_at'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_scorecard_response', 'unlocked_at'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_scorecard_response', 'unlocked_by_user_id'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_interviewer_role_rule', 'assignment_mode'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_interviewer_availability', 'slot_minutes'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_interview_slot', 'zoom_status_key'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_interview', 'manual_zoom_join_url'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_interview', 'timezone'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_interview', 'invitation_status_key'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_interview', 'invitation_preview_text'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_interview', 'outcome_key'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_interview', 'cancelled_at'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_google_calendar_connection', 'interviewer_profile_id'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_google_calendar_connection', 'user_id'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_google_calendar_connection', 'encrypted_calendar_id'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_google_calendar_connection', 'encrypted_access_token'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_google_calendar_connection', 'encrypted_refresh_token'));
        $this->assertSame(1, $this->countUniqueIndexes('nesp_google_calendar_connection', 'IDX_google_calendar_interviewer'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_vapi_phone_screen', 'call_request_key'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_vapi_phone_screen', 'destination_phone_hash'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_vapi_phone_screen', 'destination_phone_last4'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_vapi_phone_screen', 'consent_status'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_vapi_phone_screen', 'consent_response_raw'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_vapi_phone_screen', 'consent_accepted_at'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_vapi_phone_screen', 'transcript_text'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_vapi_phone_screen', 'structured_result_json'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_vapi_phone_screen', 'provider_end_reason'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_vapi_phone_screen', 'scheduling_token_hash'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_vapi_phone_screen', 'scheduling_token_expires_at'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_vapi_phone_screen', 'scheduling_link_url'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_vapi_phone_screen', 'invitation_copy_text'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_vapi_phone_screen', 'scheduled_start_at_utc'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_vapi_phone_screen', 'scheduled_start_et'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_vapi_phone_screen', 'call_attempt_count'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_vapi_phone_screen_setting', 'setting_key'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_vapi_availability_block', 'weekday'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_vapi_blackout_date', 'blackout_date'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_vapi_scheduling_activity', 'activity_key'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_vapi_webhook_event', 'provider_event_id'));
        $this->assertSame(1, $this->countUniqueIndexes('nesp_vapi_webhook_event', 'IDX_provider_event_id'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_screening_questionnaire', 'token_hash'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_screening_questionnaire', 'question_set_key'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_screening_questionnaire', 'question_set_version_id'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_screening_questionnaire', 'question_snapshot_json'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_screening_questionnaire', 'reviewer_profile_id'));
        $this->assertSame(0, $this->countMatchingColumns('nesp_screening_questionnaire', 'link_url'));
        $this->assertSame(0, $this->countMatchingColumns('nesp_screening_questionnaire', 'invitation_copy_text'));
        $this->assertSame(1, $this->countUniqueIndexes('nesp_screening_questionnaire', 'IDX_questionnaire_token_hash'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_screening_questionnaire_answer', 'answer_text'));
        $this->assertSame(1, $this->countUniqueIndexes('nesp_screening_questionnaire_answer', 'IDX_questionnaire_answer_key'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_screening_questionnaire_activity', 'activity_key'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_recruiting_campaign_control', 'platform_key'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_recruiting_campaign_control', 'manual_spend'));
        $this->assertSame(1, $this->countUniqueIndexes('nesp_recruiting_campaign_control', 'IDX_platform_key'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_staffing_import_batch', 'undone_at'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_staffing_import_row', 'source_row_hash'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_staffing_import_issue', 'status_key'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_historical_job_staffing', 'total_required_staff'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_historical_job_staffing', 'data_quality_status'));
        $this->assertSame(0, $this->countUniqueIndexes('nesp_staffing_import_batch', 'IDX_nesp_import_source'));
    }

    public function testQuestionnaireSchemaDefinitionsStayConsistentAndDoNotStorePlaintextInvitations()
    {
        $catsSchemaColumns = $this->extractCreateTableColumns(file_get_contents('db/cats_schema.sql'), 'nesp_screening_questionnaire');
        $schemaPhpColumns = $this->extractCreateTableColumns(file_get_contents('modules/install/Schema.php'), 'nesp_screening_questionnaire');
        $additiveColumns = $this->extractCreateTableColumns(file_get_contents('db/nesp_screening_questionnaire_additive.sql'), 'nesp_screening_questionnaire');

        $this->assertSame($catsSchemaColumns, $schemaPhpColumns);
        $this->assertSame($catsSchemaColumns, $additiveColumns);
        $this->assertNotContains('link_url', $catsSchemaColumns);
        $this->assertNotContains('invitation_copy_text', $catsSchemaColumns);
        $this->assertContains('token_hash', $catsSchemaColumns);
        $this->assertContains('question_snapshot_json', $catsSchemaColumns);
    }

    public function testQuestionnaireWorkflowRunsOnAdditiveMigrationSchema()
    {
        global $mySQLConnection;

        include_once(LEGACY_ROOT . '/lib/DatabaseConnection.php');
        include_once(LEGACY_ROOT . '/lib/NESPWorkflow.php');

        $this->dropQuestionnaireTables();
        $this->mySQLQueryMultipleLocal(file_get_contents('db/nesp_screening_questionnaire_additive.sql'), ";\n");

        $candidateID = $this->insertFakeCandidate('Avery', 'Fixture');
        $jobOrderID = $this->insertFakeJobOrder('Weekend Staff Portrait & Team Photographer - Youth Sports');
        $this->insertFakeCandidateJobOrder($candidateID, $jobOrderID);

        $workflow = new \NESPWorkflow(\DatabaseConnection::getInstance());
        $result = $workflow->requestQuestionnaire($candidateID, $jobOrderID, 1);

        $this->assertIsArray($result);
        $this->assertTrue($result['link_generated']);
        $this->assertGreaterThan(0, $result['questionnaire_id']);
        $this->assertStringContainsString('Hi Avery', $result['one_time_invitation_copy']);

        $token = $this->extractQuestionnaireToken($result['one_time_invitation_copy']);
        $this->assertNotSame('', $token);
        $this->assertSame(0, $this->countMatchingColumns('nesp_screening_questionnaire', 'link_url'));
        $this->assertSame(0, $this->countMatchingColumns('nesp_screening_questionnaire', 'invitation_copy_text'));
        $this->assertSame(1, $this->countRowsWhere(
            'nesp_screening_questionnaire',
            sprintf(
                "screening_questionnaire_id = %d AND token_hash = '%s'",
                (int) $result['questionnaire_id'],
                mysqli_real_escape_string($mySQLConnection, \NESPWorkflow::questionnaireTokenHash($token))
            )
        ));

        $duplicate = $workflow->requestQuestionnaire($candidateID, $jobOrderID, 1);
        $this->assertIsArray($duplicate);
        $this->assertFalse($duplicate['link_generated']);
        $this->assertSame('', $duplicate['one_time_invitation_copy']);

        $regenerated = $workflow->regenerateQuestionnaireLink($result['questionnaire_id'], 1);
        $this->assertIsArray($regenerated);
        $this->assertTrue($regenerated['link_generated']);
        $this->assertStringContainsString('Hi Avery', $regenerated['one_time_invitation_copy']);
        $regeneratedToken = $this->extractQuestionnaireToken($regenerated['one_time_invitation_copy']);
        $this->assertNotSame($token, $regeneratedToken);
        $this->assertFalse($workflow->getQuestionnairePageByToken($token)['ok']);

        $page = $workflow->getQuestionnairePageByToken($regeneratedToken);
        $this->assertTrue($page['ok']);
        $answers = array();
        foreach ($page['questionnaire']['questions'] as $question)
        {
            $answers[$question['key']] = 'Fixture answer for ' . $question['key'];
        }

        $submit = $workflow->submitQuestionnaireFromToken($regeneratedToken, $answers);
        $this->assertSame(array('ok' => true, 'state' => 'completed'), $submit);
        $duplicateSubmit = $workflow->submitQuestionnaireFromToken($regeneratedToken, $answers);
        $this->assertFalse($duplicateSubmit['ok']);
        $this->assertSame('submitted', $duplicateSubmit['state']);
        $this->assertSame(0, $this->countRows('nesp_candidate_workflow'));
        $this->assertSame(1, $this->countRowsWhere(
            'candidate_joborder',
            sprintf('candidate_id = %d AND joborder_id = %d AND status = 0', $candidateID, $jobOrderID)
        ));
    }

    public function testQuestionnaireRepairMigrationIsNotNeededForHashedTokenSchema()
    {
        $this->dropQuestionnaireTables();
        $this->mySQLQueryMultipleLocal(file_get_contents('db/nesp_screening_questionnaire_additive.sql'), ";\n");
        $this->mySQLQueryMultipleLocal(file_get_contents('db/nesp_screening_questionnaire_additive.sql'), ";\n");

        $this->assertSame(1, $this->countMatchingTables('nesp_screening_questionnaire'));
        $this->assertSame(1, $this->countMatchingTables('nesp_screening_questionnaire_answer'));
        $this->assertSame(1, $this->countMatchingTables('nesp_screening_questionnaire_activity'));
        $this->assertSame(0, $this->countMatchingColumns('nesp_screening_questionnaire', 'link_url'));
        $this->assertSame(0, $this->countMatchingColumns('nesp_screening_questionnaire', 'invitation_copy_text'));
    }

    public function testQuestionnaireIssuedLinksUseStoredSnapshot()
    {
        include_once(LEGACY_ROOT . '/lib/DatabaseConnection.php');
        include_once(LEGACY_ROOT . '/lib/NESPWorkflow.php');

        $workflow = new \NESPWorkflow(\DatabaseConnection::getInstance());
        $workflow->ensureDefaultQuestionSetsSeeded(1);

        $candidateID = $this->insertFakeCandidate('Snapshot', 'Applicant');
        $jobOrderID = $this->insertFakeJobOrder('Weekend Staff Portrait & Team Photographer - Youth Sports');
        $this->insertFakeCandidateJobOrder($candidateID, $jobOrderID);

        $result = $workflow->requestQuestionnaire($candidateID, $jobOrderID, 1);
        $token = $this->extractQuestionnaireToken($result['one_time_invitation_copy']);
        $page = $workflow->getQuestionnairePageByToken($token);
        $this->assertTrue($page['ok']);
        $originalLabel = $page['questionnaire']['questions'][0]['label'];
        $originalSetLabel = $page['questionnaire']['question_set_label'];
        $originalIntro = $page['questionnaire']['question_set_intro'];
        $versionID = (int) $page['questionnaire']['question_set_version_id'];
        $this->assertStringContainsString('Photographer', $originalSetLabel);

        $draftID = $workflow->createQuestionSetDraftFromVersion(0, $versionID, 1);
        $draft = $workflow->getQuestionSetVersionDetail($draftID);
        $draft['questions'][0]['label'] = 'Changed future-only fixture question';
        $input = array(
            'displayName' => 'Draft-only fixture label',
            'description' => 'Draft-only fixture intro',
            'roleMatches' => array(array('match_text' => 'draft-only unmatched role', 'joborder_id' => 0)),
            'questionKey' => array(),
            'questionLabel' => array(),
            'questionHelp' => array(),
            'questionType' => array(),
            'questionRequired' => array(),
            'questionChoices' => array(),
            'questionSortOrder' => array()
        );
        foreach ($draft['questions'] as $question)
        {
            $input['questionKey'][] = $question['key'];
            $input['questionLabel'][] = $question['label'];
            $input['questionHelp'][] = $question['help'];
            $input['questionType'][] = $question['type'];
            $input['questionRequired'][] = !empty($question['required']) ? '1' : '0';
            $input['questionChoices'][] = implode("\n", $question['choices']);
            $input['questionSortOrder'][] = (string) $question['sort_order'];
        }
        $this->assertTrue($workflow->saveQuestionSetDraft($draftID, $input, 1)['ok']);
        $this->assertSame($originalSetLabel, $workflow->getQuestionSetVersionDetail($versionID)['display_name']);

        $this->mySQLQueryLocal(sprintf(
            "UPDATE nesp_question_set
             SET display_name = 'Shared mutable fixture label',
                 description = 'Shared mutable fixture intro'
             WHERE question_set_id = %d",
            (int) $draft['question_set_id']
        ));
        $mutatedIssuedLink = $workflow->getQuestionnairePageByToken($token);
        $this->assertTrue($mutatedIssuedLink['ok']);
        $this->assertSame($originalSetLabel, $mutatedIssuedLink['questionnaire']['question_set_label']);
        $this->assertSame($originalIntro, $mutatedIssuedLink['questionnaire']['question_set_intro']);

        $newCandidateID = $this->insertFakeCandidate('Future', 'Applicant');
        $this->insertFakeCandidateJobOrder($newCandidateID, $jobOrderID);
        $activePreview = $workflow->getCandidateQuestionnairePreview($newCandidateID, $jobOrderID);
        $this->assertSame($originalSetLabel, $activePreview['question_set_label']);
        $this->assertSame($originalIntro, $activePreview['question_set_intro']);
        $this->assertSame($originalLabel, $activePreview['questions'][0]['label']);

        $this->assertTrue($workflow->publishQuestionSetDraft($draftID, 1));

        $sameIssuedLink = $workflow->getQuestionnairePageByToken($token);
        $this->assertTrue($sameIssuedLink['ok']);
        $this->assertSame($originalSetLabel, $sameIssuedLink['questionnaire']['question_set_label']);
        $this->assertSame($originalIntro, $sameIssuedLink['questionnaire']['question_set_intro']);
        $this->assertSame($originalLabel, $sameIssuedLink['questionnaire']['questions'][0]['label']);
        $this->assertNotSame('Changed future-only fixture question', $sameIssuedLink['questionnaire']['questions'][0]['label']);

        $publishedPreview = $workflow->getCandidateQuestionnairePreview($newCandidateID, $jobOrderID);
        $this->assertSame('Draft-only fixture label', $publishedPreview['question_set_label']);
        $this->assertSame('Draft-only fixture intro', $publishedPreview['question_set_intro']);
        $this->assertSame('Changed future-only fixture question', $publishedPreview['questions'][0]['label']);
    }

    public function testQuestionnaireDraftRoleMatchesAffectRoutingOnlyAfterPublish()
    {
        include_once(LEGACY_ROOT . '/lib/DatabaseConnection.php');
        include_once(LEGACY_ROOT . '/lib/NESPWorkflow.php');

        $workflow = new \NESPWorkflow(\DatabaseConnection::getInstance());
        $workflow->ensureDefaultQuestionSetsSeeded(1);

        $fieldCandidateID = $this->insertFakeCandidate('Field', 'Fixture');
        $fieldJobOrderID = $this->insertFakeJobOrder('Weekend Table Greeter / Field Assistant');
        $this->insertFakeCandidateJobOrder($fieldCandidateID, $fieldJobOrderID);
        $fieldPreview = $workflow->getCandidateQuestionnairePreview($fieldCandidateID, $fieldJobOrderID);
        $this->assertSame('Field Staff Pre-Interview', $fieldPreview['question_set_label']);

        $photoCandidateID = $this->insertFakeCandidate('Photo', 'Fixture');
        $photoJobOrderID = $this->insertFakeJobOrder('Weekend Staff Portrait & Team Photographer - Youth Sports');
        $this->insertFakeCandidateJobOrder($photoCandidateID, $photoJobOrderID);
        $photoPreview = $workflow->getCandidateQuestionnairePreview($photoCandidateID, $photoJobOrderID);
        $this->assertSame('Photographer Pre-Interview', $photoPreview['question_set_label']);

        $draftID = $workflow->createQuestionSetDraftFromVersion(0, (int) $photoPreview['question_set_version_id'], 1);
        $draft = $workflow->getQuestionSetVersionDetail($draftID);
        $input = array(
            'displayName' => 'Photographer Draft Routed To Field',
            'description' => $draft['description'],
            'roleMatches' => array(array('match_text' => '', 'joborder_id' => $fieldJobOrderID)),
            'questionKey' => array(),
            'questionLabel' => array(),
            'questionHelp' => array(),
            'questionType' => array(),
            'questionRequired' => array(),
            'questionChoices' => array(),
            'questionSortOrder' => array()
        );
        foreach ($draft['questions'] as $question)
        {
            $input['questionKey'][] = $question['key'];
            $input['questionLabel'][] = $question['label'];
            $input['questionHelp'][] = $question['help'];
            $input['questionType'][] = $question['type'];
            $input['questionRequired'][] = !empty($question['required']) ? '1' : '0';
            $input['questionChoices'][] = implode("\n", $question['choices']);
            $input['questionSortOrder'][] = (string) $question['sort_order'];
        }

        $this->assertTrue($workflow->saveQuestionSetDraft($draftID, $input, 1)['ok']);
        $fieldPreviewBeforePublish = $workflow->getCandidateQuestionnairePreview($fieldCandidateID, $fieldJobOrderID);
        $this->assertSame('Field Staff Pre-Interview', $fieldPreviewBeforePublish['question_set_label']);

        $this->assertTrue($workflow->publishQuestionSetDraft($draftID, 1));
        $fieldPreviewAfterPublish = $workflow->getCandidateQuestionnairePreview($fieldCandidateID, $fieldJobOrderID);
        $this->assertSame('Photographer Draft Routed To Field', $fieldPreviewAfterPublish['question_set_label']);
    }

    public function testInterviewerDirectAccessRequiresActiveExactNonRevokedGrant()
    {
        include_once(LEGACY_ROOT . '/lib/DatabaseConnection.php');
        include_once(LEGACY_ROOT . '/lib/NESPWorkflow.php');

        $candidateID = $this->insertFakeCandidate('Scoped', 'Candidate');
        $jobOrderID = $this->insertFakeJobOrder('Customer Service');
        $wrongJobOrderID = $this->insertFakeJobOrder('Field Assistant');
        $this->insertFakeCandidateJobOrder($candidateID, $jobOrderID);
        $this->insertFakeCandidateJobOrder($candidateID, $wrongJobOrderID);

        $this->mySQLQueryLocal(
            "INSERT INTO user (user_name, password, access_level, can_change_password, is_test_user, email, first_name, last_name, categories, can_see_eeo_info)
             VALUES ('nesp.scoped.fixture', 'hash', 100, 1, 0, 'scoped@example.test', 'Scoped', 'Fixture', 'nesp_interviewer', 0)"
        );
        $userID = $this->lastInsertID();
        $this->mySQLQueryLocal(sprintf(
            "INSERT INTO nesp_interviewer_profile (user_id, display_name, email, role_key, is_active, account_state_key, date_created, date_modified)
             VALUES (%d, 'Scoped Fixture', 'scoped@example.test', 'interviewer', 1, 'active', NOW(), NOW())",
            $userID
        ));
        $profileID = $this->lastInsertID();

        $workflow = new \NESPWorkflow(\DatabaseConnection::getInstance());
        $this->assertFalse($workflow->userCanAccessCandidate($userID, $candidateID, $jobOrderID));

        $this->mySQLQueryLocal(sprintf(
            "INSERT INTO nesp_interviewer_candidate_grant (interviewer_profile_id, candidate_id, joborder_id, granted_by_user_id, access_level_key, can_view_resume, can_add_notes, can_submit_scorecard, date_granted)
             VALUES (%d, %d, %d, 1, 'interview', 1, 1, 1, NOW())",
            $profileID,
            $candidateID,
            $jobOrderID
        ));
        $grantID = $this->lastInsertID();

        $this->assertTrue($workflow->userCanAccessCandidate($userID, $candidateID, $jobOrderID));
        $this->assertFalse($workflow->userCanAccessCandidate($userID, $candidateID, $wrongJobOrderID));

        $this->assertTrue($workflow->revokeCandidateGrant($grantID, 1));
        $this->assertFalse($workflow->userCanAccessCandidate($userID, $candidateID, $jobOrderID));

        $this->mySQLQueryLocal(sprintf(
            "UPDATE nesp_interviewer_candidate_grant SET date_revoked = NULL WHERE grant_id = %d",
            $grantID
        ));
        $this->mySQLQueryLocal(sprintf(
            "UPDATE nesp_interviewer_profile SET is_active = 0, account_state_key = 'suspended' WHERE interviewer_profile_id = %d",
            $profileID
        ));
        $this->assertFalse($workflow->userCanAccessCandidate($userID, $candidateID, $jobOrderID));
    }

    public function testCareerPortalApplicationRoutesToNeedsCraigWorkflowOnce()
    {
        include_once(LEGACY_ROOT . '/lib/DatabaseConnection.php');
        include_once(LEGACY_ROOT . '/lib/NESPWorkflow.php');

        $candidateID = $this->insertFakeCandidate('Public', 'Applicant');
        $jobOrderID = $this->insertFakeJobOrder('Weekend Staff Portrait & Team Photographer - Youth Sports');
        $this->insertFakeCandidateJobOrder($candidateID, $jobOrderID);

        $workflow = new \NESPWorkflow(\DatabaseConnection::getInstance());
        $this->assertFalse($workflow->routeCareerPortalApplicationToNeedsCraig($candidateID, $jobOrderID, 1, true));
        $this->assertSame(0, $this->countRowsWhere(
            'nesp_candidate_workflow',
            sprintf('candidate_id = %d AND joborder_id = %d', $candidateID, $jobOrderID)
        ));

        $this->mySQLQueryLocal(
            "UPDATE nesp_feature_flag
             SET is_enabled = 1
             WHERE flag_key = 'NESP_WORKFLOW_ENABLED'"
        );

        $this->assertTrue($workflow->routeCareerPortalApplicationToNeedsCraig($candidateID, $jobOrderID, 1, true));

        $this->assertSame(1, $this->countRowsWhere(
            'nesp_candidate_workflow',
            sprintf('candidate_id = %d AND joborder_id = %d', $candidateID, $jobOrderID)
        ));
        $this->assertSame(1, $this->countRowsWhere(
            'nesp_candidate_workflow',
            sprintf(
                "candidate_id = %d AND joborder_id = %d AND waiting_on_key = 'Craig' AND next_action_label = 'Review application' AND workflow_stage_id = (SELECT workflow_stage_id FROM nesp_workflow_stage WHERE stage_key = 'new' LIMIT 1)",
                $candidateID,
                $jobOrderID
            )
        ));
        $this->assertSame(1, $this->countRowsWhere(
            'nesp_audit_event',
            "event_type = 'candidate_workflow_stage_changed'"
        ));

        $this->assertTrue($workflow->routeCareerPortalApplicationToNeedsCraig($candidateID, $jobOrderID, 1, true));
        $this->assertSame(1, $this->countRowsWhere(
            'nesp_candidate_workflow',
            sprintf('candidate_id = %d AND joborder_id = %d', $candidateID, $jobOrderID)
        ));
    }

    public function testQuestionnaireRollbackRemovesAdditiveTables()
    {
        $this->dropQuestionnaireTables();
        $this->mySQLQueryMultipleLocal(file_get_contents('db/nesp_screening_questionnaire_additive.sql'), ";\n");
        $this->mySQLQueryMultipleLocal(file_get_contents('db/nesp_screening_questionnaire_rollback.sql'), ";\n");

        $this->assertSame(0, $this->countMatchingTables('nesp_screening_questionnaire'));
        $this->assertSame(0, $this->countMatchingTables('nesp_screening_questionnaire_answer'));
        $this->assertSame(0, $this->countMatchingTables('nesp_screening_questionnaire_activity'));
    }

    public function testQuestionSetAdminRollbackRemovesAdditiveTablesAndColumns()
    {
        $this->mySQLQueryMultipleLocal(file_get_contents('db/nesp_question_set_admin_additive.sql'), ";\n");
        $this->assertSame(1, $this->countMatchingTables('nesp_question_set'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_screening_questionnaire', 'question_snapshot_json'));

        $this->mySQLQueryMultipleLocal(file_get_contents('db/nesp_question_set_admin_rollback.sql'), ";\n");
        $this->assertSame(0, $this->countMatchingTables('nesp_question_set'));
        $this->assertSame(0, $this->countMatchingTables('nesp_question_set_version'));
        $this->assertSame(0, $this->countMatchingTables('nesp_question_set_question'));
        $this->assertSame(0, $this->countMatchingTables('nesp_question_set_role_match'));
        $this->assertSame(0, $this->countMatchingColumns('nesp_screening_questionnaire', 'question_snapshot_json'));
        $this->mySQLQueryMultipleLocal(file_get_contents('db/nesp_question_set_admin_additive.sql'), ";\n");
    }

    private function countMatchingTables($table)
    {
        global $mySQLConnection;

        $result = mysqli_query(
            $mySQLConnection,
            sprintf("SHOW TABLES LIKE '%s'", mysqli_real_escape_string($mySQLConnection, $table))
        );

        return mysqli_num_rows($result);
    }

    private function countRows($table)
    {
        return $this->countRowsWhere($table, '1 = 1');
    }

    private function countMatchingColumns($table, $column)
    {
        global $mySQLConnection;

        $result = mysqli_query(
            $mySQLConnection,
            sprintf(
                "SHOW COLUMNS FROM `%s` LIKE '%s'",
                mysqli_real_escape_string($mySQLConnection, $table),
                mysqli_real_escape_string($mySQLConnection, $column)
            )
        );

        return mysqli_num_rows($result);
    }

    private function countRowsWhere($table, $whereClause)
    {
        global $mySQLConnection;

        $result = mysqli_query(
            $mySQLConnection,
            sprintf(
                'SELECT COUNT(*) AS total FROM `%s` WHERE %s',
                mysqli_real_escape_string($mySQLConnection, $table),
                $whereClause
            )
        );
        $row = mysqli_fetch_assoc($result);

        return (int) $row['total'];
    }

    private function countUniqueIndexes($table, $index)
    {
        global $mySQLConnection;

        $result = mysqli_query(
            $mySQLConnection,
            sprintf(
                "SHOW INDEX FROM `%s` WHERE Key_name = '%s' AND Non_unique = 0",
                mysqli_real_escape_string($mySQLConnection, $table),
                mysqli_real_escape_string($mySQLConnection, $index)
            )
        );

        $indexes = array();
        while ($row = mysqli_fetch_assoc($result))
        {
            $indexes[$row['Key_name']] = true;
        }

        return count($indexes);
    }

    private function extractCreateTableColumns($source, $table)
    {
        $pattern = '/CREATE TABLE(?: IF NOT EXISTS)? `'
            . preg_quote($table, '/')
            . '`\s*\((.*?)\)\s*ENGINE=/s';
        $this->assertMatchesRegularExpression($pattern, $source, $table . ' create statement not found.');
        preg_match($pattern, $source, $matches);

        preg_match_all('/^\s*`([^`]+)`\s+/m', $matches[1], $columnMatches);
        return $columnMatches[1];
    }

    private function dropQuestionnaireTables()
    {
        $this->mySQLQueryLocal('DROP TABLE IF EXISTS nesp_screening_questionnaire_activity');
        $this->mySQLQueryLocal('DROP TABLE IF EXISTS nesp_screening_questionnaire_answer');
        $this->mySQLQueryLocal('DROP TABLE IF EXISTS nesp_screening_questionnaire');
    }

    private function insertFakeCandidate($firstName, $lastName)
    {
        $this->mySQLQueryLocal(
            sprintf(
                "INSERT INTO candidate (first_name, last_name, email1, is_active, entered_by, owner, date_created, date_modified)
                 VALUES ('%s', '%s', 'fixture@example.test', 1, 1, 1, NOW(), NOW())",
                $this->escape($firstName),
                $this->escape($lastName)
            )
        );
        return $this->lastInsertID();
    }

    private function insertFakeJobOrder($title)
    {
        $this->mySQLQueryLocal(
            sprintf(
                "INSERT INTO joborder (title, company_id, entered_by, owner, status, public, openings, openings_available, date_created, date_modified)
                 VALUES ('%s', 1, 1, 1, 'Active', 1, 1, 1, NOW(), NOW())",
                $this->escape($title)
            )
        );
        return $this->lastInsertID();
    }

    private function insertFakeCandidateJobOrder($candidateID, $jobOrderID)
    {
        $this->mySQLQueryLocal(
            sprintf(
                'INSERT INTO candidate_joborder (candidate_id, joborder_id, status, date_submitted, date_created, date_modified)
                 VALUES (%d, %d, 0, NOW(), NOW(), NOW())',
                (int) $candidateID,
                (int) $jobOrderID
            )
        );
    }

    private function extractQuestionnaireToken($copy)
    {
        if (!preg_match('/screeningQuestionnaire\.php\?t=([^\s]+)/', $copy, $matches))
        {
            return '';
        }

        return rawurldecode($matches[1]);
    }

    private function mySQLQueryMultipleLocal($SQLData, $delimiter = ';')
    {
        $SQLStatements = explode($delimiter, str_replace("\r\n", "\n", $SQLData));

        foreach ($SQLStatements as $SQL)
        {
            $SQL = trim($SQL);
            if ($SQL === '')
            {
                continue;
            }

            $this->mySQLQueryLocal($SQL);
        }
    }

    private function mySQLQueryLocal($query)
    {
        global $mySQLConnection;

        $result = mysqli_query($mySQLConnection, $query);
        if (!$result)
        {
            $this->fail('MySQL query failed: ' . mysqli_error($mySQLConnection) . "\n" . $query);
        }

        return $result;
    }

    private function lastInsertID()
    {
        global $mySQLConnection;

        return (int) mysqli_insert_id($mySQLConnection);
    }

    private function escape($value)
    {
        global $mySQLConnection;

        return mysqli_real_escape_string($mySQLConnection, $value);
    }
}
