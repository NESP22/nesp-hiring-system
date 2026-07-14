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
                <a class="<?php echo($this->viewKey === 'dashboard' ? 'active' : ''); ?>" href="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp">Needs Craig</a>
                <a class="<?php echo($this->viewKey === 'waiting' ? 'active' : ''); ?>" href="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=waiting">Waiting</a>
                <a class="<?php echo($this->viewKey === 'interviews' ? 'active' : ''); ?>" href="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=interviews">Interviews</a>
                <a class="<?php echo($this->viewKey === 'completed' ? 'active' : ''); ?>" href="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=completed">Completed</a>
                <a href="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=staffingForecast">Staffing Forecast</a>
                <?php if ($this->getUserAccessLevel('settings.administration') >= ACCESS_LEVEL_SA): ?>
                    <a href="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=settings">Settings</a>
                <?php endif; ?>
            </div>

            <div class="nesp-card-grid nesp-card-grid-compact">
                <div class="nesp-card">
                    <span class="nesp-card-label">Needs Craig</span>
                    <strong><?php $this->_(count($this->queues['needsCraig'])); ?></strong>
                </div>
                <div class="nesp-card">
                    <span class="nesp-card-label">Waiting on Applicant</span>
                    <strong><?php $this->_(count($this->queues['waitingApplicant'])); ?></strong>
                </div>
                <div class="nesp-card">
                    <span class="nesp-card-label">Waiting on Interviewer</span>
                    <strong><?php $this->_(count($this->queues['waitingInterviewer'])); ?></strong>
                </div>
                <div class="nesp-card">
                    <span class="nesp-card-label">Interviews This Week</span>
                    <strong><?php $this->_($this->summary['interviewsThisWeek']); ?></strong>
                </div>
            </div>

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
