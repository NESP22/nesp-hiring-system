<?php TemplateUtility::printHeader('Invite to Screening Questionnaire', array('modules/nesp/nespWorkflow.css')); ?>
<?php TemplateUtility::printHeaderBlock(); ?>
<?php TemplateUtility::printTabs($this->active, $this->subActive); ?>
<?php $applicantEmailReady = isset($this->applicantEmailDelivery['status_key']) && $this->applicantEmailDelivery['status_key'] === 'enabled'; ?>
    <div id="main" class="nesp-workflow">
        <?php TemplateUtility::printQuickSearch(); ?>
        <div id="contents">
            <div class="nesp-page-title">
                <div class="nesp-brand-lockup">
                    <img src="images/nesp-logo.png" alt="New England Sports Photo" />
                    <div>
                        <span class="nesp-kicker">New England Sports Photo</span>
                        <h2>Invite to Screening Questionnaire</h2>
                        <p><?php echo($applicantEmailReady
                            ? 'Review the candidate and role, then send one secure questionnaire email.'
                            : 'Prepare a polished, secure applicant questionnaire invitation for manual delivery.'); ?></p>
                    </div>
                </div>
            </div>

            <div class="nesp-safety-banner">
                <?php if ($applicantEmailReady): ?>
                    Selecting Send Questionnaire Email sends one email to this applicant after human review. It does not text, call, rank, reject, approve, hire, schedule, or make an employment decision.
                <?php else: ?>
                    <?php $this->_($this->applicantEmailDelivery['message']); ?> You can still generate copy-only invitation text for manual delivery.
                <?php endif; ?>
            </div>

            <?php if (!empty($this->contactDetailsMessage)): ?>
            <div class="nesp-success" role="status"><?php $this->_($this->contactDetailsMessage); ?></div>
            <?php endif; ?>

            <div class="nesp-step-row" aria-label="Questionnaire invitation steps">
                <div class="nesp-step is-current"><span>1</span><strong>Review candidate</strong></div>
                <div class="nesp-step"><span>2</span><strong><?php echo($applicantEmailReady ? 'Send email' : 'Generate link'); ?></strong></div>
                <div class="nesp-step"><span>3</span><strong><?php echo($applicantEmailReady ? 'Track completion' : 'Copy invitation'); ?></strong></div>
            </div>

            <div class="nesp-panel">
                <h3>Candidate and Role</h3>
                <dl class="nesp-detail-list">
                    <dt>Candidate</dt>
                    <dd><?php $this->_($this->preview['candidate_name']); ?></dd>
                    <dt>Email recipient</dt>
                    <dd><?php $this->_($this->preview['email1']); ?></dd>
                    <dt>Role</dt>
                    <dd><?php $this->_($this->preview['title']); ?></dd>
                    <dt>Question set</dt>
                    <dd><?php $this->_($this->preview['question_set_label']); ?></dd>
                    <dt>Estimated time</dt>
                    <dd><strong>Approximately <?php $this->_($this->preview['estimated_minutes']); ?></strong></dd>
                </dl>
            </div>

            <form class="nesp-action-form" method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=requestQuestionnaire" aria-label="Generate secure questionnaire link">
                <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                <input type="hidden" name="candidateID" value="<?php echo((int) $this->preview['candidate_id']); ?>" />
                <input type="hidden" name="jobOrderID" value="<?php echo((int) $this->preview['joborder_id']); ?>" />
                <input type="hidden" name="reviewedEmailFingerprint" value="<?php echo(htmlspecialchars($this->reviewedEmailFingerprint, ENT_QUOTES, 'UTF-8')); ?>" />
                <?php if ($applicantEmailReady): ?>
                    <label class="nesp-confirmation-check">
                        <input type="checkbox" name="confirmSend" value="confirm" required />
                        I reviewed this applicant and role. Send one questionnaire email now.
                    </label>
                    <button class="nesp-primary-action" type="submit" name="deliveryMode" value="email">Send Questionnaire Email</button>
                    <button class="nesp-secondary-action" type="submit" name="deliveryMode" value="copy" formnovalidate>Generate Copy Instead</button>
                <?php else: ?>
                    <button class="nesp-primary-action" type="submit" name="deliveryMode" value="copy">Generate Secure Questionnaire Link</button>
                <?php endif; ?>
                <a class="nesp-secondary-action" href="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp">Cancel</a>
            </form>

            <details class="nesp-panel">
                <summary><strong>Review questionnaire questions</strong></summary>
                <?php if (!empty($this->preview['question_set_intro'])): ?>
                <p><?php $this->_($this->preview['question_set_intro']); ?></p>
                <?php endif; ?>
                <ol class="nesp-list">
                    <?php foreach ($this->preview['questions'] as $question): ?>
                    <li><?php $this->_($question['label']); ?><?php echo(!empty($question['required']) ? ' *' : ''); ?></li>
                    <?php endforeach; ?>
                </ol>
            </details>
        </div>
    </div>
<?php TemplateUtility::printFooter(); ?>
