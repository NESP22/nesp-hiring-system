<?php TemplateUtility::printHeader('NESP Interviewer Access', array('modules/nesp/nespWorkflow.css')); ?>
<?php TemplateUtility::printHeaderBlock(); ?>
<?php TemplateUtility::printTabs($this->active, $this->subActive); ?>
    <div id="main" class="nesp-workflow">
        <?php TemplateUtility::printQuickSearch(); ?>
        <div id="contents">
            <div class="nesp-page-title">
                <h2>NESP Interviewer Access</h2>
                <p>Scoped interviewer permissions are enforced by profile state, approved job roles, explicit candidate grants, and server-side authorization.</p>
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
                <h3>Scoped Access Model</h3>
                <ul class="nesp-list">
                    <li>Interviewers will use individual OpenCATS accounts after scoped access is implemented and tested.</li>
                    <li>Candidate visibility is based on explicit candidate and job grants, not broad OpenCATS recruiter permissions.</li>
                    <li>Candidate grants require an active, open interviewer profile approved for that exact job.</li>
                    <li>Scorecards and notes are tied to assigned interviews and stored in MariaDB.</li>
                    <li>System administration, integration secrets, and feature flags remain administrator-only.</li>
                </ul>
            </div>
        </div>
    </div>
<?php TemplateUtility::printFooter(); ?>
