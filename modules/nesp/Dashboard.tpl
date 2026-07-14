<?php TemplateUtility::printHeader('NESP Hiring Dashboard', array('modules/nesp/nespWorkflow.css')); ?>
<?php TemplateUtility::printHeaderBlock(); ?>
<?php TemplateUtility::printTabs($this->active, $this->subActive); ?>
    <div id="main" class="nesp-workflow">
        <?php TemplateUtility::printQuickSearch(); ?>
        <div id="contents">
            <div class="nesp-page-title">
                <h2>NESP Hiring Dashboard</h2>
                <p>Task-first hiring view for Craig, applicants, interviewers, and seasonal staffing. Production integrations stay disabled unless Craig turns them on later.</p>
            </div>

            <div class="nesp-safety-banner">
                Human-reviewed only: no automatic rejection, ranking, applicant email, phone calls, Zoom meetings, AI review, or external posting happens from this dashboard.
            </div>

            <div class="nesp-dashboard-nav">
                <?php foreach ($this->dashboardNavigation as $navItem): ?>
                    <?php if ($navItem['key'] === 'settings' && $this->getUserAccessLevel('settings.administration') < ACCESS_LEVEL_SA): ?>
                        <?php continue; ?>
                    <?php endif; ?>
                    <?php
                        $navURL = CATSUtility::getIndexName() . '?m=nesp';
                        if ($navItem['action'] !== 'dashboard')
                        {
                            $navURL .= '&amp;a=' . $navItem['action'];
                        }
                        $isActive = $this->viewKey === $navItem['key'] || ($this->viewKey === 'dashboard' && $navItem['key'] === 'needsCraig');
                    ?>
                    <a class="<?php echo($isActive ? 'active' : ''); ?>" href="<?php echo($navURL); ?>"><?php $this->_($navItem['label']); ?></a>
                <?php endforeach; ?>
            </div>

            <div class="nesp-card-grid nesp-card-grid-compact">
                <div class="nesp-card">
                    <span class="nesp-card-label">Needs Craig</span>
                    <strong><?php $this->_($this->queueCounts['needsCraig']); ?></strong>
                </div>
                <div class="nesp-card">
                    <span class="nesp-card-label">Waiting on Applicant</span>
                    <strong><?php $this->_($this->queueCounts['waitingApplicant']); ?></strong>
                </div>
                <div class="nesp-card">
                    <span class="nesp-card-label">Waiting on Interviewer</span>
                    <strong><?php $this->_($this->queueCounts['waitingInterviewer']); ?></strong>
                </div>
                <div class="nesp-card">
                    <span class="nesp-card-label">Interviews This Week</span>
                    <strong><?php $this->_($this->summary['interviewsThisWeek']); ?></strong>
                </div>
                <div class="nesp-card">
                    <span class="nesp-card-label">Overdue</span>
                    <strong><?php $this->_($this->summary['overdueItems']); ?></strong>
                </div>
                <div class="nesp-card">
                    <span class="nesp-card-label">Routing Rules</span>
                    <strong><?php $this->_($this->summary['assignmentRules']); ?></strong>
                </div>
                <div class="nesp-card">
                    <span class="nesp-card-label">Availability Blocks</span>
                    <strong><?php $this->_($this->summary['availabilityBlocks']); ?></strong>
                </div>
            </div>

            <?php if ($this->viewKey === 'dashboard' || $this->viewKey === 'interviews'): ?>
            <div class="nesp-two-column">
                <div class="nesp-panel">
                    <h3>Suggested Interviewer Routing</h3>
                    <?php if (count($this->assignmentSuggestions)): ?>
                        <table class="nesp-table">
                            <tr>
                                <th>Candidate</th>
                                <th>Role</th>
                                <th>Suggested Owner</th>
                                <th>Rule</th>
                                <th>Action</th>
                            </tr>
                            <?php foreach ($this->assignmentSuggestions as $suggestion): ?>
                            <tr>
                                <td><?php $this->_($suggestion['candidate_name']); ?></td>
                                <td><?php $this->_($suggestion['role_title']); ?></td>
                                <td><?php $this->_($suggestion['suggested_interviewer']); ?></td>
                                <td><?php $this->_($suggestion['assignment_rule']); ?></td>
                                <td><a class="nesp-secondary-action" href="<?php echo($suggestion['candidate_url']); ?>">Review</a></td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    <?php else: ?>
                        <div class="nesp-empty">No candidates are ready for interviewer routing yet.</div>
                    <?php endif; ?>
                </div>

                <div class="nesp-panel">
                    <h3>Interviewer Follow-Through</h3>
                    <?php if (count($this->interviewerAccountability)): ?>
                        <table class="nesp-table">
                            <tr>
                                <th>Interviewer</th>
                                <th>Assigned</th>
                                <th>Open</th>
                                <th>Scorecards</th>
                                <th>Overdue</th>
                                <th>Availability</th>
                            </tr>
                            <?php foreach ($this->interviewerAccountability as $row): ?>
                            <tr>
                                <td><?php $this->_($row['display_name']); ?></td>
                                <td><?php $this->_($row['active_grants']); ?></td>
                                <td><?php $this->_($row['open_interviews']); ?></td>
                                <td><?php $this->_($row['scorecards_due']); ?></td>
                                <td><strong><?php $this->_($row['overdue_items']); ?></strong></td>
                                <td><?php $this->_($row['availability_blocks']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    <?php else: ?>
                        <div class="nesp-empty">No interviewer profiles exist yet.</div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php
                $sections = array();
                if ($this->viewKey === 'waiting')
                {
                    $sections = array('waitingApplicant', 'waitingInterviewer');
                }
                else if ($this->viewKey === 'interviews')
                {
                    $sections = array('upcomingInterviews', 'waitingInterviewer');
                }
                else if ($this->viewKey === 'completed')
                {
                    $sections = array('recentlyCompleted');
                }
                else
                {
                    $sections = array('needsCraig', 'waitingApplicant', 'waitingInterviewer', 'upcomingInterviews', 'recentlyCompleted');
                }
            ?>

            <?php foreach ($sections as $sectionKey): ?>
                <div class="nesp-queue-section">
                    <h3><?php $this->_($this->queueDefinitions[$sectionKey]['title']); ?></h3>
                    <?php if ($sectionKey === 'upcomingInterviews'): ?>
                        <?php if (count($this->upcomingInterviews)): ?>
                            <table class="nesp-table nesp-interview-table">
                                <tr>
                                    <th>Candidate</th>
                                    <th>Role</th>
                                    <th>Interviewer</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Duration</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                                <?php foreach ($this->upcomingInterviews as $interview): ?>
                                <tr>
                                    <td><?php $this->_($interview['candidate_name']); ?></td>
                                    <td><?php $this->_($interview['role_title']); ?></td>
                                    <td><?php $this->_($interview['interviewer_name']); ?></td>
                                    <td><?php $this->_(date('M j, Y', strtotime($interview['scheduled_start']))); ?></td>
                                    <td><?php $this->_(date('g:i A', strtotime($interview['scheduled_start']))); ?></td>
                                    <td><?php $this->_((int) $interview['duration_minutes'] . ' min'); ?></td>
                                    <td><?php $this->_($interview['status_key']); ?></td>
                                    <td><a class="nesp-secondary-action" href="<?php echo(CATSUtility::getIndexName()); ?>?m=candidates&amp;a=show&amp;candidateID=<?php echo((int) $interview['candidate_id']); ?>">Open</a></td>
                                </tr>
                                <?php endforeach; ?>
                            </table>
                        <?php else: ?>
                            <div class="nesp-empty"><?php $this->_($this->queueDefinitions[$sectionKey]['empty']); ?></div>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if (count($this->queues[$sectionKey])): ?>
                            <div class="nesp-task-grid">
                                <?php foreach ($this->queues[$sectionKey] as $card): ?>
                                    <div class="nesp-task-card">
                                        <div class="nesp-task-topline">
                                            <strong><?php $this->_($card['candidate_name']); ?></strong>
                                            <span><?php $this->_($card['stage_name']); ?></span>
                                        </div>
                                        <div class="nesp-task-role"><?php $this->_($card['role_title']); ?></div>
                                        <p><?php $this->_($card['summary']); ?></p>
                                        <dl>
                                            <dt>Waiting on</dt>
                                            <dd><?php $this->_($card['waiting_on']); ?></dd>
                                            <dt>Last activity</dt>
                                            <dd><?php $this->_($card['last_activity']); ?></dd>
                                        </dl>
                                        <a class="nesp-primary-action" href="<?php echo($card['candidate_url']); ?>"><?php $this->_($card['next_action_label']); ?></a>
                                        <div class="nesp-secondary-actions">
                                            <a href="<?php echo($card['candidate_url']); ?>">Candidate</a>
                                            <a href="<?php echo($card['job_url']); ?>">Role</a>
                                            <a href="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=confirmPhoneScreen&amp;candidateID=<?php echo((int) $card['candidate_id']); ?>&amp;jobOrderID=<?php echo((int) $card['joborder_id']); ?>">Invite to Phone Screen</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="nesp-empty"><?php $this->_($this->queueDefinitions[$sectionKey]['empty']); ?></div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <div class="nesp-two-column">
                <div class="nesp-panel">
                    <h3>Feature Flag Safety</h3>
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
                    <h3>Workflow Coverage</h3>
                    <ul class="nesp-list">
                        <li><?php $this->_($this->summary['workflowTrackedCandidates']); ?> candidates tracked in NESP workflow.</li>
                        <li><?php $this->_($this->summary['recentAuditEvents']); ?> audit events recorded this week.</li>
                        <li><?php $this->_($this->summary['integrationsEnabled']); ?> integration flags enabled.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
<?php TemplateUtility::printFooter(); ?>
