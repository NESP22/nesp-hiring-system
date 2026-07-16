<?php TemplateUtility::printHeader('My Availability', array('modules/nesp/nespWorkflow.css')); ?>
<?php TemplateUtility::printHeaderBlock(); ?>
<?php TemplateUtility::printTabs($this->active, $this->subActive); ?>
    <div id="main" class="nesp-workflow">
        <?php TemplateUtility::printQuickSearch(); ?>
        <div id="contents">
            <div class="nesp-page-title">
                <h2>My Availability</h2>
                <p>Eastern Time availability for interview scheduling. Nothing here sends messages or creates Zoom meetings.</p>
            </div>

            <div class="nesp-safety-banner">
                Time zone: Eastern Time. Changes are saved immediately and audited. Existing assignments remain visible.
            </div>

            <div class="nesp-two-column">
                <div class="nesp-panel">
                    <h3>Availability Status</h3>
                    <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=setInterviewerAvailabilityStatus" class="nesp-form">
                        <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                        <input type="hidden" name="interviewerProfileID" value="<?php echo((int) $this->profile['interviewer_profile_id']); ?>" />
                        <label>
                            Status
                            <select name="availabilityStatusKey">
                                <option value="open"<?php if (!isset($this->profile['availability_status_key']) || $this->profile['availability_status_key'] !== 'closed'): ?> selected="selected"<?php endif; ?>>Open for Interviews</option>
                                <option value="closed"<?php if (isset($this->profile['availability_status_key']) && $this->profile['availability_status_key'] === 'closed'): ?> selected="selected"<?php endif; ?>>Closed for Interviews</option>
                            </select>
                        </label>
                        <label>
                            Reopen date/time
                            <input type="text" name="availabilityClosedUntil" value="<?php echo(htmlspecialchars(isset($this->profile['availability_closed_until']) ? $this->profile['availability_closed_until'] : '', ENT_QUOTES, 'UTF-8')); ?>" />
                        </label>
                        <label>
                            Reason
                            <textarea name="availabilityCloseReason" rows="3"><?php echo(htmlspecialchars(isset($this->profile['availability_close_reason']) ? $this->profile['availability_close_reason'] : '', ENT_QUOTES, 'UTF-8')); ?></textarea>
                        </label>
                        <button type="submit" class="nesp-primary-button">Save Status</button>
                    </form>
                </div>

                <div class="nesp-panel">
                    <h3>Add Weekly Time Block</h3>
                    <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=createInterviewerAvailability" class="nesp-form">
                        <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                        <input type="hidden" name="interviewerProfileID" value="<?php echo((int) $this->profile['interviewer_profile_id']); ?>" />
                        <label>
                            Day
                            <select name="weekdayKey">
                                <?php foreach (array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') as $weekday): ?>
                                    <option value="<?php $this->_($weekday); ?>"><?php $this->_($weekday); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            Open time
                            <input type="text" name="startTime" value="09:00" />
                        </label>
                        <label>
                            Close time
                            <input type="text" name="endTime" value="12:00" />
                        </label>
                        <input type="hidden" name="timezone" value="America/New_York" />
                        <input type="hidden" name="slotMinutes" value="<?php echo((int) $this->availabilityTemplate['slot_minutes']); ?>" />
                        <input type="hidden" name="bufferMinutes" value="<?php echo((int) $this->availabilityTemplate['buffer_minutes']); ?>" />
                        <label>
                            Notes
                            <textarea name="notes" rows="2"></textarea>
                        </label>
                        <button type="submit" class="nesp-secondary-button">Add Time Block</button>
                    </form>
                </div>
            </div>

            <div class="nesp-two-column">
                <div class="nesp-panel">
                    <h3>Date Override</h3>
                    <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=createInterviewerAvailabilityOverride" class="nesp-form">
                        <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                        <input type="hidden" name="interviewerProfileID" value="<?php echo((int) $this->profile['interviewer_profile_id']); ?>" />
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
                        <button type="submit" class="nesp-secondary-button">Save Date Override</button>
                    </form>
                </div>

                <div class="nesp-panel">
                    <h3>Blackout Date</h3>
                    <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=createInterviewerBlackout" class="nesp-form">
                        <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                        <input type="hidden" name="interviewerProfileID" value="<?php echo((int) $this->profile['interviewer_profile_id']); ?>" />
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
                <h3>Saved Weekly Blocks</h3>
                <table class="nesp-table">
                    <tr>
                        <th>Day</th>
                        <th>Open</th>
                        <th>Close</th>
                        <th>Buffer</th>
                        <th>Notes</th>
                    </tr>
                    <?php foreach ($this->availability['recurring'] as $block): ?>
                    <tr>
                        <td><?php $this->_($block['weekday_key']); ?></td>
                        <td><?php $this->_(substr($block['start_time'], 0, 5)); ?></td>
                        <td><?php $this->_(substr($block['end_time'], 0, 5)); ?></td>
                        <td><?php $this->_((int) $block['buffer_minutes'] . ' min'); ?></td>
                        <td><?php $this->_($block['notes']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!count($this->availability['recurring'])): ?>
                    <tr><td colspan="5">No weekly availability has been saved yet.</td></tr>
                    <?php endif; ?>
                </table>
            </div>

            <div class="nesp-two-column">
                <div class="nesp-panel">
                    <h3>Date Overrides</h3>
                    <table class="nesp-table">
                        <tr><th>Date</th><th>Type</th><th>Window</th></tr>
                        <?php foreach ($this->availability['overrides'] as $override): ?>
                        <tr>
                            <td><?php $this->_($override['override_date']); ?></td>
                            <td><?php $this->_($override['override_type_key']); ?></td>
                            <td><?php $this->_($override['start_time'] . ' - ' . $override['end_time']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (!count($this->availability['overrides'])): ?>
                        <tr><td colspan="3">No date overrides.</td></tr>
                        <?php endif; ?>
                    </table>
                </div>

                <div class="nesp-panel">
                    <h3>Blackout Dates</h3>
                    <table class="nesp-table">
                        <tr><th>Starts</th><th>Ends</th><th>All Day</th></tr>
                        <?php foreach ($this->availability['blackouts'] as $blackout): ?>
                        <tr>
                            <td><?php $this->_($blackout['starts_at']); ?></td>
                            <td><?php $this->_($blackout['ends_at']); ?></td>
                            <td><?php echo(((int) $blackout['is_all_day'] === 1) ? 'Yes' : 'No'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (!count($this->availability['blackouts'])): ?>
                        <tr><td colspan="3">No blackout dates.</td></tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
<?php TemplateUtility::printFooter(); ?>
