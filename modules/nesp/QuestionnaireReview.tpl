<?php TemplateUtility::printHeader('Review Screening Questionnaire', array('modules/nesp/nespWorkflow.css')); ?>
<?php TemplateUtility::printHeaderBlock(); ?>
<?php TemplateUtility::printTabs($this->active, $this->subActive); ?>
<?php
$applicantEmailReady = isset($this->applicantEmailDelivery['status_key']) && $this->applicantEmailDelivery['status_key'] === 'enabled';
$questionnaireComplete = $this->questionnaire['status_key'] === 'completed';
$questionnaireWaiting = in_array($this->questionnaire['status_key'], array('waiting', 'in_progress'), true);
$emailStatus = isset($this->questionnaire['auto_email_status_key']) ? $this->questionnaire['auto_email_status_key'] : 'not_attempted';
$koalendarBookingEmailReady = isset($this->koalendarBookingEmailDelivery['status_key'])
    && $this->koalendarBookingEmailDelivery['status_key'] === 'enabled';
$koalendarBookingEmailStatus = isset($this->questionnaire['koalendar_booking_email_status_key'])
    ? $this->questionnaire['koalendar_booking_email_status_key'] : 'not_attempted';
?>
    <div id="main" class="nesp-workflow">
        <?php TemplateUtility::printQuickSearch(); ?>
        <div id="contents">
            <div class="nesp-page-title">
                <div class="nesp-brand-lockup">
                    <img src="images/nesp-logo.png" alt="New England Sports Photo" />
                    <div>
                        <span class="nesp-kicker">New England Sports Photo</span>
                        <h2>Review Screening Questionnaire</h2>
                        <p>Send and track one secure questionnaire email, with manual copy available as a fallback.</p>
                    </div>
                </div>
            </div>

            <div class="nesp-safety-banner">
                Reviewing a questionnaire does not change candidate stage or make an automated hiring decision.
            </div>

            <?php if ($this->questionnaireDeliveryMessage !== ''): ?>
                <div class="<?php echo($this->questionnaireDeliverySeverity === 'success' ? 'nesp-success' : 'nesp-safety-banner'); ?>" role="status">
                    <?php $this->_($this->questionnaireDeliveryMessage); ?>
                </div>
            <?php endif; ?>
            <?php if ($this->koalendarDeliveryMessage !== ''): ?>
                <div class="<?php echo($this->koalendarDeliveryOK ? 'nesp-success' : 'nesp-safety-banner'); ?>" role="status">
                    <?php $this->_($this->koalendarDeliveryMessage); ?>
                </div>
            <?php endif; ?>

            <div class="nesp-step-row" aria-label="Questionnaire review progress">
                <div class="nesp-step<?php echo(!$questionnaireWaiting && !$questionnaireComplete ? ' is-current' : ''); ?>"><span>1</span><strong>Prepare invite</strong></div>
                <div class="nesp-step<?php echo($questionnaireWaiting ? ' is-current' : ''); ?>"><span>2</span><strong>Waiting for applicant</strong></div>
                <div class="nesp-step<?php echo($questionnaireComplete ? ' is-current' : ''); ?>"><span>3</span><strong>Human review</strong></div>
            </div>

            <div class="nesp-panel">
                <h3>Summary</h3>
                <dl class="nesp-detail-list">
                    <dt>Candidate</dt>
                    <dd><?php $this->_($this->questionnaire['candidate_name']); ?></dd>
                    <dt>Email recipient</dt>
                    <dd><?php $this->_($this->questionnaire['email1']); ?></dd>
                    <dt>Role</dt>
                    <dd><?php $this->_($this->questionnaire['role_title']); ?></dd>
                    <dt>Status</dt>
                    <dd><?php $this->_($this->questionnaire['status_label']); ?></dd>
                    <dt>Question set</dt>
                    <dd><?php $this->_($this->questionnaire['question_set_label']); ?></dd>
                    <dt>Reviewer</dt>
                    <dd><?php $this->_($this->questionnaire['reviewer_name']); ?></dd>
                    <dt>Submitted</dt>
                    <dd><?php $this->_(empty($this->questionnaire['submitted_at']) ? 'Not submitted' : $this->questionnaire['submitted_at']); ?></dd>
                    <dt>Email delivery</dt>
                    <dd>
                        <?php
                        $emailLabels = array(
                            'not_attempted' => 'Not sent',
                            'sending' => 'Delivery status uncertain - do not resend',
                            'sent' => 'Sent',
                            'failed' => 'Failed - manual review required'
                        );
                        $this->_(isset($emailLabels[$emailStatus]) ? $emailLabels[$emailStatus] : $emailStatus);
                        ?>
                    </dd>
                </dl>
            </div>

            <?php if ($this->isAdmin): ?>
            <div class="nesp-panel">
                <h3>Questionnaire Invitation</h3>
                <?php if ($applicantEmailReady
                    && $this->questionnaire['status_key'] === 'link_ready'
                    && (!isset($this->questionnaire['auto_email_status_key']) || $this->questionnaire['auto_email_status_key'] === 'not_attempted')): ?>
                    <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=sendQuestionnaireEmail">
                        <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                        <input type="hidden" name="questionnaireID" value="<?php echo((int) $this->questionnaire['screening_questionnaire_id']); ?>" />
                        <input type="hidden" name="reviewedEmailFingerprint" value="<?php echo(htmlspecialchars($this->reviewedEmailFingerprint, ENT_QUOTES, 'UTF-8')); ?>" />
                        <label class="nesp-confirmation-check">
                            <input type="checkbox" name="confirmSend" value="confirm" required />
                            I reviewed this applicant and role. Send one questionnaire email now.
                        </label>
                        <button class="nesp-primary-action" type="submit">Send Questionnaire Email</button>
                    </form>
                    <p class="nesp-muted">Sends one email to the verified applicant address. Duplicate sends are blocked.</p>
                <?php elseif (!$applicantEmailReady): ?>
                    <div class="nesp-empty"><?php $this->_($this->applicantEmailDelivery['message']); ?> Use the copy fallback below.</div>
                <?php endif; ?>
                <?php if ($this->oneTimeInvitationCopy !== ''): ?>
                <label class="nesp-field-label" for="questionnaireInvitationCopy"><?php echo($emailStatus === 'sent' ? 'Sent invitation copy (for reference)' : 'One-time invitation text'); ?></label>
                <textarea id="questionnaireInvitationCopy" rows="5" readonly><?php $this->_($this->oneTimeInvitationCopy); ?></textarea>
                <p>
                    <button type="button" class="nesp-secondary-action" onclick="document.getElementById('questionnaireInvitationCopy').select(); document.execCommand('copy');">Copy Invitation</button>
                </p>
                <?php elseif ($emailStatus === 'sent'): ?>
                <div class="nesp-empty">The questionnaire email was sent. Do not regenerate its link.</div>
                <?php else: ?>
                <div class="nesp-empty">The secure invitation text is shown only immediately after link generation.</div>
                <?php endif; ?>
                <?php if ($this->oneTimeInvitationCopy !== '' && $emailStatus !== 'sent'): ?>
                <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=markQuestionnaireInvitationCopied">
                    <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                    <input type="hidden" name="questionnaireID" value="<?php echo((int) $this->questionnaire['screening_questionnaire_id']); ?>" />
                    <button class="nesp-secondary-action" type="submit">Mark Invitation Copied</button>
                </form>
                <?php endif; ?>
            </div>

            <div class="nesp-two-column">
                <div class="nesp-panel">
                    <h3>Link Controls</h3>
                    <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=revokeQuestionnaireLink">
                        <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                        <input type="hidden" name="questionnaireID" value="<?php echo((int) $this->questionnaire['screening_questionnaire_id']); ?>" />
                        <button class="nesp-secondary-action" type="submit">Revoke Link</button>
                    </form>
                    <?php if (!in_array($emailStatus, array('sending', 'sent'), true)): ?>
                    <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=regenerateQuestionnaireLink">
                        <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                        <input type="hidden" name="questionnaireID" value="<?php echo((int) $this->questionnaire['screening_questionnaire_id']); ?>" />
                        <button class="nesp-secondary-action" type="submit">Regenerate Link</button>
                    </form>
                    <?php else: ?>
                    <p class="nesp-muted">Regeneration is locked after an email attempt so the delivered link cannot be replaced accidentally.</p>
                    <?php endif; ?>
                </div>

                <div class="nesp-panel">
                    <h3>Assign Reviewer</h3>
                    <?php if (empty($this->eligibleReviewerProfiles)): ?>
                        <p class="nesp-muted">No active, open interviewer is approved for this role yet. Update Interviewer Settings before assigning this questionnaire.</p>
                    <?php else: ?>
                        <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=assignQuestionnaireReviewer">
                            <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                            <input type="hidden" name="questionnaireID" value="<?php echo((int) $this->questionnaire['screening_questionnaire_id']); ?>" />
                            <label for="interviewerProfileID">Interviewer</label>
                            <select id="interviewerProfileID" name="interviewerProfileID" required>
                                <option value="">Choose interviewer</option>
                                <?php foreach ($this->eligibleReviewerProfiles as $profile): ?>
                                    <option value="<?php echo((int) $profile['interviewer_profile_id']); ?>"><?php $this->_($profile['display_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="nesp-primary-action" type="submit">Assign</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="nesp-panel">
                <h3>Answers</h3>
                <?php if (count($this->questionnaire['answers'])): ?>
                    <table class="nesp-table">
                        <caption>Submitted questionnaire answers</caption>
                        <tr>
                            <th>Question</th>
                            <th>Answer</th>
                        </tr>
                        <?php foreach ($this->questionnaire['answers'] as $answer): ?>
                        <tr>
                            <td data-label="Question"><?php $this->_($answer['question_label']); ?></td>
                            <td data-label="Answer"><?php $this->_($answer['answer_text']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                <?php else: ?>
                    <div class="nesp-empty">No final questionnaire answers have been submitted yet.</div>
                <?php endif; ?>
            </div>

            <div class="nesp-panel">
                <h3>Review Notes</h3>
                <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=saveQuestionnaireReview">
                    <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                    <input type="hidden" name="questionnaireID" value="<?php echo((int) $this->questionnaire['screening_questionnaire_id']); ?>" />
                    <label class="nesp-field-label" for="questionnaireReviewNote">Private review notes</label>
                    <textarea id="questionnaireReviewNote" name="reviewNote" rows="5"><?php $this->_($this->questionnaire['review_notes']); ?></textarea>
                    <label><input type="checkbox" name="markComplete" value="1" /> Mark review complete</label>
                    <button class="nesp-primary-action" type="submit">Save Review</button>
                </form>
            </div>

            <?php if ($questionnaireComplete && $this->questionnaire['review_status_key'] === 'complete' && !empty($this->questionnaire['review_completed_at']) && !empty($this->questionnaire['booking_owner_grant_id'])): ?>
            <div class="nesp-panel">
                <h3>Reviewed Next Action</h3>
                <?php if (!empty($this->questionnaire['reviewer_koalendar_booking_url'])): ?>
                    <p>The questionnaire is reviewed and <?php $this->_($this->questionnaire['booking_interviewer_name']); ?> is assigned. The public Koalendar page below is tied to that interviewer only.</p>
                    <a class="nesp-primary-action" href="<?php echo(htmlspecialchars($this->questionnaire['reviewer_koalendar_booking_url'], ENT_QUOTES, 'UTF-8')); ?>" target="_blank" rel="noopener noreferrer">Open <?php $this->_($this->questionnaire['booking_interviewer_name']); ?>'s Booking Page</a>
                    <p class="nesp-muted">Opening this page does not email the applicant or create a booking.</p>
                    <?php if ($this->isAdmin && $koalendarBookingEmailReady && $koalendarBookingEmailStatus === 'not_attempted'): ?>
                        <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=sendKoalendarSchedulingLink">
                            <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                            <input type="hidden" name="questionnaireID" value="<?php echo((int) $this->questionnaire['screening_questionnaire_id']); ?>" />
                            <input type="hidden" name="reviewedEmailFingerprint" value="<?php echo(htmlspecialchars($this->reviewedEmailFingerprint, ENT_QUOTES, 'UTF-8')); ?>" />
                            <input type="hidden" name="reviewedBookingFingerprint" value="<?php echo(htmlspecialchars($this->reviewedBookingFingerprint, ENT_QUOTES, 'UTF-8')); ?>" />
                            <p class="nesp-muted">New completed reviews send this invite automatically. Use this only for a reviewed applicant who was completed before automatic invites were enabled.</p>
                            <button class="nesp-primary-action" type="submit">Send Interview Invite</button>
                        </form>
                    <?php elseif ($this->isAdmin && in_array($koalendarBookingEmailStatus, array('sent', 'failed'), true)): ?>
                        <div class="nesp-empty">
                            <?php echo($koalendarBookingEmailStatus === 'sent' ? 'Interview invite sent.' : 'A previous interview-invite email attempt failed.'); ?>
                            A resend requires a new explicit confirmation and is recorded in the audit trail.
                        </div>
                        <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=sendKoalendarSchedulingLink">
                            <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                            <input type="hidden" name="questionnaireID" value="<?php echo((int) $this->questionnaire['screening_questionnaire_id']); ?>" />
                            <input type="hidden" name="reviewedEmailFingerprint" value="<?php echo(htmlspecialchars($this->reviewedEmailFingerprint, ENT_QUOTES, 'UTF-8')); ?>" />
                            <input type="hidden" name="reviewedBookingFingerprint" value="<?php echo(htmlspecialchars($this->reviewedBookingFingerprint, ENT_QUOTES, 'UTF-8')); ?>" />
                            <input type="hidden" name="sendMode" value="resend" />
                            <label class="nesp-confirmation-check">
                                <input type="checkbox" name="confirmBookingSend" value="confirm" required />
                                I reviewed the applicant, assigned interviewer, and public Koalendar page again.
                            </label>
                            <label class="nesp-confirmation-check">
                                <input type="checkbox" name="confirmResend" value="resend" required />
                                I intend to send another interview invite and understand this is recorded.
                            </label>
                            <button class="nesp-secondary-action" type="submit">Resend Interview Invite</button>
                        </form>
                    <?php elseif ($this->isAdmin): ?>
                        <div class="nesp-empty"><?php $this->_($this->koalendarBookingEmailDelivery['message']); ?></div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="nesp-empty"><?php $this->_($this->questionnaire['booking_interviewer_name']); ?> is assigned, but no approved Koalendar booking page is saved yet. Add it in Interviewer Settings or My Availability.</div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
<?php TemplateUtility::printFooter(); ?>
