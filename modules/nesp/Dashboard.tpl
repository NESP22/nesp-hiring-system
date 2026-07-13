<?php TemplateUtility::printHeader('NESP Hiring', array('modules/nesp/nespWorkflow.css')); ?>
<?php TemplateUtility::printHeaderBlock(); ?>
<?php TemplateUtility::printTabs($this->active, $this->subActive); ?>
    <div id="main" class="nesp-workflow">
        <?php TemplateUtility::printQuickSearch(); ?>
        <div id="contents">
            <div class="nesp-page-title">
                <h2>NESP Hiring Dashboard</h2>
                <p>Browser-based workflow foundation. All Phase 1 integrations are intentionally off.</p>
            </div>

            <div class="nesp-safety-banner">
                Human-reviewed only: no automatic rejection, ranking, hiring, applicant email, phone calls, Zoom meetings, or AI review is enabled.
            </div>

            <div class="nesp-card-grid">
                <div class="nesp-card">
                    <span class="nesp-card-label">Public Jobs</span>
                    <strong><?php $this->_($this->summary['publicJobs']); ?></strong>
                </div>
                <div class="nesp-card">
                    <span class="nesp-card-label">Active Candidates</span>
                    <strong><?php $this->_($this->summary['allCandidates']); ?></strong>
                </div>
                <div class="nesp-card">
                    <span class="nesp-card-label">Tracked in NESP Workflow</span>
                    <strong><?php $this->_($this->summary['workflowTrackedCandidates']); ?></strong>
                </div>
                <div class="nesp-card">
                    <span class="nesp-card-label">Needs Review</span>
                    <strong><?php $this->_($this->summary['needsReview']); ?></strong>
                </div>
                <div class="nesp-card">
                    <span class="nesp-card-label">Enabled Integrations</span>
                    <strong><?php $this->_($this->summary['integrationsEnabled']); ?></strong>
                </div>
                <div class="nesp-card">
                    <span class="nesp-card-label">Audit Events This Week</span>
                    <strong><?php $this->_($this->summary['recentAuditEvents']); ?></strong>
                </div>
            </div>

            <div class="nesp-two-column">
                <div class="nesp-panel">
                    <h3>Integration Status</h3>
                    <table class="nesp-table">
                        <tr>
                            <th>Integration</th>
                            <th>Status</th>
                            <th>Message</th>
                        </tr>
                        <?php foreach ($this->integrationStatuses as $status): ?>
                        <tr>
                            <td><?php $this->_($status['display_name']); ?></td>
                            <td><span class="nesp-status nesp-status-off"><?php $this->_($status['status_key']); ?></span></td>
                            <td><?php $this->_($status['message']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>

                <div class="nesp-panel">
                    <h3>Workflow Stages</h3>
                    <table class="nesp-table">
                        <tr>
                            <th>Stage</th>
                            <th>Use</th>
                        </tr>
                        <?php foreach ($this->workflowStages as $stage): ?>
                        <tr>
                            <td><?php $this->_($stage['display_name']); ?></td>
                            <td><?php $this->_($stage['description']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
<?php TemplateUtility::printFooter(); ?>
