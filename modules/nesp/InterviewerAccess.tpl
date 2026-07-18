<?php TemplateUtility::printHeader('NESP Interviewer Access', array('modules/nesp/nespWorkflow.css')); ?>
<?php TemplateUtility::printHeaderBlock(); ?>
<?php TemplateUtility::printTabs($this->active, $this->subActive); ?>
    <div id="main" class="nesp-workflow">
        <?php TemplateUtility::printQuickSearch(); ?>
        <div id="contents">
            <div class="nesp-page-title">
                <h2>Interviewer access</h2>
                <p>Use this quick check to confirm who can interview, what they can see, and what still needs attention.</p>
            </div>

            <div class="nesp-card-grid nesp-card-grid-compact">
                <div class="nesp-card">
                    <span class="nesp-card-label">Active interviewers</span>
                    <strong><?php $this->_($this->summary['activeInterviewers']); ?></strong>
                </div>
                <div class="nesp-card">
                    <span class="nesp-card-label">Candidate grants</span>
                    <strong><?php $this->_($this->summary['candidateGrants']); ?></strong>
                </div>
                <div class="nesp-card">
                    <span class="nesp-card-label">Scheduled interviews</span>
                    <strong><?php $this->_($this->summary['scheduledInterviews']); ?></strong>
                </div>
                <div class="nesp-card">
                    <span class="nesp-card-label">Scorecards to review</span>
                    <strong><?php $this->_($this->summary['pendingScorecards']); ?></strong>
                </div>
            </div>

            <div class="nesp-two-column">
                <div class="nesp-panel">
                    <h3>Access checklist</h3>
                    <ul class="nesp-list">
                        <li>Interviewer account is active.</li>
                        <li>Interviewer is approved for the exact job role.</li>
                        <li>Candidate access is granted explicitly.</li>
                        <li>Assignments and scorecards stay tied to that interviewer.</li>
                    </ul>
                </div>

                <div class="nesp-panel">
                    <h3>Admin-only controls</h3>
                    <ul class="nesp-list">
                        <li>Account setup and access changes.</li>
                        <li>Candidate and job grants.</li>
                        <li>System settings and integration secrets.</li>
                        <li>Audit history and override decisions.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
<?php TemplateUtility::printFooter(); ?>
