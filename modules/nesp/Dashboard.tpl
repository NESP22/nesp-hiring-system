<?php TemplateUtility::printHeader('NESP Hiring Dashboard', array('modules/nesp/nespWorkflow.css')); ?>
<?php TemplateUtility::printHeaderBlock(); ?>
<?php TemplateUtility::printTabs($this->active, $this->subActive); ?>
    <div id="main" class="nesp-workflow">
        <?php TemplateUtility::printQuickSearch(); ?>
        <div id="contents">
            <div class="nesp-page-title">
                <h2>Hiring Dashboard</h2>
                <p>Start here. The first four sections show what needs attention now, what is waiting, what is scheduled, and what was just finished.</p>
            </div>

            <div class="nesp-safety-banner">
                Human-reviewed only: no automatic rejection, ranking, applicant email, phone calls, Zoom meetings, AI review, or external posting happens from this dashboard.
            </div>

            <?php
                $attentionCards = array(
                    array('key' => 'needsCraig', 'label' => 'Needs Me Now', 'count' => $this->queueCounts['needsCraig'], 'hint' => 'Review these first', 'action' => 'dashboard'),
                    array('key' => 'waitingApplicant', 'label' => 'Waiting on Applicant', 'count' => $this->queueCounts['waitingApplicant'], 'hint' => 'No action unless overdue', 'action' => 'waiting'),
                    array('key' => 'upcomingInterviews', 'label' => 'Upcoming Interviews', 'count' => count($this->upcomingInterviews), 'hint' => 'Track, reschedule, or cancel', 'action' => 'interviews'),
                    array('key' => 'recentlyCompleted', 'label' => 'Recently Completed', 'count' => $this->queueCounts['recentlyCompleted'], 'hint' => 'Confirm finished items', 'action' => 'completed')
                );
            ?>
            <section class="nesp-operator-focus" aria-label="What needs attention now">
                <div class="nesp-focus-copy">
                    <span class="nesp-kicker">Operator view</span>
                    <h3>What needs my attention now?</h3>
                    <p>Work left to right. Each candidate card has one main next step; extra links are tucked under Details.</p>
                    <ol class="nesp-start-list" aria-label="Start here checklist">
                        <li>Open Needs Me Now.</li>
                        <li>Press the blue next-action button on the first card.</li>
                        <li>Use Details only when you need background.</li>
                    </ol>
                </div>
                <div class="nesp-attention-grid">
                    <?php foreach ($attentionCards as $attentionCard): ?>
                        <?php
                            $attentionURL = CATSUtility::getIndexName() . '?m=nesp';
                            if ($attentionCard['action'] !== 'dashboard')
                            {
                                $attentionURL .= '&amp;a=' . $attentionCard['action'];
                            }
                            $attentionActive = $this->viewKey === $attentionCard['action'] || ($this->viewKey === 'dashboard' && $attentionCard['key'] === 'needsCraig');
                        ?>
                        <a class="nesp-attention-card <?php echo($attentionActive ? 'active' : ''); ?>" href="<?php echo($attentionURL); ?>">
                            <span><?php $this->_($attentionCard['label']); ?></span>
                            <strong><?php $this->_($attentionCard['count']); ?></strong>
                            <em><?php $this->_($attentionCard['hint']); ?></em>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <div class="nesp-secondary-tools">
                <span>Other hiring tools</span>
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
            </div>

            <?php
                $sections = array();
                $sectionLabels = array(
                    'needsCraig' => 'Needs Me Now',
                    'waitingApplicant' => 'Waiting on Applicant',
                    'waitingInterviewer' => 'Waiting on Interviewer',
                    'upcomingInterviews' => 'Upcoming Interviews',
                    'recentlyCompleted' => 'Recently Completed'
                );
                $emptyActions = array(
                    'needsCraig' => array('label' => 'Check Waiting Items', 'url' => CATSUtility::getIndexName() . '?m=nesp&amp;a=waiting'),
                    'waitingApplicant' => array('label' => 'Review Questionnaire Queue', 'url' => CATSUtility::getIndexName() . '?m=nesp&amp;a=questionnaires'),
                    'waitingInterviewer' => array('label' => 'Open Interviewer Settings', 'url' => CATSUtility::getIndexName() . '?m=nesp&amp;a=settings'),
                    'upcomingInterviews' => array('label' => 'Open Interviews', 'url' => CATSUtility::getIndexName() . '?m=nesp&amp;a=interviews'),
                    'recentlyCompleted' => array('label' => 'Review Completed Items', 'url' => CATSUtility::getIndexName() . '?m=nesp&amp;a=completed')
                );
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
                    $sections = array('needsCraig', 'waitingApplicant', 'upcomingInterviews', 'recentlyCompleted');
                }
            ?>

            <?php foreach ($sections as $sectionKey): ?>
                <div class="nesp-queue-section">
                    <h3><?php $this->_($sectionLabels[$sectionKey]); ?></h3>
                    <?php if ($sectionKey === 'upcomingInterviews'): ?>
                        <?php if (count($this->upcomingInterviews)): ?>
                            <table class="nesp-table nesp-data-table nesp-interview-table">
                                <tr>
                                    <th>Candidate</th>
                                    <th>Role</th>
                                    <th>Interviewer</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Duration</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                                <?php foreach ($this->upcomingInterviews as $interview): ?>
                                <tr>
                                    <td data-label="Candidate"><?php $this->_($interview['candidate_name']); ?></td>
                                    <td data-label="Role"><?php $this->_($interview['role_title']); ?></td>
                                    <td data-label="Interviewer"><?php $this->_($interview['interviewer_name']); ?></td>
                                    <td data-label="Date"><?php $this->_(date('M j, Y', strtotime($interview['scheduled_start']))); ?></td>
                                    <td data-label="Time"><?php $this->_(date('g:i A', strtotime($interview['scheduled_start']))); ?></td>
                                    <td data-label="Duration"><?php $this->_((int) $interview['duration_minutes'] . ' min'); ?></td>
                                    <td data-label="Status"><?php $this->_($interview['status_label']); ?></td>
                                    <td data-label="Actions">
                                        <div class="nesp-button-row">
                                            <a class="nesp-primary-action nesp-primary-action-small" href="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=recordInterviewOutcome&amp;interviewID=<?php echo((int) $interview['interview_id']); ?>">Track Interview</a>
                                            <details class="nesp-secondary-actions nesp-inline-details">
                                                <summary>Details</summary>
                                                <a href="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=scheduleInterview&amp;interviewID=<?php echo((int) $interview['interview_id']); ?>">Reschedule</a>
                                                <a href="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=cancelInterview&amp;interviewID=<?php echo((int) $interview['interview_id']); ?>">Cancel</a>
                                            </details>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </table>
                        <?php else: ?>
                            <div class="nesp-empty nesp-empty-action">
                                <strong><?php $this->_($this->queueDefinitions[$sectionKey]['empty']); ?></strong>
                                <a class="nesp-secondary-button" href="<?php echo($emptyActions[$sectionKey]['url']); ?>"><?php $this->_($emptyActions[$sectionKey]['label']); ?></a>
                            </div>
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
                                        <dl class="nesp-card-meta">
                                            <dt>Owner</dt>
                                            <dd><?php $this->_(ucwords(str_replace('_', ' ', $card['waiting_on']))); ?></dd>
                                            <?php if (!empty($card['due_at'])): ?>
                                                <?php $dueTimestamp = strtotime($card['due_at']); ?>
                                                <dt><?php echo($dueTimestamp !== false && $dueTimestamp < time() ? 'Overdue since' : 'Due'); ?></dt>
                                                <dd><?php $this->_($dueTimestamp === false ? $card['due_at'] : date('M j, g:i A', $dueTimestamp)); ?></dd>
                                            <?php else: ?>
                                                <?php $activityTimestamp = strtotime($card['last_activity']); ?>
                                                <dt>Waiting since</dt>
                                                <dd><?php $this->_($activityTimestamp === false ? $card['last_activity'] : date('M j, g:i A', $activityTimestamp)); ?></dd>
                                            <?php endif; ?>
                                        </dl>
                                        <?php if (!empty($card['scheduled_start'])): ?>
                                            <div class="nesp-task-next">
                                                Interview: <?php $this->_(date('M j, g:i A', strtotime($card['scheduled_start']))); ?>
                                                <?php if (!empty($card['interview_status_label'])): ?> · <?php $this->_($card['interview_status_label']); ?><?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        <a class="nesp-primary-action" href="<?php echo($card['primary_action_url']); ?>"><?php $this->_($card['next_action_label']); ?></a>
                                        <details class="nesp-secondary-actions">
                                            <summary>Details</summary>
                                            <a href="<?php echo($card['candidate_url']); ?>">Candidate</a>
                                            <a href="<?php echo($card['job_url']); ?>">Role</a>
                                            <a href="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=confirmQuestionnaire&amp;candidateID=<?php echo((int) $card['candidate_id']); ?>&amp;jobOrderID=<?php echo((int) $card['joborder_id']); ?>">Questionnaire</a>
                                            <a href="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=confirmPhoneScreen&amp;candidateID=<?php echo((int) $card['candidate_id']); ?>&amp;jobOrderID=<?php echo((int) $card['joborder_id']); ?>">Phone Screen</a>
                                            <a href="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=scheduleInterview&amp;candidateID=<?php echo((int) $card['candidate_id']); ?>&amp;jobOrderID=<?php echo((int) $card['joborder_id']); ?>">Schedule Interview</a>
                                            <?php if (!empty($card['interview_id'])): ?>
                                                <a href="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=recordInterviewOutcome&amp;interviewID=<?php echo((int) $card['interview_id']); ?>">Interview Tracking</a>
                                            <?php endif; ?>
                                        </details>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="nesp-empty nesp-empty-action">
                                <strong><?php $this->_($this->queueDefinitions[$sectionKey]['empty']); ?></strong>
                                <a class="nesp-secondary-button" href="<?php echo($emptyActions[$sectionKey]['url']); ?>"><?php $this->_($emptyActions[$sectionKey]['label']); ?></a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <?php if ($this->viewKey === 'dashboard' || $this->viewKey === 'interviews'): ?>
            <div class="nesp-secondary-workspace">
                <h3>Secondary Review</h3>
                <div class="nesp-two-column">
                    <div class="nesp-panel">
                        <h4>Suggested Interviewer Routing</h4>
                        <?php if (count($this->assignmentSuggestions)): ?>
                            <table class="nesp-table nesp-data-table">
                                <tr>
                                    <th>Candidate</th>
                                    <th>Role</th>
                                    <th>Suggested Owner</th>
                                    <th>Rule</th>
                                    <th>Action</th>
                                </tr>
                                <?php foreach ($this->assignmentSuggestions as $suggestion): ?>
                                <tr>
                                    <td data-label="Candidate"><?php $this->_($suggestion['candidate_name']); ?></td>
                                    <td data-label="Role"><?php $this->_($suggestion['role_title']); ?></td>
                                    <td data-label="Suggested Owner"><?php $this->_($suggestion['suggested_interviewer']); ?></td>
                                    <td data-label="Rule"><?php $this->_($suggestion['assignment_rule']); ?></td>
                                    <td data-label="Action"><a class="nesp-secondary-action" href="<?php echo($suggestion['candidate_url']); ?>">Review</a></td>
                                </tr>
                                <?php endforeach; ?>
                            </table>
                        <?php else: ?>
                            <div class="nesp-empty">No candidates are ready for interviewer routing yet.</div>
                        <?php endif; ?>
                    </div>

                    <div class="nesp-panel">
                        <h4>Interviewer Follow-Through</h4>
                        <?php if (count($this->interviewerAccountability)): ?>
                            <table class="nesp-table nesp-data-table">
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
                                    <td data-label="Interviewer"><?php $this->_($row['display_name']); ?></td>
                                    <td data-label="Assigned"><?php $this->_($row['active_grants']); ?></td>
                                    <td data-label="Open"><?php $this->_($row['open_interviews']); ?></td>
                                    <td data-label="Scorecards"><?php $this->_($row['scorecards_due']); ?></td>
                                    <td data-label="Overdue"><strong><?php $this->_($row['overdue_items']); ?></strong></td>
                                    <td data-label="Availability"><?php $this->_($row['availability_blocks']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </table>
                        <?php else: ?>
                            <div class="nesp-empty">No interviewer profiles exist yet.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="nesp-two-column">
                <div class="nesp-panel">
                    <h3>Feature Flag Safety</h3>
                    <table class="nesp-table nesp-data-table">
                        <tr>
                            <th>Integration</th>
                            <th>Status</th>
                            <th>Message</th>
                        </tr>
                        <?php foreach ($this->integrationStatuses as $status): ?>
                        <tr>
                            <td data-label="Integration"><?php $this->_($status['display_name']); ?></td>
                            <td data-label="Status"><span class="nesp-status nesp-status-off"><?php $this->_($status['status_key']); ?></span></td>
                            <td data-label="Message"><?php $this->_($status['message']); ?></td>
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
