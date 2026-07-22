<?php TemplateUtility::printHeader('Collect Applicant Contact Details', array('modules/nesp/nespWorkflow.css')); ?>
<?php TemplateUtility::printHeaderBlock(); ?>
<?php TemplateUtility::printTabs($this->active, $this->subActive); ?>
    <div id="main" class="nesp-workflow">
        <?php TemplateUtility::printQuickSearch(); ?>
        <div id="contents">
            <div class="nesp-page-title">
                <div class="nesp-brand-lockup">
                    <img src="images/nesp-logo.png" alt="New England Sports Photo" />
                    <div>
                        <span class="nesp-kicker">New England Sports Photo</span>
                        <h2>Collect Applicant Contact Details</h2>
                        <p>Add a verified applicant email, then continue directly to the correct questionnaire.</p>
                    </div>
                </div>
            </div>

            <div class="nesp-safety-banner">
                Saving updates the applicant record and audit history only. It does not send email, text, calls, questionnaires, calendar invitations, or make an employment decision.
            </div>

            <div class="nesp-step-row" aria-label="Applicant questionnaire preparation steps">
                <div class="nesp-step is-current"><span>1</span><strong>Add email</strong></div>
                <div class="nesp-step"><span>2</span><strong>Review questionnaire</strong></div>
                <div class="nesp-step"><span>3</span><strong>Generate link</strong></div>
            </div>

            <div class="nesp-panel">
                <h3>Applicant and Role</h3>
                <dl class="nesp-detail-list">
                    <dt>Applicant</dt>
                    <dd><?php $this->_($this->contact['candidate_name']); ?></dd>
                    <dt>Role</dt>
                    <dd><?php $this->_($this->contact['role_title']); ?></dd>
                    <dt>Source</dt>
                    <dd><?php $this->_(trim((string) $this->contact['source']) !== '' ? $this->contact['source'] : 'Not recorded'); ?></dd>
                </dl>
            </div>

            <form class="nesp-form" method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=saveContactDetails" aria-label="Save applicant email and continue to questionnaire">
                <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                <input type="hidden" name="workflowID" value="<?php echo((int) $this->contact['candidate_workflow_id']); ?>" />
                <input type="hidden" name="candidateID" value="<?php echo((int) $this->contact['candidate_id']); ?>" />
                <input type="hidden" name="jobOrderID" value="<?php echo((int) $this->contact['joborder_id']); ?>" />
                <label>
                    Applicant email
                    <input type="email" name="email" maxlength="128" autocomplete="email" required value="<?php echo(htmlspecialchars($this->contact['email1'], ENT_QUOTES, 'UTF-8')); ?>" />
                </label>
                <p class="nesp-muted">Use the email the applicant supplied on the job board. The system blocks an address already attached to another active candidate.</p>
                <div class="nesp-button-row">
                    <button class="nesp-primary-action" type="submit">Save Email and Continue to Questionnaire</button>
                    <a class="nesp-secondary-action" href="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp">Cancel</a>
                </div>
            </form>
        </div>
    </div>
<?php TemplateUtility::printFooter(); ?>
