<?php TemplateUtility::printHeader('Assigned Candidates', array('modules/nesp/nespWorkflow.css')); ?>
<?php TemplateUtility::printHeaderBlock(); ?>
<?php TemplateUtility::printTabs($this->active, $this->subActive); ?>
    <div id="main" class="nesp-workflow">
        <?php TemplateUtility::printQuickSearch(); ?>
        <div id="contents">
            <div class="nesp-page-title">
                <h2><?php $this->_(!empty($this->isAdminAssignmentOverview) ? 'All Interviewer Assignments' : 'My Assigned Candidates'); ?></h2>
                <p><?php $this->_(!empty($this->isAdminAssignmentOverview) ? 'Admin overview of every active interviewer assignment. Open a candidate record to manage the workflow.' : 'Your interview work in one place. Open an assignment to review the candidate, conduct the interview, and complete the scorecard.'); ?></p>
            </div>

            <div class="nesp-dashboard-nav">
                <a class="active" href="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=assignedCandidates">My Next Actions</a>
                <a href="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=myAvailability">My Availability</a>
            </div>

            <div class="nesp-safety-banner nesp-interviewer-note">
                <?php if (!empty($this->isAdminAssignmentOverview)): ?>
                    You are seeing every active assignment because you are an administrator. Interviewers continue to see only their own assignments.
                <?php else: ?>
                    You only see candidates Craig assigned to you. Save a draft while you interview, then submit the scorecard when you are finished.
                <?php endif; ?>
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
                        <?php if (!empty($this->isAdminAssignmentOverview)): ?>
                            <p><strong>Assigned interviewer:</strong> <?php $this->_($candidate['interviewer_name']); ?></p>
                        <?php endif; ?>
                        <p><?php $this->_($candidate['summary']); ?></p>
                        <dl>
                            <dt>Interview</dt>
                            <dd><?php $this->_($candidate['scheduled_start'] ? date('M j, Y - g:i A', strtotime($candidate['scheduled_start'])) : 'Not scheduled'); ?></dd>
                            <dt>Scorecard</dt>
                            <dd><?php $this->_($candidate['scorecard_status_key'] ? $candidate['scorecard_status_key'] : 'Not started'); ?></dd>
                        </dl>
                        <?php if (!empty($candidate['questionnaire_review_completed_at']) && !empty($candidate['koalendar_booking_url'])): ?>
                            <div class="nesp-success">
                                <strong>Booking handoff ready</strong>
                                <p>The questionnaire is reviewed. Use the assigned interviewer's approved Koalendar page.</p>
                                <a class="nesp-secondary-action" href="<?php echo(htmlspecialchars($candidate['koalendar_booking_url'], ENT_QUOTES, 'UTF-8')); ?>" target="_blank" rel="noopener noreferrer">Open Booking Page</a>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($this->isAdminAssignmentOverview)): ?>
                            <a class="nesp-primary-action" href="<?php echo(CATSUtility::getIndexName()); ?>?m=candidates&amp;a=show&amp;candidateID=<?php echo((int) $candidate['candidate_id']); ?>">Open candidate</a>
                        <?php else: ?>
                            <a class="nesp-primary-action" href="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=assignedCandidate&amp;candidateID=<?php echo((int) $candidate['candidate_id']); ?>&amp;jobOrderID=<?php echo((int) $candidate['joborder_id']); ?>">Open interview</a>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="nesp-empty">No candidate assignments are available for your account.</div>
            <?php endif; ?>
        </div>
    </div>
<?php TemplateUtility::printFooter(); ?>
