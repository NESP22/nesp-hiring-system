<?php TemplateUtility::printHeader('Assigned Candidates', array('modules/nesp/nespWorkflow.css')); ?>
<?php TemplateUtility::printHeaderBlock(); ?>
<?php TemplateUtility::printTabs($this->active, $this->subActive); ?>
    <div id="main" class="nesp-workflow">
        <?php TemplateUtility::printQuickSearch(); ?>
        <div id="contents">
            <div class="nesp-page-title">
                <h2>My Assigned Candidates</h2>
                <p>Your interview work in one place. Open an assignment to review the candidate, conduct the interview, and complete the scorecard.</p>
            </div>

            <div class="nesp-dashboard-nav">
                <a class="active" href="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=assignedCandidates">My Next Actions</a>
                <a href="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=myAvailability">My Availability</a>
            </div>

            <div class="nesp-safety-banner nesp-interviewer-note">
                You only see candidates Craig assigned to you. Save a draft while you interview, then submit the scorecard when you are finished.
            </div>

            <?php if (count($this->assignedCandidates)): ?>
                <div class="nesp-task-grid">
                    <?php foreach ($this->assignedCandidates as $candidate): ?>
                    <div class="nesp-task-card">
                        <div class="nesp-task-topline">
                            <strong><?php $this->_($candidate['candidate_name']); ?></strong>
                            <span><?php $this->_($candidate['stage_name']); ?></span>
                        </div>
                        <div class="nesp-task-role"><?php $this->_($candidate['role_title']); ?></div>
                        <p><?php $this->_($candidate['summary']); ?></p>
                        <dl>
                            <dt>Interview</dt>
                            <dd><?php $this->_($candidate['scheduled_start'] ? date('M j, Y - g:i A', strtotime($candidate['scheduled_start'])) : 'Not scheduled'); ?></dd>
                            <dt>Scorecard</dt>
                            <dd><?php $this->_($candidate['scorecard_status_key'] ? $candidate['scorecard_status_key'] : 'Not started'); ?></dd>
                        </dl>
                        <a class="nesp-primary-action" href="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=assignedCandidate&amp;candidateID=<?php echo((int) $candidate['candidate_id']); ?>&amp;jobOrderID=<?php echo((int) $candidate['joborder_id']); ?>">Open interview</a>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="nesp-empty">No candidate assignments are available for your account.</div>
            <?php endif; ?>
        </div>
    </div>
<?php TemplateUtility::printFooter(); ?>
