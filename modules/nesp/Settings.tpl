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

            <?php if (trim($this->temporaryLoginMessage) !== ''): ?>
                <div class="nesp-confirm-box">
                    <?php $this->_($this->temporaryLoginMessage); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($this->oneTimeLoginDetails)): ?>
                <?php
                    $copyLoginDetails = "Login URL: " . $this->oneTimeLoginDetails['login_url'] . "\n"
                        . "Username: " . $this->oneTimeLoginDetails['username'] . "\n"
                        . "Temporary password: " . $this->oneTimeLoginDetails['temporary_password'];
                ?>
                <div class="nesp-confirm-box">
                    <strong>Copy-only login details.</strong>
                    The app has not sent this to anyone. This message is shown one time. Share manually only after Craig approves activation.
                    <label class="nesp-field-label" for="oneTimeLoginDetails">Login details</label>
                    <textarea id="oneTimeLoginDetails" rows="4" readonly><?php echo(htmlspecialchars($copyLoginDetails, ENT_QUOTES, 'UTF-8')); ?></textarea>
                    <p><button type="button" class="nesp-secondary-action" onclick="document.getElementById('oneTimeLoginDetails').select(); document.execCommand('copy');">Copy Login Details</button></p>
                </div>
            <?php endif; ?>

            <?php if (trim($this->googleCalendarMessage) !== ''): ?>
                <div class="nesp-confirm-box">
                    <?php $this->_($this->googleCalendarMessage); ?>
                </div>
            <?php endif; ?>

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

            <div class="nesp-card-grid nesp-card-grid-tight">
                <?php
                    $settingsStateCounts = array('Active' => 0, 'Prepared but not active' => 0, 'Suspended/deactivated' => 0);
                    foreach ($this->interviewerProfiles as $profile)
                    {
                        if (isset($settingsStateCounts[$profile['state_badge']]))
                        {
                            $settingsStateCounts[$profile['state_badge']]++;
                        }
                    }
                ?>
                <div class="nesp-card"><span class="nesp-card-label">Active</span><strong><?php echo((int) $settingsStateCounts['Active']); ?></strong></div>
                <div class="nesp-card"><span class="nesp-card-label">Prepared but not active</span><strong><?php echo((int) $settingsStateCounts['Prepared but not active']); ?></strong></div>
                <div class="nesp-card"><span class="nesp-card-label">Suspended/deactivated</span><strong><?php echo((int) $settingsStateCounts['Suspended/deactivated']); ?></strong></div>
                <div class="nesp-card"><span class="nesp-card-label">Active candidate grants</span><strong><?php echo((int) $this->summary['candidateGrants']); ?></strong></div>
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
                    <div class="nesp-safety-banner">
                        No email, invitation, password reset, Zoom meeting, Vapi call, or applicant message is sent from this screen.
                    </div>
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
                            Temporary password
                            <input type="password" name="temporaryPassword" autocomplete="new-password" />
                            <span class="nesp-help-text">Optional. If entered, an OpenCATS login is prepared but remains disabled until Craig activates the interviewer.</span>
                        </label>
                        <label>
                            Role
                            <select name="roleKey">
                                <option value="interviewer">Interviewer</option>
                                <option value="lead_interviewer">Lead interviewer</option>
                                <option value="field_trainer">Field trainer</option>
                            </select>
                        </label>
                        <div class="nesp-confirm-box">New profiles start inactive. Login activation is a separate audited action.</div>
                        <fieldset class="nesp-fieldset">
                            <legend>Approved job roles</legend>
                            <?php foreach ($this->jobRoleOptions as $roleOption): ?>
                                <label class="nesp-checkbox-row">
                                    <input type="checkbox" name="approvedJobOrderIDs[]" value="<?php echo((int) $roleOption['joborder_id']); ?>" />
                                    <?php $this->_($roleOption['label']); ?> - job <?php echo((int) $roleOption['joborder_id']); ?>
                                </label>
                            <?php endforeach; ?>
                        </fieldset>
                        <label>
                            Time zone
                            <input type="text" name="timezone" value="America/New_York" />
                        </label>
                        <label>
                            Max interviews per day
                            <input type="text" name="maxInterviewsPerDay" value="3" />
                        </label>
                        <label>
                            Max interviews per week
                            <input type="text" name="maxInterviewsPerWeek" value="12" />
                        </label>
                        <label>
                            Min notice minutes
                            <input type="text" name="minNoticeMinutes" value="1440" />
                        </label>
                        <label>
                            Default interview minutes
                            <input type="text" name="defaultInterviewMinutes" value="30" />
                        </label>
                        <label>
                            Default Zoom participant link
                            <input type="url" name="defaultZoomJoinURL" placeholder="https://*.zoom.us/j/..." />
                            <span class="nesp-help-text">Optional. Participant join link only; host/start URLs are rejected.</span>
                        </label>
                        <label>
                            Buffer minutes
                            <input type="text" name="bufferMinutes" value="15" />
                        </label>
                        <label>
                            Earliest interview time
                            <input type="text" name="earliestTime" value="09:00" />
                        </label>
                        <label>
                            Latest interview time
                            <input type="text" name="latestTime" value="17:00" />
                        </label>
                        <label class="nesp-checkbox-row">
                            <input type="checkbox" name="mayRecommend" checked="checked" />
                            May provide advisory recommendations
                        </label>
                        <label class="nesp-checkbox-row">
                            <input type="checkbox" name="craigMustAttend" />
                            Craig must attend interviews
                        </label>
                        <label>
                            Private admin notes
                            <textarea name="privateAdminNotes" rows="3"></textarea>
                        </label>
                        <label>
                            Email warning
                            <textarea name="emailWarning" rows="2"></textarea>
                        </label>
                        <button type="submit" class="nesp-secondary-button">Create Inactive Profile</button>
                    </form>
                </div>
            </div>

            <div class="nesp-panel">
                <h3>Approved Real Interviewer Setup</h3>
                <table class="nesp-table">
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Approved Roles</th>
                        <th>Account State</th>
                        <th>Warning</th>
                    </tr>
                    <?php foreach ($this->seedProfiles as $seedProfile): ?>
                    <tr>
                        <td><?php $this->_($seedProfile['display_name']); ?></td>
                        <td><?php $this->_($seedProfile['email']); ?></td>
                        <td><?php $this->_(implode(', ', $seedProfile['approved_joborder_ids'])); ?></td>
                        <td><?php $this->_($seedProfile['account_state_key']); ?></td>
                        <td><?php $this->_($seedProfile['email_warning']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
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
                <h3>Google Calendar Free/Busy</h3>
                <div class="nesp-safety-banner">
                    Free/busy is disabled by default, uses only <?php $this->_($this->googleCalendarConfiguration['minimum_scope']); ?>, stores encrypted tokens only, and never reads event titles, creates events, sends invitations, or writes to calendars.
                </div>
                <div class="nesp-card-grid nesp-card-grid-tight">
                    <div class="nesp-card"><span class="nesp-card-label">Feature Enabled</span><strong><?php echo($this->googleCalendarConfiguration['feature_enabled'] ? 'Yes' : 'No'); ?></strong></div>
                    <div class="nesp-card"><span class="nesp-card-label">OAuth Client Configured</span><strong><?php echo($this->googleCalendarConfiguration['client_configured'] ? 'Yes' : 'No'); ?></strong></div>
                    <div class="nesp-card"><span class="nesp-card-label">Redirect URI Configured</span><strong><?php echo($this->googleCalendarConfiguration['redirect_uri_configured'] ? 'Yes' : 'No'); ?></strong></div>
                    <div class="nesp-card"><span class="nesp-card-label">Token Encryption Configured</span><strong><?php echo($this->googleCalendarConfiguration['token_encryption_configured'] ? 'Yes' : 'No'); ?></strong></div>
                    <div class="nesp-card"><span class="nesp-card-label">Calendar Event Creation</span><strong>Disabled</strong></div>
                    <div class="nesp-card"><span class="nesp-card-label">Connection State</span><strong><?php $this->_($this->googleCalendarConfiguration['status_key']); ?></strong></div>
                </div>

                <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=googleCalendarConnect" class="nesp-form">
                    <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                    <label>
                        Interviewer
                        <select name="interviewerProfileID">
                            <?php foreach ($this->interviewerProfiles as $profile): ?>
                                <option value="<?php echo((int) $profile['interviewer_profile_id']); ?>"><?php $this->_($profile['display_name']); ?> - <?php $this->_($profile['email']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button type="submit" class="nesp-secondary-button">Prepare Free/Busy OAuth</button>
                    <span class="nesp-help-text">This prepares a consent URL for approved testing only. Token exchange remains an encrypted-storage integration step and is not run from this page.</span>
                </form>

                <table class="nesp-table">
                    <tr>
                        <th>Interviewer</th>
                        <th>Status</th>
                        <th>Scope</th>
                        <th>Calendar</th>
                        <th>Last Error</th>
                        <th>Action</th>
                    </tr>
                    <?php foreach ($this->googleCalendarConnections as $connection): ?>
                    <tr>
                        <td><?php $this->_($connection['display_name']); ?><br /><span class="nesp-muted"><?php $this->_($connection['email']); ?></span></td>
                        <td><?php $this->_($connection['status_key']); ?></td>
                        <td><?php $this->_($connection['token_scope']); ?></td>
                        <td><?php $this->_($connection['calendar_id_hash'] === '' ? 'primary calendar hash pending' : $connection['calendar_id_hash']); ?></td>
                        <td><?php $this->_($connection['last_error']); ?></td>
                        <td>
                            <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=googleCalendarDisconnect">
                                <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                                <input type="hidden" name="interviewerProfileID" value="<?php echo((int) $connection['interviewer_profile_id']); ?>" />
                                <button type="submit" class="nesp-secondary-button">Disconnect</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($this->googleCalendarConnections)): ?>
                    <tr>
                        <td colspan="6">No Google Calendar free/busy connections have been prepared.</td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>

            <div class="nesp-panel">
                <h3>Interviewer Profiles</h3>
                <table class="nesp-table">
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Username</th>
                        <th>Account</th>
                        <th>Approved Jobs</th>
                        <th>Status</th>
                        <th>Active Grants</th>
                        <th>Capacity</th>
                        <th>Zoom Link</th>
                        <th>Last Login</th>
                        <th>Last Change</th>
                        <th>Action</th>
                    </tr>
                    <?php foreach ($this->interviewerProfiles as $profile): ?>
                    <tr>
                        <td data-label="Name"><?php $this->_($profile['display_name']); ?></td>
                        <td data-label="Email"><?php $this->_($profile['email']); ?></td>
                        <td data-label="Username"><?php $this->_(empty($profile['username']) ? 'No login prepared' : $profile['username']); ?></td>
                        <td data-label="Account">
                            <?php $this->_(isset($this->accountStates[$profile['account_state_key']]) ? $this->accountStates[$profile['account_state_key']] : $profile['account_state_key']); ?><br />
                            <span class="nesp-muted"><?php $this->_($profile['role_key']); ?></span>
                            <?php if (trim($profile['email_warning']) !== ''): ?>
                                <br /><strong><?php $this->_($profile['email_warning']); ?></strong>
                            <?php endif; ?>
                        </td>
                        <td data-label="Approved Jobs"><?php $this->_($profile['approved_joborder_ids']); ?></td>
                        <td data-label="Status"><span class="nesp-status-pill"><?php $this->_($profile['state_badge']); ?></span></td>
                        <td data-label="Active Grants"><?php $this->_($profile['active_grants']); ?></td>
                        <td data-label="Capacity"><?php $this->_((int) $profile['max_interviews_per_day'] . '/day, ' . (int) $profile['max_interviews_per_week'] . '/week'); ?></td>
                        <td data-label="Zoom Link"><?php $this->_($profile['default_zoom_join_url'] === '' ? 'None' : NESPWorkflow::maskZoomURLForAudit($profile['default_zoom_join_url'])); ?></td>
                        <td data-label="Last Login"><?php $this->_(empty($profile['last_login_display']) ? 'Never' : $profile['last_login_display']); ?></td>
                        <td data-label="Last Change"><?php $this->_($profile['date_modified']); ?></td>
                        <td data-label="Action"><a class="nesp-secondary-action" href="#interviewer-<?php echo((int) $profile['interviewer_profile_id']); ?>">Edit</a></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!count($this->interviewerProfiles)): ?>
                    <tr>
                        <td colspan="12">No interviewer profiles have been created.</td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>

            <?php foreach ($this->interviewerProfiles as $profile): ?>
                <?php
                    $approvedJobs = array_filter(explode(',', $profile['approved_joborder_ids']));
                ?>
                <div class="nesp-panel" id="interviewer-<?php echo((int) $profile['interviewer_profile_id']); ?>">
                    <h3>Edit Interviewer: <?php $this->_($profile['display_name']); ?></h3>
                    <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=updateInterviewerSettings" class="nesp-form nesp-form-wide">
                        <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                        <input type="hidden" name="interviewerProfileID" value="<?php echo((int) $profile['interviewer_profile_id']); ?>" />
                        <label>
                            Full name
                            <input type="text" name="displayName" value="<?php echo(htmlspecialchars($profile['display_name'], ENT_QUOTES, 'UTF-8')); ?>" />
                        </label>
                        <label>
                            Email address
                            <input type="text" name="email" value="<?php echo(htmlspecialchars($profile['email'], ENT_QUOTES, 'UTF-8')); ?>" />
                        </label>
                        <div class="nesp-confirm-box">
                            Account state: <?php $this->_($profile['state_badge']); ?>.
                            Linked username: <?php $this->_(empty($profile['username']) ? 'none' : $profile['username']); ?>.
                            Login access changes use the audited buttons below.
                        </div>
                        <fieldset class="nesp-fieldset">
                            <legend>Approved job roles</legend>
                            <?php foreach ($this->jobRoleOptions as $roleOption): ?>
                                <label class="nesp-checkbox-row">
                                    <input type="checkbox" name="approvedJobOrderIDs[]" value="<?php echo((int) $roleOption['joborder_id']); ?>"<?php if (in_array((string) $roleOption['joborder_id'], $approvedJobs)): ?> checked="checked"<?php endif; ?> />
                                    <?php $this->_($roleOption['label']); ?> - job <?php echo((int) $roleOption['joborder_id']); ?>
                                </label>
                            <?php endforeach; ?>
                        </fieldset>
                        <label>
                            Availability status
                            <select name="availabilityStatusKey">
                                <option value="open"<?php if ($profile['availability_status_key'] !== 'closed'): ?> selected="selected"<?php endif; ?>>Open for Interviews</option>
                                <option value="closed"<?php if ($profile['availability_status_key'] === 'closed'): ?> selected="selected"<?php endif; ?>>Closed for Interviews</option>
                            </select>
                        </label>
                        <label>
                            Reopen date/time
                            <input type="text" name="availabilityClosedUntil" value="<?php echo(htmlspecialchars($profile['availability_closed_until'], ENT_QUOTES, 'UTF-8')); ?>" />
                        </label>
                        <label>
                            Close reason
                            <textarea name="availabilityCloseReason" rows="2"><?php echo(htmlspecialchars($profile['availability_close_reason'], ENT_QUOTES, 'UTF-8')); ?></textarea>
                        </label>
                        <label>
                            Time zone
                            <input type="text" name="timezone" value="<?php echo(htmlspecialchars($profile['timezone'], ENT_QUOTES, 'UTF-8')); ?>" />
                        </label>
                        <label>
                            Max interviews/day
                            <input type="text" name="maxInterviewsPerDay" value="<?php echo((int) $profile['max_interviews_per_day']); ?>" />
                        </label>
                        <label>
                            Max interviews/week
                            <input type="text" name="maxInterviewsPerWeek" value="<?php echo((int) $profile['max_interviews_per_week']); ?>" />
                        </label>
                        <label>
                            Min notice minutes
                            <input type="text" name="minNoticeMinutes" value="<?php echo((int) $profile['min_notice_minutes']); ?>" />
                        </label>
                        <label>
                            Default duration
                            <input type="text" name="defaultInterviewMinutes" value="<?php echo((int) $profile['default_interview_minutes']); ?>" />
                        </label>
                        <label>
                            Default Zoom participant link
                            <input type="url" name="defaultZoomJoinURL" value="<?php echo(htmlspecialchars($profile['default_zoom_join_url'], ENT_QUOTES, 'UTF-8')); ?>" placeholder="https://*.zoom.us/j/..." />
                            <span class="nesp-help-text">Optional. Used as a scheduling default only when NESP_INTERVIEWER_ZOOM_LINKS_ENABLED is enabled. Participant join link only; host/start URLs are rejected.</span>
                        </label>
                        <label>
                            Buffer minutes
                            <input type="text" name="bufferMinutes" value="<?php echo((int) $profile['buffer_minutes']); ?>" />
                        </label>
                        <label>
                            Earliest interview
                            <input type="text" name="earliestTime" value="<?php echo(htmlspecialchars(substr($profile['earliest_time'], 0, 5), ENT_QUOTES, 'UTF-8')); ?>" />
                        </label>
                        <label>
                            Latest interview
                            <input type="text" name="latestTime" value="<?php echo(htmlspecialchars(substr($profile['latest_time'], 0, 5), ENT_QUOTES, 'UTF-8')); ?>" />
                        </label>
                        <label class="nesp-checkbox-row">
                            <input type="checkbox" name="craigMustAttend"<?php if ((int) $profile['craig_must_attend'] === 1): ?> checked="checked"<?php endif; ?> />
                            Craig must attend
                        </label>
                        <label class="nesp-checkbox-row">
                            <input type="checkbox" name="mayRecommend"<?php if ((int) $profile['may_recommend'] === 1): ?> checked="checked"<?php endif; ?> />
                            May provide advisory recommendation
                        </label>
                        <label>
                            Private admin notes
                            <textarea name="privateAdminNotes" rows="3"><?php echo(htmlspecialchars($profile['private_admin_notes'], ENT_QUOTES, 'UTF-8')); ?></textarea>
                        </label>
                        <label>
                            Email confirmation warning
                            <textarea name="emailWarning" rows="2"><?php echo(htmlspecialchars($profile['email_warning'], ENT_QUOTES, 'UTF-8')); ?></textarea>
                        </label>
                        <div class="nesp-confirm-box">Review these changes before saving. Saving is immediate, audited, and never sends email automatically.</div>
                        <button type="submit" class="nesp-primary-button">Save Interviewer Settings</button>
                    </form>

                    <div class="nesp-action-row">
                        <?php if (!empty($profile['can_prepare_login'])): ?>
                            <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=prepareInterviewerLogin">
                                <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                                <input type="hidden" name="interviewerProfileID" value="<?php echo((int) $profile['interviewer_profile_id']); ?>" />
                                <input type="password" name="temporaryPassword" autocomplete="new-password" placeholder="Optional temp password" />
                                <button type="submit" class="nesp-secondary-button">Prepare Login</button>
                            </form>
                        <?php endif; ?>
                        <?php if (!empty($profile['can_activate_login'])): ?>
                            <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=activateInterviewerLogin">
                                <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                                <input type="hidden" name="interviewerProfileID" value="<?php echo((int) $profile['interviewer_profile_id']); ?>" />
                                <button type="submit" class="nesp-primary-button">Activate Login</button>
                            </form>
                        <?php endif; ?>
                        <?php if (!empty($profile['can_suspend_login'])): ?>
                            <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=suspendInterviewerLogin">
                                <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                                <input type="hidden" name="interviewerProfileID" value="<?php echo((int) $profile['interviewer_profile_id']); ?>" />
                                <button type="submit" class="nesp-secondary-button">Suspend</button>
                            </form>
                        <?php endif; ?>
                        <?php if (!empty($profile['can_reactivate_login'])): ?>
                            <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=reactivateInterviewerLogin">
                                <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                                <input type="hidden" name="interviewerProfileID" value="<?php echo((int) $profile['interviewer_profile_id']); ?>" />
                                <button type="submit" class="nesp-primary-button">Reactivate</button>
                            </form>
                        <?php endif; ?>
                        <?php if (!empty($profile['can_reset_temp_password'])): ?>
                            <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=resetInterviewerTempPassword">
                                <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                                <input type="hidden" name="interviewerProfileID" value="<?php echo((int) $profile['interviewer_profile_id']); ?>" />
                                <input type="password" name="temporaryPassword" autocomplete="new-password" placeholder="Optional temp password" />
                                <button type="submit" class="nesp-secondary-button">Reset Temp Password</button>
                            </form>
                        <?php endif; ?>
                        <?php if (!empty($profile['can_disable_login'])): ?>
                            <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=disableInterviewerLogin">
                                <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                                <input type="hidden" name="interviewerProfileID" value="<?php echo((int) $profile['interviewer_profile_id']); ?>" />
                                <button type="submit" class="nesp-secondary-button">Permanently Disable</button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($profile['can_archive'])): ?>
                    <div class="nesp-confirm-box">
                        This profile has no linked login, active role, routing rule, grant, interview, calendar connection, or assignment. Archiving keeps its audit history but hides it from normal settings.
                        <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=archiveInertInterviewerProfile" class="nesp-action-row">
                            <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                            <input type="hidden" name="interviewerProfileID" value="<?php echo((int) $profile['interviewer_profile_id']); ?>" />
                            <input type="text" name="archiveConfirmation" autocomplete="off" placeholder="Type ARCHIVE" />
                            <button type="submit" class="nesp-secondary-button">Archive Inert Duplicate Profile</button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

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

                    <h4>Active Candidate Grants</h4>
                    <table class="nesp-table">
                        <tr>
                            <th>Interviewer</th>
                            <th>Candidate</th>
                            <th>Role</th>
                            <th>Granted</th>
                            <th>Action</th>
                        </tr>
                        <?php foreach ($this->candidateGrants as $grant): ?>
                        <tr>
                            <td data-label="Interviewer"><?php $this->_($grant['interviewer_name']); ?><br /><span class="nesp-muted"><?php $this->_($grant['interviewer_email']); ?></span></td>
                            <td data-label="Candidate"><?php $this->_($grant['candidate_name']); ?><br /><span class="nesp-muted"><?php $this->_($grant['candidate_email']); ?></span></td>
                            <td data-label="Role"><?php $this->_($grant['role_title']); ?></td>
                            <td data-label="Granted"><?php $this->_($grant['date_granted']); ?></td>
                            <td data-label="Action">
                                <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=revokeCandidateGrant">
                                    <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                                    <input type="hidden" name="grantID" value="<?php echo((int) $grant['grant_id']); ?>" />
                                    <button type="submit" class="nesp-secondary-button">Revoke Candidate Access</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($this->candidateGrants)): ?>
                        <tr>
                            <td data-label="Grants" colspan="5">No active candidate grants.</td>
                        </tr>
                        <?php endif; ?>
                    </table>
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
                                <th>Action</th>
                            </tr>
                            <?php foreach ($this->assignmentRules as $rule): ?>
                            <tr>
                                <td><?php $this->_($rule['interviewer_name']); ?></td>
                                <td><?php $this->_($rule['role_match_text']); ?></td>
                                <td><?php $this->_($rule['job_title']); ?></td>
                                <td><?php $this->_($rule['assignment_mode']); ?></td>
                                <td><?php $this->_($rule['priority']); ?></td>
                                <td>
                                    <?php if ((int) $rule['is_active'] === 1): ?>
                                    <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=deactivateInterviewerRoleRule">
                                        <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                                        <input type="hidden" name="roleRuleID" value="<?php echo((int) $rule['role_rule_id']); ?>" />
                                        <button type="submit" class="nesp-secondary-button">Remove Rule</button>
                                    </form>
                                    <?php else: ?>
                                    <span class="nesp-muted">Removed</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (!count($this->assignmentRules)): ?>
                            <tr>
                                <td colspan="6">No routing rules have been created.</td>
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

            <div class="nesp-two-column">
                <div class="nesp-panel">
                    <h3>Date-Specific Override</h3>
                    <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=createInterviewerAvailabilityOverride" class="nesp-form">
                        <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                        <label>
                            Interviewer
                            <select name="interviewerProfileID">
                                <option value="">Choose interviewer</option>
                                <?php foreach ($this->interviewerProfiles as $profile): ?>
                                    <option value="<?php echo((int) $profile['interviewer_profile_id']); ?>"><?php $this->_($profile['display_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            Date
                            <input type="text" name="overrideDate" placeholder="YYYY-MM-DD" />
                        </label>
                        <label>
                            Type
                            <select name="overrideTypeKey">
                                <option value="available">Available custom hours</option>
                                <option value="available_all_day">Available all day</option>
                                <option value="unavailable_all_day">Unavailable all day</option>
                            </select>
                        </label>
                        <label>
                            Open
                            <input type="text" name="startTime" value="09:00" />
                        </label>
                        <label>
                            Close
                            <input type="text" name="endTime" value="17:00" />
                        </label>
                        <input type="hidden" name="timezone" value="America/New_York" />
                        <label>
                            Private reason
                            <textarea name="privateReason" rows="2"></textarea>
                        </label>
                        <button type="submit" class="nesp-secondary-button">Save Override</button>
                    </form>
                </div>

                <div class="nesp-panel">
                    <h3>Blackout Date</h3>
                    <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=createInterviewerBlackout" class="nesp-form">
                        <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                        <label>
                            Interviewer
                            <select name="interviewerProfileID">
                                <option value="">Choose interviewer</option>
                                <?php foreach ($this->interviewerProfiles as $profile): ?>
                                    <option value="<?php echo((int) $profile['interviewer_profile_id']); ?>"><?php $this->_($profile['display_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            Starts
                            <input type="text" name="startsAt" placeholder="YYYY-MM-DD HH:MM" />
                        </label>
                        <label>
                            Ends
                            <input type="text" name="endsAt" placeholder="YYYY-MM-DD HH:MM" />
                        </label>
                        <label class="nesp-checkbox-row">
                            <input type="checkbox" name="isAllDay" />
                            All day
                        </label>
                        <input type="hidden" name="timezone" value="America/New_York" />
                        <label>
                            Private reason
                            <textarea name="privateReason" rows="2"></textarea>
                        </label>
                        <button type="submit" class="nesp-secondary-button">Save Blackout</button>
                    </form>
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
