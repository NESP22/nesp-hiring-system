<?php TemplateUtility::printHeader('Phone Screen Availability', array('modules/nesp/nespWorkflow.css')); ?>
<?php TemplateUtility::printHeaderBlock(); ?>
<?php TemplateUtility::printTabs($this->active, $this->subActive); ?>
    <div id="main" class="nesp-workflow">
        <?php TemplateUtility::printQuickSearch(); ?>
        <div id="contents">
            <div class="nesp-page-title">
                <h2>Phone Screen Availability</h2>
                <p>Craig controls when automated NESP Hiring phone screens may be booked. Times are shown to candidates in Eastern Time by default.</p>
            </div>

            <div class="nesp-safety-banner">
                These settings only define appointment windows. They do not enable Vapi, send messages, or place calls.
            </div>

            <div class="nesp-dashboard-nav">
                <a href="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=phoneScreens">Phone Screens</a>
                <a class="active" href="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=phoneScreenAvailability">Availability</a>
            </div>

            <div class="nesp-panel">
                <h3>Scheduling Rules</h3>
                <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=savePhoneScreenAvailability" class="nesp-form nesp-form-grid">
                    <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                    <label>Time zone <input type="text" name="timezone" value="<?php $this->_($this->settings['timezone']); ?>" /></label>
                    <label>Earliest call time <input type="time" name="earliest_call_time" value="<?php $this->_($this->settings['earliest_call_time']); ?>" /></label>
                    <label>Latest call time <input type="time" name="latest_call_time" value="<?php $this->_($this->settings['latest_call_time']); ?>" /></label>
                    <label>Booking slots, minutes <input type="number" min="1" name="slot_minutes" value="<?php echo((int) $this->settings['slot_minutes']); ?>" /></label>
                    <label>Call duration, minutes <input type="number" min="1" name="call_duration_minutes" value="<?php echo((int) $this->settings['call_duration_minutes']); ?>" /></label>
                    <label>Buffer, minutes <input type="number" min="0" name="buffer_minutes" value="<?php echo((int) $this->settings['buffer_minutes']); ?>" /></label>
                    <label>Minimum notice, minutes <input type="number" min="1" name="min_booking_notice_minutes" value="<?php echo((int) $this->settings['min_booking_notice_minutes']); ?>" /></label>
                    <label>Link expiration, hours <input type="number" min="1" name="link_expiration_hours" value="<?php echo((int) $this->settings['link_expiration_hours']); ?>" /></label>
                    <label>Max screens per hour <input type="number" min="1" name="max_screens_per_hour" value="<?php echo((int) $this->settings['max_screens_per_hour']); ?>" /></label>
                    <label>Max screens per day <input type="number" min="1" name="max_screens_per_day" value="<?php echo((int) $this->settings['max_screens_per_day']); ?>" /></label>
                    <label>Booking horizon, days <input type="number" min="1" name="booking_horizon_days" value="<?php echo((int) $this->settings['booking_horizon_days']); ?>" /></label>
                    <button type="submit" class="nesp-primary-button">Save Scheduling Rules</button>
                </form>
            </div>

            <div class="nesp-two-column">
                <div class="nesp-panel">
                    <h3>Available Time Blocks</h3>
                    <table class="nesp-table">
                        <tr>
                            <th>Day</th>
                            <th>Start</th>
                            <th>End</th>
                            <th>Action</th>
                        </tr>
                        <?php $days = array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'); ?>
                        <?php foreach ($this->availabilityBlocks as $block): ?>
                        <tr>
                            <td><?php $this->_($days[(int) $block['weekday']]); ?></td>
                            <td><?php $this->_($block['start_time']); ?></td>
                            <td><?php $this->_($block['end_time']); ?></td>
                            <td>
                                <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=deletePhoneScreenAvailabilityBlock" class="nesp-inline-form">
                                    <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                                    <input type="hidden" name="availabilityBlockID" value="<?php echo((int) $block['availability_block_id']); ?>" />
                                    <button type="submit" class="nesp-secondary-button">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>

                    <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=createPhoneScreenAvailabilityBlock" class="nesp-form">
                        <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                        <label>
                            Day
                            <select name="weekday">
                                <?php foreach ($days as $index => $day): ?>
                                    <option value="<?php echo((int) $index); ?>"><?php $this->_($day); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Start <input type="time" name="startTime" value="09:00" /></label>
                        <label>End <input type="time" name="endTime" value="18:00" /></label>
                        <button type="submit" class="nesp-secondary-button">Add Time Block</button>
                    </form>
                </div>

                <div class="nesp-panel">
                    <h3>Blackout Dates</h3>
                    <table class="nesp-table">
                        <tr>
                            <th>Date</th>
                            <th>Label</th>
                            <th>Action</th>
                        </tr>
                        <?php foreach ($this->blackoutDates as $blackout): ?>
                        <tr>
                            <td><?php $this->_($blackout['blackout_date']); ?></td>
                            <td><?php $this->_($blackout['label']); ?></td>
                            <td>
                                <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=deletePhoneScreenBlackout" class="nesp-inline-form">
                                    <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                                    <input type="hidden" name="blackoutDateID" value="<?php echo((int) $blackout['blackout_date_id']); ?>" />
                                    <button type="submit" class="nesp-secondary-button">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (!count($this->blackoutDates)): ?>
                        <tr>
                            <td colspan="3">No blackout dates are configured.</td>
                        </tr>
                        <?php endif; ?>
                    </table>

                    <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=createPhoneScreenBlackout" class="nesp-form">
                        <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                        <label>Date <input type="date" name="blackoutDate" /></label>
                        <label>Label <input type="text" name="label" /></label>
                        <button type="submit" class="nesp-secondary-button">Add Blackout Date</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php TemplateUtility::printFooter(); ?>
