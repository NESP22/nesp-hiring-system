<?php TemplateUtility::printHeader('NESP Interviewer Access', array('modules/nesp/nespWorkflow.css')); ?>
<?php TemplateUtility::printHeaderBlock(); ?>
<?php TemplateUtility::printTabs($this->active, $this->subActive); ?>
    <div id="main" class="nesp-workflow">
        <?php TemplateUtility::printQuickSearch(); ?>
        <div id="contents">
            <div class="nesp-page-title">
                <h2>NESP Interviewer Access</h2>
                <p>Scoped interviewer permissions are available in the database foundation. No real interviewer accounts are created in Phase 1.</p>
            </div>

            <div class="nesp-card-grid">
                <div class="nesp-card">
                    <span class="nesp-card-label">Active Interviewer Profiles</span>
                    <strong><?php $this->_($this->summary['activeInterviewers']); ?></strong>
                </div>
                <div class="nesp-card">
                    <span class="nesp-card-label">Active Candidate Grants</span>
                    <strong><?php $this->_($this->summary['candidateGrants']); ?></strong>
                </div>
                <div class="nesp-card">
                    <span class="nesp-card-label">Scheduled Interviews</span>
                    <strong><?php $this->_($this->summary['scheduledInterviews']); ?></strong>
                </div>
                <div class="nesp-card">
                    <span class="nesp-card-label">Draft Scorecards</span>
                    <strong><?php $this->_($this->summary['pendingScorecards']); ?></strong>
                </div>
            </div>

            <div class="nesp-panel">
                <h3>Phase 1 Access Model</h3>
                <ul class="nesp-list">
                    <li>Interviewers will use individual OpenCATS accounts after scoped access is implemented and tested.</li>
                    <li>Candidate visibility is based on explicit candidate and job grants, not broad OpenCATS recruiter permissions.</li>
                    <li>Scorecards and notes are tied to assigned interviews and stored in MariaDB.</li>
                    <li>System administration, integration secrets, and feature flags remain administrator-only.</li>
                </ul>
            </div>
        </div>
    </div>
<?php TemplateUtility::printFooter(); ?>
