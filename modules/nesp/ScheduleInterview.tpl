<?php TemplateUtility::printHeader('Schedule Interview', array('modules/nesp/nespWorkflow.css')); ?>
<?php TemplateUtility::printHeaderBlock(); ?>
<?php TemplateUtility::printTabs($this->active, $this->subActive); ?>
    <div id="main" class="nesp-workflow">
        <?php TemplateUtility::printQuickSearch(); ?>
        <div id="contents">
            <div class="nesp-page-title">
                <h2><?php echo(empty($this->interview) ? 'Schedule Interview' : 'Reschedule Interview'); ?></h2>
                <p>Manual Zoom tracking only. Create or update the meeting in Zoom yourself, then paste only the applicant join link here.</p>
            </div>

            <div class="nesp-safety-banner">
                This screen does not create Zoom meetings, send email, send calendar invites, or contact applicants automatically.
            </div>

            <div class="nesp-dashboard-nav">
                <?php foreach ($this->dashboardNavigation as $navItem): ?>
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
                    <h3>Candidate</h3>
                    <dl class="nesp-detail-list">
                        <dt>Name</dt>
                        <dd><?php $this->_($this->preview['candidate_name']); ?></dd>
                        <dt>Role</dt>
                        <dd><?php $this->_($this->preview['role_title']); ?></dd>
                        <dt>Current stage</dt>
                        <dd><?php $this->_(empty($this->preview['stage_name']) ? 'Not routed yet' : $this->preview['stage_name']); ?></dd>
                    </dl>
                    <?php if (count($this->preview['active_interviews'])): ?>
                        <div class="nesp-confirm-box">An active interview already exists. Update that interview instead of creating a duplicate.</div>
                    <?php endif; ?>
                </div>

                <div class="nesp-panel">
                    <h3>Manual Zoom Rule</h3>
                    <ul class="nesp-list">
                        <li>Create the Zoom meeting outside this app.</li>
                        <li>Paste only the applicant join link.</li>
                        <li>Never paste the host/start URL.</li>
                        <li>Review the invitation copy before sending anything.</li>
                    </ul>
                </div>
            </div>

            <?php
                $existing = empty($this->interview) ? array() : $this->interview;
                $startDate = !empty($existing['scheduled_start']) ? date('Y-m-d', strtotime($existing['scheduled_start'])) : '';
                $startTime = !empty($existing['scheduled_start']) ? date('H:i', strtotime($existing['scheduled_start'])) : '';
                $duration = (!empty($existing['scheduled_start']) && !empty($existing['scheduled_end'])) ? (int) ((strtotime($existing['scheduled_end']) - strtotime($existing['scheduled_start'])) / 60) : $this->preview['default_duration_minutes'];
            ?>
            <div class="nesp-panel">
                <h3>Interview Details</h3>
                <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=saveManualInterview" class="nesp-form nesp-form-wide">
                    <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                    <input type="hidden" name="candidateID" value="<?php echo((int) $this->preview['candidate_id']); ?>" />
                    <input type="hidden" name="jobOrderID" value="<?php echo((int) $this->preview['joborder_id']); ?>" />
                    <?php if (!empty($existing)): ?>
                        <input type="hidden" name="interviewID" value="<?php echo((int) $existing['interview_id']); ?>" />
                    <?php endif; ?>

                    <label>
                        Interviewer
                        <select name="interviewerProfileID" required>
                            <option value="">Choose interviewer</option>
                            <?php foreach ($this->preview['interviewer_profiles'] as $profile): ?>
                                <option value="<?php echo((int) $profile['interviewer_profile_id']); ?>" <?php if (!empty($existing) && (int) $existing['interviewer_profile_id'] === (int) $profile['interviewer_profile_id']): ?>selected<?php endif; ?>>
                                    <?php $this->_($profile['display_name']); ?><?php if ((int) $profile['is_active'] !== 1): ?> (inactive)<?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        Date
                        <input type="date" name="interviewDate" value="<?php echo(htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8')); ?>" required />
                    </label>

                    <label>
                        Start time
                        <input type="time" name="interviewTime" value="<?php echo(htmlspecialchars($startTime, ENT_QUOTES, 'UTF-8')); ?>" required />
                    </label>

                    <label>
                        Duration minutes
                        <input type="number" name="durationMinutes" value="<?php echo((int) $duration); ?>" min="5" max="240" required />
                    </label>

                    <label>
                        Timezone
                        <input type="text" name="timezone" value="<?php echo(htmlspecialchars(!empty($existing['timezone']) ? $existing['timezone'] : $this->preview['default_timezone'], ENT_QUOTES, 'UTF-8')); ?>" />
                    </label>

                    <label>
                        Applicant Zoom join URL
                        <input type="url" name="zoomJoinURL" value="<?php echo(htmlspecialchars(!empty($existing['manual_zoom_join_url']) ? $existing['manual_zoom_join_url'] : '', ENT_QUOTES, 'UTF-8')); ?>" placeholder="https://*.zoom.us/j/..." required />
                    </label>

                    <label>
                        Internal notes
                        <textarea name="internalNotes" rows="4"><?php echo(htmlspecialchars(!empty($existing['internal_notes']) ? $existing['internal_notes'] : '', ENT_QUOTES, 'UTF-8')); ?></textarea>
                    </label>

                    <div class="nesp-confirm-box">
                        Saving creates or updates the internal interview record and invitation preview only. It does not send the invitation.
                    </div>

                    <label class="nesp-checkbox-row">
                        <input type="checkbox" name="adminOverrideAvailability" value="1" />
                        Admin override availability conflicts
                    </label>

                    <label>
                        Override reason
                        <textarea name="availabilityOverrideReason" rows="3"></textarea>
                    </label>

                    <div class="nesp-button-row">
                        <button type="submit" class="nesp-primary-button"><?php echo(empty($existing) ? 'Create Interview Preview' : 'Save Reschedule Preview'); ?></button>
                        <a class="nesp-secondary-action" href="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=interviews">Back to Interviews</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php TemplateUtility::printFooter(); ?>
