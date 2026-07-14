<?php TemplateUtility::printHeader('NESP Staffing Forecast', array('modules/nesp/nespWorkflow.css')); ?>
<?php TemplateUtility::printHeaderBlock(); ?>
<?php TemplateUtility::printTabs($this->active, $this->subActive); ?>
    <div id="main" class="nesp-workflow">
        <?php TemplateUtility::printQuickSearch(); ?>
        <div id="contents">
            <div class="nesp-page-title">
                <h2>Staffing Forecast</h2>
                <p>Seasonal photographer planning view. Forecasts are guidance, not guaranteed staffing numbers.</p>
            </div>

            <div class="nesp-safety-banner">
                This screen does not contact applicants, publish postings, edit jobs, import Drive files automatically, or change feature flags.
            </div>

            <div class="nesp-card-grid nesp-card-grid-compact">
                <div class="nesp-card">
                    <span class="nesp-card-label">Source Status</span>
                    <strong><?php $this->_($this->forecast['sourceStatus']['status_label']); ?></strong>
                </div>
                <div class="nesp-card">
                    <span class="nesp-card-label">Rows Imported</span>
                    <strong><?php $this->_($this->forecast['sourceStatus']['rows_imported']); ?></strong>
                </div>
                <div class="nesp-card">
                    <span class="nesp-card-label">Rows Requiring Review</span>
                    <strong><?php $this->_($this->forecast['sourceStatus']['rows_requiring_review']); ?></strong>
                </div>
                <div class="nesp-card">
                    <span class="nesp-card-label">Confidence</span>
                    <strong><?php $this->_($this->forecast['metrics']['confidence']); ?></strong>
                </div>
            </div>

            <div class="nesp-panel">
                <h3>Source Status</h3>
                <table class="nesp-table">
                    <tr><th>No historical data imported</th><td><?php echo(((int) $this->forecast['sourceStatus']['rows_imported'] === 0) ? 'Yes' : 'No'); ?></td></tr>
                    <tr><th>Files discovered</th><td><?php $this->_($this->forecast['sourceStatus']['files_discovered']); ?></td></tr>
                    <tr><th>Files imported</th><td><?php $this->_($this->forecast['sourceStatus']['files_imported']); ?></td></tr>
                    <tr><th>Rows imported</th><td><?php $this->_($this->forecast['sourceStatus']['rows_imported']); ?></td></tr>
                    <tr><th>Rows requiring review</th><td><?php $this->_($this->forecast['sourceStatus']['rows_requiring_review']); ?></td></tr>
                    <tr><th>Last import date</th><td><?php $this->_($this->forecast['sourceStatus']['last_import_date']); ?></td></tr>
                </table>
            </div>

            <div class="nesp-two-column">
                <div class="nesp-panel">
                    <h3>Season Summary</h3>
                    <p class="nesp-help-text">Counts distinct normalized events by season year.</p>
                    <table class="nesp-table">
                        <tr><th>Season</th><th>Events</th><th>Unique Staff</th></tr>
                        <?php foreach ($this->forecast['metrics']['events_by_season'] as $season => $events): ?>
                        <tr>
                            <td><?php $this->_($season); ?></td>
                            <td><?php $this->_($events); ?></td>
                            <td><?php $this->_(isset($this->forecast['metrics']['unique_staff_by_season'][$season]) ? $this->forecast['metrics']['unique_staff_by_season'][$season] : 0); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (!count($this->forecast['metrics']['events_by_season'])): ?>
                        <tr><td colspan="3">No normalized event rows are available yet.</td></tr>
                        <?php endif; ?>
                    </table>
                </div>

                <div class="nesp-panel">
                    <h3>Hiring Gap</h3>
                    <p class="nesp-help-text">The gap is advisory and never opens, closes, or edits jobs.</p>
                    <table class="nesp-table">
                        <tr><th>Recommended pool</th><td><?php $this->_($this->forecast['metrics']['recommended_pool']); ?></td></tr>
                        <tr><th>Recommended backup</th><td><?php $this->_($this->forecast['metrics']['recommended_backup']); ?></td></tr>
                        <tr><th>Hiring target</th><td><?php $this->_($this->forecast['metrics']['hiring_gap']); ?></td></tr>
                        <tr><th>Peak-day staffing</th><td><?php $this->_($this->forecast['metrics']['peak_day_staffing']); ?></td></tr>
                        <tr><th>Average staff per event</th><td><?php $this->_($this->forecast['metrics']['average_staff_per_event']); ?></td></tr>
                    </table>
                    <?php if ($this->getUserAccessLevel('settings.administration') >= ACCESS_LEVEL_SA): ?>
                    <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=createStaffingRecommendation" class="nesp-inline-form">
                        <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                        <button type="submit" class="nesp-secondary-button">Create Hiring Recommendation</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

            <div class="nesp-two-column">
                <div class="nesp-panel">
                    <h3>Week-by-Week</h3>
                    <p class="nesp-help-text">Shows normalized events per week after import review.</p>
                    <table class="nesp-table">
                        <tr><th>Week Starting</th><th>Events</th></tr>
                        <?php foreach ($this->forecast['metrics']['events_by_week'] as $week => $events): ?>
                        <tr><td><?php $this->_($week); ?></td><td><?php $this->_($events); ?></td></tr>
                        <?php endforeach; ?>
                        <?php if (!count($this->forecast['metrics']['events_by_week'])): ?>
                        <tr><td colspan="2">No imported weekly rows are available.</td></tr>
                        <?php endif; ?>
                    </table>
                </div>

                <div class="nesp-panel">
                    <h3>Peak Weekends</h3>
                    <p class="nesp-help-text">Peak staffing currently uses the busiest normalized event date.</p>
                    <table class="nesp-table">
                        <tr><th>Peak concurrent staff</th><td><?php $this->_($this->forecast['metrics']['peak_concurrent_staff']); ?></td></tr>
                        <tr><th>Total staff-hours</th><td><?php $this->_($this->forecast['metrics']['staff_hours']); ?></td></tr>
                    </table>
                </div>
            </div>

            <div class="nesp-two-column">
                <div class="nesp-panel">
                    <h3>Staffing by State</h3>
                    <p class="nesp-help-text">Counts normalized event rows by state when source data provides a state.</p>
                    <table class="nesp-table">
                        <tr><th>State</th><th>Events</th></tr>
                        <?php foreach ($this->forecast['metrics']['events_by_state'] as $state => $events): ?>
                        <tr><td><?php $this->_($state); ?></td><td><?php $this->_($events); ?></td></tr>
                        <?php endforeach; ?>
                    </table>
                </div>

                <div class="nesp-panel">
                    <h3>Staffing by Role</h3>
                    <p class="nesp-help-text">Sums normalized staff assignments by role.</p>
                    <table class="nesp-table">
                        <tr><th>Role</th><th>Assignments</th></tr>
                        <?php foreach ($this->forecast['metrics']['staff_by_role'] as $role => $assignments): ?>
                        <tr><td><?php $this->_($role); ?></td><td><?php $this->_($assignments); ?></td></tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>

            <div class="nesp-panel">
                <h3>Historical Comparison</h3>
                <p class="nesp-help-text">Legacy history rows remain separate from normalized imports and are shown for review context only.</p>
                <table class="nesp-table">
                    <tr>
                        <th>Week</th>
                        <th>Season</th>
                        <th>Events</th>
                        <th>Slots</th>
                        <th>Hours</th>
                    </tr>
                    <?php foreach ($this->forecast['history'] as $row): ?>
                    <tr>
                        <td><?php $this->_($row['week_start']); ?></td>
                        <td><?php $this->_($row['season_year'] . ' ' . $row['season_name']); ?></td>
                        <td><?php $this->_($row['event_count']); ?></td>
                        <td><?php $this->_($row['photographer_slots']); ?></td>
                        <td><?php $this->_($row['photographer_hours']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!count($this->forecast['history'])): ?>
                    <tr><td colspan="5">No historical summary rows are available.</td></tr>
                    <?php endif; ?>
                </table>
            </div>

            <div class="nesp-two-column">
                <div class="nesp-panel">
                    <h3>Formulas and Assumptions</h3>
                    <ul class="nesp-list">
                        <?php foreach ($this->forecast['assumptions'] as $assumption): ?>
                            <li><?php $this->_($assumption); ?></li>
                        <?php endforeach; ?>
                        <?php foreach ($this->forecast['metrics']['formulas'] as $label => $formula): ?>
                            <li><?php $this->_($label . ': ' . $formula); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="nesp-panel">
                    <h3>Import Issues</h3>
                    <table class="nesp-table">
                        <tr><th>Issue</th><th>Message</th><th>Date</th></tr>
                        <?php foreach ($this->forecast['importIssues'] as $issue): ?>
                        <tr>
                            <td><?php $this->_($issue['issue_key']); ?></td>
                            <td><?php $this->_($issue['message']); ?></td>
                            <td><?php $this->_($issue['date_created']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (!count($this->forecast['importIssues'])): ?>
                        <tr><td colspan="3">No open import issues.</td></tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
<?php TemplateUtility::printFooter(); ?>
