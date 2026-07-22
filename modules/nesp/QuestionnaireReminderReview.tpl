<?php TemplateUtility::printHeader('Review Questionnaire Reminder Delivery', array('modules/nesp/nespWorkflow.css')); ?>
<?php TemplateUtility::printHeaderBlock(); ?>
<?php TemplateUtility::printTabs($this->active, $this->subActive); ?>
    <div id="main" class="nesp-workflow">
        <?php TemplateUtility::printQuickSearch(); ?>
        <div id="contents">
            <div class="nesp-page-title">
                <div class="nesp-brand-lockup">
                    <img src="images/nesp-logo.png" alt="New England Sports Photo" />
                    <div>
                        <span class="nesp-kicker">Human confirmation required</span>
                        <h2>Review Reminder Delivery</h2>
                        <p>The mail provider response was not saved conclusively.</p>
                    </div>
                </div>
            </div>

            <div class="nesp-safety-banner">
                The system will not send another reminder automatically or remove this applicant. Check the approved mail provider's delivery log before choosing an outcome. This action sends no message.
            </div>

            <div class="nesp-panel">
                <h3>Applicant</h3>
                <dl class="nesp-detail-list">
                    <dt>Candidate</dt>
                    <dd><?php $this->_($this->questionnaire['candidate_name']); ?></dd>
                    <dt>Role</dt>
                    <dd><?php $this->_($this->questionnaire['role_title']); ?></dd>
                    <dt>Reminder attempted</dt>
                    <dd><?php $this->_($this->questionnaire['reminder_attempted_at']); ?> UTC</dd>
                </dl>
            </div>

            <div class="nesp-panel">
                <h3>Record the Provider Result</h3>
                <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=resolveQuestionnaireReminderReview">
                    <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                    <input type="hidden" name="questionnaireID" value="<?php echo((int) $this->questionnaire['screening_questionnaire_id']); ?>" />

                    <label class="nesp-field-label" for="reminderDecision">Outcome</label>
                    <select id="reminderDecision" name="decision" required>
                        <option value="">Choose outcome</option>
                        <option value="confirm_sent">Provider log confirms the reminder was sent</option>
                        <option value="keep_active">Delivery cannot be confirmed; keep applicant active</option>
                    </select>

                    <label class="nesp-field-label" for="reminderReviewReason">Reason</label>
                    <textarea id="reminderReviewReason" name="reason" rows="3" required></textarea>

                    <label class="nesp-confirm-checkbox">
                        <input type="checkbox" name="confirmReview" value="confirm" required />
                        I checked the approved mail provider and confirm this review decision.
                    </label>

                    <div>
                        <button class="nesp-primary-action" type="submit">Save Delivery Review</button>
                        <a class="nesp-secondary-action" href="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=questionnaires">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php TemplateUtility::printFooter(); ?>
