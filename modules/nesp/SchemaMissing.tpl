<?php TemplateUtility::printHeader('NESP Hiring', array('modules/nesp/nespWorkflow.css')); ?>
<?php TemplateUtility::printHeaderBlock(); ?>
<?php TemplateUtility::printTabs($this->active, $this->subActive); ?>
    <div id="main" class="nesp-workflow">
        <?php TemplateUtility::printQuickSearch(); ?>
        <div id="contents">
            <div class="nesp-page-title">
                <h2>NESP Hiring Dashboard</h2>
                <p>The NESP workflow database migration has not been applied yet.</p>
            </div>
            <div class="nesp-safety-banner">
                The workflow is unavailable until migration 394 is installed. No integrations have been enabled.
            </div>
        </div>
    </div>
<?php TemplateUtility::printFooter(); ?>
