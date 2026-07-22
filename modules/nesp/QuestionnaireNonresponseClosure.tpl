<?php TemplateUtility::printHeader('Close Questionnaire Review', array('modules/nesp/nespWorkflow.css')); ?>
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
                        <h2>Close Review After No Response</h2>
                        <p>The four-day reminder grace period has ended.</p>
                    </div>
                </div>
            </div>

            <div class="nesp-safety-banner">
                This is a human decision. The system has not rejected or removed this applicant automatically, and this action sends no message.
            </div>

            <div class="nesp-panel">
                <h3>Applicant</h3>
                <dl class="nesp-detail-list">
                    <dt>Candidate</dt>
                    <dd><?php $this->_($this->questionnaire['candidate_name']); ?></dd>
                    <dt>Role</dt>
                    <dd><?php $this->_($this->questionnaire['role_title']); ?></dd>
                    <dt>Reminder sent</dt>
                    <dd><?php $this->_($this->questionnaire['reminder_sent_at']); ?> UTC</dd>
                </dl>
            </div>

            <div class="nesp-panel">
                <h3>Confirm Closure</h3>
                <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=closeQuestionnaireNonresponse">
                    <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                    <input type="hidden" name="questionnaireID" value="<?php echo((int) $this->questionnaire['screening_questionnaire_id']); ?>" />

                    <label class="nesp-field-label" for="nonresponseReason">Reason</label>
                    <textarea id="nonresponseReason" name="reason" rows="3" required>No questionnaire response after the initial invitation, four-day reminder, and four-day grace period.</textarea>

                    <label class="nesp-confirm-checkbox">
                        <input type="checkbox" name="confirmClose" value="confirm" required />
                        I reviewed this applicant and confirm removal from the active applicant pool.
                    </label>

                    <div>
                        <button class="nesp-primary-action" type="submit">Confirm Close Review</button>
                        <a class="nesp-secondary-action" href="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=questionnaires">Keep Active</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php TemplateUtility::printFooter(); ?>
