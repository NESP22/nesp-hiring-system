<?php TemplateUtility::printHeader('Copy-Only Message', array('modules/nesp/nespWorkflow.css')); ?>
<?php TemplateUtility::printHeaderBlock(); ?>
<?php TemplateUtility::printTabs($this->active, $this->subActive); ?>
    <div id="main" class="nesp-workflow">
        <?php TemplateUtility::printQuickSearch(); ?>
        <div id="contents">
            <div class="nesp-page-title">
                <h2><?php $this->_($this->message['label']); ?></h2>
                <p>Copied - nothing was sent.</p>
            </div>

            <div class="nesp-safety-banner">
                This screen generated text only. It did not send email, SMS, account credentials, applicant communication, Vapi calls, or Zoom invitations.
            </div>

            <div class="nesp-panel">
                <label class="nesp-copy-box-label">
                    Reviewable text
                    <textarea class="nesp-copy-box" rows="12" readonly="readonly"><?php echo(htmlspecialchars($this->message['body'], ENT_QUOTES, 'UTF-8')); ?></textarea>
                </label>
                <a class="nesp-secondary-action" href="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=settings">Back to Settings</a>
            </div>
        </div>
    </div>
<?php TemplateUtility::printFooter(); ?>
