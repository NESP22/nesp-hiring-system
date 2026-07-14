<?php TemplateUtility::printHeader('NESP Feature Disabled', array('modules/nesp/nespWorkflow.css')); ?>
<?php TemplateUtility::printHeaderBlock(); ?>
<?php TemplateUtility::printTabs($this->active, $this->subActive); ?>
    <div id="main" class="nesp-workflow">
        <?php TemplateUtility::printQuickSearch(); ?>
        <div id="contents">
            <div class="nesp-page-title">
                <h2>NESP Hiring Feature Disabled</h2>
                <p>This Phase 2 screen is installed but not active. Production-safe defaults keep the workflow hidden until Craig enables the matching feature flag.</p>
            </div>

            <div class="nesp-safety-banner">
                No applicant records were changed, no messages were sent, and no integrations were contacted.
            </div>

            <div class="nesp-panel">
                <h3>Required Feature Flag</h3>
                <p class="nesp-help-text"><?php $this->_($this->featureFlagKey); ?> is currently off.</p>
                <?php if ($this->getUserAccessLevel('settings.administration') >= ACCESS_LEVEL_SA): ?>
                    <a class="nesp-primary-action" href="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=settings">Review Settings</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php TemplateUtility::printFooter(); ?>
