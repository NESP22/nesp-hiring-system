<?php TemplateUtility::printHeader('Set Up Interviewer Availability', array('modules/nesp/nespWorkflow.css')); ?>
<?php TemplateUtility::printHeaderBlock(); ?>
<?php TemplateUtility::printTabs($this->active, $this->subActive); ?>
    <div id="main" class="nesp-workflow">
        <?php TemplateUtility::printQuickSearch(); ?>
        <div id="contents">
            <div class="nesp-page-title">
                <h2>Set Up Your Interviewer Access</h2>
                <p>Your administrator account remains separate from interviewer access. This protects hiring data and keeps each interviewer limited to their own assignments.</p>
            </div>

            <div class="nesp-panel">
                <?php if ($this->canManageInterviewerProfiles): ?>
                    <h3>One Step Needed</h3>
                    <p>Create or activate a dedicated interviewer profile for yourself, then sign in with that interviewer account to set availability and interview candidates.</p>
                    <a class="nesp-primary-action" href="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=settings">Open Interviewer Settings</a>
                <?php else: ?>
                    <h3>Your Interviewer Account Is Not Ready</h3>
                    <p>Ask an administrator to create and activate an interviewer profile linked to your account. Once it is active, return here to set your availability.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php TemplateUtility::printFooter(); ?>
