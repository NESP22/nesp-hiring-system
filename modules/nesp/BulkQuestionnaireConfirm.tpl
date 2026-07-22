<?php TemplateUtility::printHeader('Confirm Questionnaire Emails', array('modules/nesp/nespWorkflow.css')); ?>
<?php TemplateUtility::printHeaderBlock(); ?>
<?php TemplateUtility::printTabs($this->active, $this->subActive); ?>
    <div id="main" class="nesp-workflow">
        <?php TemplateUtility::printQuickSearch(); ?>
        <div id="contents">
            <div class="nesp-page-title">
                <h2>Send All Ready Questionnaires</h2>
                <p>Review this exact list before sending one role-specific questionnaire to each applicant.</p>
            </div>

            <div class="nesp-safety-banner">
                Duplicate sends are blocked. Applicants with missing or changed emails, completed questionnaires, or previous delivery attempts are skipped.
            </div>

            <?php if ($this->preview['delivery']['status_key'] !== 'enabled'): ?>
                <div class="nesp-warning-box"><?php $this->_($this->preview['delivery']['message']); ?></div>
            <?php elseif ((int) $this->preview['ready_count'] === 0): ?>
                <div class="nesp-empty">No applicants are ready for questionnaire email.</div>
            <?php else: ?>
                <div class="nesp-panel">
                    <h3><?php echo((int) $this->preview['ready_count']); ?> Ready to Send</h3>
                    <table class="nesp-table">
                        <tr>
                            <th>Applicant</th>
                            <th>Role</th>
                            <th>Questionnaire</th>
                            <th>Email recipient</th>
                        </tr>
                        <?php foreach ($this->preview['ready'] as $row): ?>
                            <tr>
                                <td data-label="Applicant"><?php $this->_($row['candidate_name']); ?></td>
                                <td data-label="Role"><?php $this->_($row['role_title']); ?></td>
                                <td data-label="Questionnaire"><?php $this->_($row['question_set_label']); ?></td>
                                <td data-label="Email recipient"><?php $this->_($row['email']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>

                <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=sendBulkQuestionnaireEmails" class="nesp-panel">
                    <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                    <input type="hidden" name="bulkToken" value="<?php echo(htmlspecialchars($this->bulkToken, ENT_QUOTES, 'UTF-8')); ?>" />
                    <label class="nesp-confirm-checkbox">
                        <input type="checkbox" name="confirmSend" value="confirm" required />
                        I reviewed the applicants, roles, and recipients above. Send one questionnaire email to each.
                    </label>
                    <div class="nesp-button-row">
                        <button type="submit" class="nesp-primary-button">Send <?php echo((int) $this->preview['ready_count']); ?> Questionnaires</button>
                        <a class="nesp-secondary-action" href="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp">Cancel</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
<?php TemplateUtility::printFooter(); ?>
