<?php TemplateUtility::printHeader('NESP Settings', array('modules/nesp/nespWorkflow.css')); ?>
<?php TemplateUtility::printHeaderBlock(); ?>
<?php TemplateUtility::printTabs($this->active, $this->subActive); ?>
    <div id="main" class="nesp-workflow">
        <?php TemplateUtility::printQuickSearch(); ?>
        <div id="contents">
            <div class="nesp-page-title">
                <h2>NESP Settings</h2>
                <p>Feature flags, scoped interviewer setup, and audit-reviewed controls. All Phase 2 production flags default off.</p>
            </div>

            <div class="nesp-safety-banner">
                Turning on a flag here changes only database feature state. It does not deploy, run migrations, create Zoom meetings, initiate Vapi calls, send messages, or run AI review.
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
                        $isActive = $this->viewKey === $navItem['key'];
                    ?>
                    <a class="<?php echo($isActive ? 'active' : ''); ?>" href="<?php echo($navURL); ?>"><?php $this->_($navItem['label']); ?></a>
                <?php endforeach; ?>
            </div>

            <div class="nesp-two-column">
                <div class="nesp-panel">
                    <h3>Feature Flags</h3>
                    <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=saveFeatureFlags">
                        <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                        <table class="nesp-table">
                            <tr>
                                <th>Enabled</th>
                                <th>Feature</th>
                                <th>Purpose</th>
                            </tr>
                            <?php foreach ($this->featureFlags as $flag): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="featureFlags[]" value="<?php $this->_($flag['flag_key']); ?>"<?php if ((int) $flag['is_enabled'] === 1): ?> checked="checked"<?php endif; ?> />
                                </td>
                                <td>
                                    <strong><?php $this->_($flag['display_name']); ?></strong><br />
                                    <span class="nesp-muted"><?php $this->_($flag['flag_key']); ?></span>
                                </td>
                                <td><?php $this->_($flag['description']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                        <button type="submit" class="nesp-primary-button">Save Feature Flags</button>
                    </form>
                </div>

                <div class="nesp-panel">
                    <h3>Interviewer Pool</h3>
                    <div class="nesp-card-grid nesp-card-grid-tight">
                        <div class="nesp-card">
                            <span class="nesp-card-label">Active Interviewers</span>
                            <strong><?php $this->_($this->summary['activeInterviewers']); ?></strong>
                        </div>
                        <div class="nesp-card">
                            <span class="nesp-card-label">Candidate Grants</span>
                            <strong><?php $this->_($this->summary['candidateGrants']); ?></strong>
                        </div>
                        <div class="nesp-card">
                            <span class="nesp-card-label">Routing Rules</span>
                            <strong><?php $this->_($this->summary['assignmentRules']); ?></strong>
                        </div>
                        <div class="nesp-card">
                            <span class="nesp-card-label">Open Slots</span>
                            <strong><?php $this->_($this->summary['openInterviewSlots']); ?></strong>
                        </div>
                    </div>

                    <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=createInterviewer" class="nesp-form">
                        <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                        <label>
                            Display name
                            <input type="text" name="displayName" />
                        </label>
                        <label>
                            Email
                            <input type="text" name="email" />
                        </label>
                        <label>
                            Role
                            <select name="roleKey">
                                <option value="interviewer">Interviewer</option>
                                <option value="lead_interviewer">Lead interviewer</option>
                                <option value="field_trainer">Field trainer</option>
                            </select>
                        </label>
                        <button type="submit" class="nesp-secondary-button">Create Inactive Profile</button>
                    </form>
                </div>
            </div>

            <div class="nesp-panel">
                <h3>Vapi Configuration Status</h3>
                <p class="nesp-help-text">This panel shows only presence and safety state. It never reveals secret values or full provider IDs.</p>
                <div class="nesp-card-grid nesp-card-grid-tight">
                    <div class="nesp-card"><span class="nesp-card-label">Vapi API Configured</span><strong><?php echo($this->vapiConfiguration['api_configured'] ? 'Yes' : 'No'); ?></strong></div>
                    <div class="nesp-card"><span class="nesp-card-label">Hiring Phone Configured</span><strong><?php echo($this->vapiConfiguration['hiring_phone_configured'] ? 'Yes' : 'No'); ?></strong></div>
                    <div class="nesp-card"><span class="nesp-card-label">Hiring Assistant Configured</span><strong><?php echo($this->vapiConfiguration['hiring_assistant_configured'] ? 'Yes' : 'No'); ?></strong></div>
                    <div class="nesp-card"><span class="nesp-card-label">Webhook Secret Configured</span><strong><?php echo($this->vapiConfiguration['webhook_secret_configured'] ? 'Yes' : 'No'); ?></strong></div>
                    <div class="nesp-card"><span class="nesp-card-label">Recording Disabled</span><strong><?php echo($this->vapiConfiguration['recording_disabled'] ? 'Yes' : 'No'); ?></strong></div>
                    <div class="nesp-card"><span class="nesp-card-label">Feature Enabled</span><strong><?php echo($this->vapiConfiguration['feature_enabled'] ? 'Yes' : 'No'); ?></strong></div>
                </div>
                <p class="nesp-help-text">Webhook URL after deployment: <?php $this->_($this->vapiConfiguration['webhook_url']); ?></p>
                <p><a class="nesp-secondary-action" href="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=phoneScreenAvailability">Edit Phone Screen Availability</a></p>
            </div>

            <div class="nesp-panel">
                <h3>Interviewer Profiles</h3>
                <table class="nesp-table">
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Active Grants</th>
                        <th>Last Change</th>
                    </tr>
                    <?php foreach ($this->interviewerProfiles as $profile): ?>
                    <tr>
                        <td><?php $this->_($profile['display_name']); ?></td>
                        <td><?php $this->_($profile['email']); ?></td>
                        <td><?php $this->_($profile['role_key']); ?></td>
                        <td><?php echo(((int) $profile['is_active'] === 1) ? 'Active' : 'Inactive'); ?></td>
                        <td><?php $this->_($profile['active_grants']); ?></td>
                        <td><?php $this->_($profile['date_modified']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!count($this->interviewerProfiles)): ?>
                    <tr>
                        <td colspan="6">No interviewer profiles have been created.</td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>

            <div class="nesp-two-column">
                <div class="nesp-panel">
                    <h3>Role Routing Rules</h3>
                    <p class="nesp-help-text">Rules only suggest an interviewer. Craig still approves any real candidate access grant.</p>
                    <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=createInterviewerRoleRule" class="nesp-form">
                        <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                        <label>
                            Interviewer
                            <select name="interviewerProfileID">
                                <option value="">Choose interviewer</option>
                                <?php foreach ($this->interviewerProfiles as $profile): ?>
                                    <option value="<?php echo((int) $profile['interviewer_profile_id']); ?>"><?php $this->_($profile['display_name']); ?><?php if ((int) $profile['is_active'] !== 1): ?> (inactive)<?php endif; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            Role match text
                            <input type="text" name="roleMatchText" placeholder="freelance photographer" />
                        </label>
                        <label>
                            Exact job ID, optional
                            <input type="text" name="jobOrderID" />
                        </label>
                        <label>
                            Mode
                            <select name="assignmentMode">
                                <option value="suggest_only">Suggest only</option>
                                <option value="manual_review">Manual review</option>
                            </select>
                        </label>
                        <label>
                            Priority
                            <input type="text" name="priority" value="50" />
                        </label>
                        <label>
                            Notes
                            <textarea name="notes" rows="3"></textarea>
                        </label>
                        <button type="submit" class="nesp-secondary-button">Add Routing Rule</button>
                    </form>

                    <?php if (count($this->assignmentRuleExamples)): ?>
                        <div class="nesp-reference-list">
                            <strong>Useful starting rules</strong>
                            <ul>
                                <?php foreach ($this->assignmentRuleExamples as $example): ?>
                                <li><?php $this->_($example['role_match_text']); ?> - <?php $this->_($example['assignment_mode']); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="nesp-panel">
                    <h3>Manual Candidate Assignment</h3>
                    <p class="nesp-help-text">Creates an explicit interviewer grant. Use only after Craig approves real interviewer access for that person.</p>
                    <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=createCandidateGrant" class="nesp-form">
                        <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                        <label>
                            Interviewer
                            <select name="interviewerProfileID">
                                <option value="">Choose interviewer</option>
                                <?php foreach ($this->interviewerProfiles as $profile): ?>
                                    <option value="<?php echo((int) $profile['interviewer_profile_id']); ?>"><?php $this->_($profile['display_name']); ?><?php if ((int) $profile['is_active'] !== 1): ?> (inactive)<?php endif; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            Candidate ID
                            <input type="text" name="candidateID" />
                        </label>
                        <label>
                            Job ID
                            <input type="text" name="jobOrderID" />
                        </label>
                        <button type="submit" class="nesp-secondary-button">Grant Assignment</button>
                    </form>
                </div>
            </div>

            <div class="nesp-two-column">
                <div class="nesp-panel">
                    <h3>Availability Blocks</h3>
                    <p class="nesp-help-text">Availability is internal scheduling prep. It does not email applicants or create Zoom meetings.</p>
                    <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=createInterviewerAvailability" class="nesp-form">
                        <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                        <label>
                            Interviewer
                            <select name="interviewerProfileID">
                                <option value="">Choose interviewer</option>
                                <?php foreach ($this->interviewerProfiles as $profile): ?>
                                    <option value="<?php echo((int) $profile['interviewer_profile_id']); ?>"><?php $this->_($profile['display_name']); ?><?php if ((int) $profile['is_active'] !== 1): ?> (inactive)<?php endif; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            Weekday
                            <select name="weekdayKey">
                                <?php foreach (array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') as $weekday): ?>
                                    <option value="<?php $this->_($weekday); ?>"><?php $this->_($weekday); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            Start time
                            <input type="text" name="startTime" value="09:00" />
                        </label>
                        <label>
                            End time
                            <input type="text" name="endTime" value="12:00" />
                        </label>
                        <label>
                            Timezone
                            <input type="text" name="timezone" value="<?php $this->_($this->availabilityTemplate['timezone']); ?>" />
                        </label>
                        <label>
                            Slot minutes
                            <input type="text" name="slotMinutes" value="<?php $this->_($this->availabilityTemplate['slot_minutes']); ?>" />
                        </label>
                        <label>
                            Buffer minutes
                            <input type="text" name="bufferMinutes" value="<?php $this->_($this->availabilityTemplate['buffer_minutes']); ?>" />
                        </label>
                        <label>
                            Notes
                            <textarea name="notes" rows="3"><?php $this->_($this->availabilityTemplate['notes']); ?></textarea>
                        </label>
                        <button type="submit" class="nesp-secondary-button">Add Availability</button>
                    </form>
                </div>

                <div class="nesp-panel">
                    <h3>Scheduling Safety</h3>
                    <ul class="nesp-list">
                        <li>Applicant self-booking is not exposed yet.</li>
                        <li>Zoom meeting creation stays disabled.</li>
                        <li>Email and SMS confirmations stay disabled.</li>
                        <li>Interview slots are internal planning records until Craig approves the next rollout.</li>
                    </ul>
                </div>
            </div>

            <div class="nesp-panel">
                <h3>Routing and Availability Review</h3>
                <div class="nesp-two-column">
                    <div>
                        <h4>Rules</h4>
                        <table class="nesp-table">
                            <tr>
                                <th>Interviewer</th>
                                <th>Match</th>
                                <th>Exact Job</th>
                                <th>Mode</th>
                                <th>Priority</th>
                            </tr>
                            <?php foreach ($this->assignmentRules as $rule): ?>
                            <tr>
                                <td><?php $this->_($rule['interviewer_name']); ?></td>
                                <td><?php $this->_($rule['role_match_text']); ?></td>
                                <td><?php $this->_($rule['job_title']); ?></td>
                                <td><?php $this->_($rule['assignment_mode']); ?></td>
                                <td><?php $this->_($rule['priority']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (!count($this->assignmentRules)): ?>
                            <tr>
                                <td colspan="5">No routing rules have been created.</td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                    <div>
                        <h4>Availability</h4>
                        <table class="nesp-table">
                            <tr>
                                <th>Interviewer</th>
                                <th>Day</th>
                                <th>Window</th>
                                <th>Slots</th>
                            </tr>
                            <?php foreach ($this->interviewerAvailability as $availability): ?>
                            <tr>
                                <td><?php $this->_($availability['interviewer_name']); ?></td>
                                <td><?php $this->_($availability['weekday_key']); ?></td>
                                <td><?php $this->_(substr($availability['start_time'], 0, 5) . ' - ' . substr($availability['end_time'], 0, 5)); ?></td>
                                <td><?php $this->_((int) $availability['slot_minutes'] . ' min'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (!count($this->interviewerAvailability)): ?>
                            <tr>
                                <td colspan="4">No availability blocks have been created.</td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>

            <div class="nesp-panel">
                <h3>Scorecards</h3>
                <table class="nesp-table">
                    <tr>
                        <th>Candidate</th>
                        <th>Role</th>
                        <th>Interviewer</th>
                        <th>Status</th>
                        <th>Recommendation</th>
                        <th>Submitted</th>
                        <th>Lock</th>
                        <th>Action</th>
                    </tr>
                    <?php foreach ($this->scorecards as $scorecard): ?>
                    <tr>
                        <td><a href="<?php echo(CATSUtility::getIndexName()); ?>?m=candidates&amp;a=show&amp;candidateID=<?php echo((int) $scorecard['candidate_id']); ?>"><?php $this->_($scorecard['candidate_name']); ?></a></td>
                        <td><?php $this->_($scorecard['role_title']); ?></td>
                        <td><?php $this->_($scorecard['interviewer_name']); ?></td>
                        <td><?php $this->_($scorecard['status_key']); ?></td>
                        <td><?php $this->_($scorecard['overall_recommendation']); ?></td>
                        <td><?php $this->_($scorecard['submitted_at']); ?></td>
                        <td>
                            <?php if ($scorecard['locked_at'] !== null && $scorecard['unlocked_at'] === null): ?>
                                Locked
                            <?php else: ?>
                                Open
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($scorecard['locked_at'] !== null && $scorecard['unlocked_at'] === null): ?>
                                <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=unlockScorecard" class="nesp-inline-form">
                                    <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                                    <input type="hidden" name="scorecardResponseID" value="<?php echo((int) $scorecard['scorecard_response_id']); ?>" />
                                    <input type="hidden" name="candidateID" value="<?php echo((int) $scorecard['candidate_id']); ?>" />
                                    <input type="hidden" name="jobOrderID" value="<?php echo((int) $scorecard['joborder_id']); ?>" />
                                    <input type="hidden" name="redirectTo" value="settings" />
                                    <button type="submit" class="nesp-secondary-button">Unlock</button>
                                </form>
                            <?php else: ?>
                                <span class="nesp-muted">No action</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!count($this->scorecards)): ?>
                    <tr>
                        <td colspan="8">No scorecards have been saved.</td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
<?php TemplateUtility::printFooter(); ?>
