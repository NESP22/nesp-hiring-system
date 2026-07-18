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
            <?php if ($this->importResult !== null): ?>
                <?php if (!empty($this->importResult['ok'])): ?>
                    <div class="nesp-success">
                        Staffing import finished. Imported <?php $this->_($this->importResult['rows_imported']); ?> role rows; skipped <?php $this->_($this->importResult['skipped']); ?> already-imported rows. Forecast was recalculated from reviewed imported rows only.
                    </div>
                <?php else: ?>
                    <div class="nesp-empty">
                        Import stopped: <?php $this->_($this->importResult['error']); ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ((int) $this->forecast['sourceStatus']['rows_imported'] === 0 && !count($this->forecast['history'])): ?>
            <div class="nesp-empty">
                No historical schedules imported yet.
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

            <div class="nesp-import-review">
                <h3>Controlled Staffing Workbook Dry-Run</h3>
                <p class="nesp-help-text">Upload an exported Fall staffing workbook to inspect rows and warnings before any database import. This dry-run does not save rows, contact applicants, enable integrations, publish ads, or change candidate records.</p>
                <form method="post" enctype="multipart/form-data" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=dryRunStaffingImport" class="nesp-inline-form nesp-upload-form">
                    <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                    <input type="file" name="staffingWorkbook" class="nesp-file-input" accept=".xlsx,.csv" />
                    <button type="submit" class="nesp-secondary-button">Run Dry-Run</button>
                </form>
                <?php if ($this->dryRunResult !== null): ?>
                    <?php if ($this->dryRunResult['error'] !== ''): ?>
                        <div class="nesp-empty"><?php $this->_($this->dryRunResult['error']); ?></div>
                    <?php else: ?>
                        <?php $dryRun = $this->dryRunResult['result']['dry_run']; ?>
                        <div class="nesp-card-grid nesp-card-grid-compact">
                            <div class="nesp-card">
                                <span class="nesp-card-label">Tabs Inspected</span>
                                <strong><?php $this->_($dryRun['source_summary']['total_tabs']); ?></strong>
                            </div>
                            <div class="nesp-card">
                                <span class="nesp-card-label">Job Rows Found</span>
                                <strong><?php $this->_($dryRun['quality']['recognized_job_rows']); ?></strong>
                            </div>
                            <div class="nesp-card">
                                <span class="nesp-card-label">Rows Need Review</span>
                                <strong><?php $this->_($dryRun['quality']['ambiguous_rows']); ?></strong>
                            </div>
                            <div class="nesp-card">
                                <span class="nesp-card-label">Rows Imported</span>
                                <strong>0</strong>
                            </div>
                        </div>
                        <div class="nesp-two-column">
                            <div class="nesp-panel">
                                <h4>Source Inventory</h4>
                                <table class="nesp-table">
                                    <tr><th>Years found</th><td><?php $this->_(count($dryRun['source_summary']['years_found']) ? implode(', ', $dryRun['source_summary']['years_found']) : 'None'); ?></td></tr>
                                    <tr><th>Prior Fall seasons</th><td><?php echo($dryRun['source_summary']['prior_fall_years_present'] ? 'Yes' : 'No'); ?></td></tr>
                                    <tr><th>Historical workbooks needed</th><td><?php echo($dryRun['source_summary']['requires_additional_historical_workbooks'] ? 'Yes' : 'No'); ?></td></tr>
                                    <tr><th>Tabs with jobs</th><td><?php $this->_(count($dryRun['source_summary']['tabs_with_jobs']) ? implode(', ', $dryRun['source_summary']['tabs_with_jobs']) : 'None'); ?></td></tr>
                                    <tr><th>Tabs with assignments</th><td><?php $this->_(count($dryRun['source_summary']['tabs_with_assignments']) ? implode(', ', $dryRun['source_summary']['tabs_with_assignments']) : 'None'); ?></td></tr>
                                </table>
                            </div>
                            <div class="nesp-panel">
                                <h4>Quality Review</h4>
                                <table class="nesp-table">
                                    <tr><th>Missing dates</th><td><?php $this->_($dryRun['quality']['rows_missing_dates']); ?></td></tr>
                                    <tr><th>Missing locations</th><td><?php $this->_($dryRun['quality']['rows_missing_location']); ?></td></tr>
                                    <tr><th>Missing start/end</th><td><?php $this->_($dryRun['quality']['rows_missing_start_or_end']); ?></td></tr>
                                    <tr><th>Invalid staffing strings</th><td><?php $this->_($dryRun['quality']['invalid_staffing_rows']); ?></td></tr>
                                    <tr><th>Duplicate rows</th><td><?php $this->_($dryRun['quality']['duplicate_rows']); ?></td></tr>
                                    <tr><th>Total warnings</th><td><?php $this->_($dryRun['quality']['issue_count']); ?></td></tr>
                                </table>
                            </div>
                        </div>
                        <div class="nesp-panel">
                            <h4>Tab Summary</h4>
                            <table class="nesp-table">
                                <tr><th>Tab</th><th>Years</th><th>Jobs</th><th>Staffing Rows</th><th>Assignments</th><th>Review Rows</th></tr>
                                <?php foreach ($dryRun['tab_summaries'] as $summary): ?>
                                <tr>
                                    <td><?php $this->_($summary['tab_name']); ?></td>
                                    <td><?php $this->_(count($summary['years_found']) ? implode(', ', $summary['years_found']) : '-'); ?></td>
                                    <td><?php $this->_($summary['recognized_job_rows']); ?></td>
                                    <td><?php $this->_($summary['staffing_rows']); ?></td>
                                    <td><?php $this->_($summary['assignment_rows']); ?></td>
                                    <td><?php $this->_($summary['ambiguous_rows']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </table>
                        </div>
                        <div class="nesp-panel">
                            <h4>Warnings Preview</h4>
                            <table class="nesp-table">
                                <tr><th>Issue</th><th>Message</th></tr>
                                <?php foreach (array_slice($this->dryRunResult['result']['issues'], 0, 25) as $issue): ?>
                                <tr>
                                    <td><?php $this->_($issue['issue_key']); ?></td>
                                    <td><?php $this->_($issue['message']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (!count($this->dryRunResult['result']['issues'])): ?>
                                <tr><td colspan="2">No warnings found in the dry-run.</td></tr>
                                <?php endif; ?>
                            </table>
                            <p class="nesp-help-text">Dry-run complete. No source rows were imported. A controlled import still requires Craig approval, an encrypted backup, additive migrations, and valid-row approval.</p>
                        </div>
                        <?php if (isset($this->dryRunResult['review_rows'])): ?>
                        <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=importApprovedStaffingRows" class="nesp-import-approval-form">
                            <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                            <input type="hidden" name="dryRunBatchID" value="<?php echo(htmlspecialchars($this->dryRunResult['batch_id'], ENT_QUOTES, 'UTF-8')); ?>" />
                            <div class="nesp-panel">
                                <h4>Step 2: Review and Approve Rows</h4>
                                <p class="nesp-help-text">Only valid rows can be approved. Ambiguous rows stay locked until the workbook is corrected and a new dry-run is run. This dry-run batch expires at <?php $this->_($this->dryRunResult['expires_at']); ?>.</p>
                                <div class="nesp-inline-form">
                                    <button type="button" class="nesp-secondary-button" onclick="nespSetStaffingApprovals(true);">Approve all valid rows</button>
                                    <button type="button" class="nesp-secondary-button" onclick="nespSetStaffingApprovals(false);">Clear all</button>
                                    <span class="nesp-help-text"><span id="nesp-approved-row-count">0</span> rows approved.</span>
                                </div>
                                <div class="nesp-table-scroll">
                                    <table class="nesp-table nesp-review-table">
                                        <tr>
                                            <th>Approve</th>
                                            <th>Tab</th>
                                            <th>Row</th>
                                            <th>Date</th>
                                            <th>Job / Location</th>
                                            <th>Staffing</th>
                                            <th>P</th>
                                            <th>Lead</th>
                                            <th>Table</th>
                                            <th>Assist</th>
                                            <th>Total</th>
                                            <th>Status</th>
                                        </tr>
                                        <?php foreach ($this->dryRunResult['review_rows'] as $reviewRow): ?>
                                        <tr>
                                            <td>
                                                <?php if (!empty($reviewRow['is_valid'])): ?>
                                                    <input type="checkbox" class="nesp-staffing-approval-checkbox" name="approvedRows[]" value="<?php echo(htmlspecialchars($reviewRow['review_key'], ENT_QUOTES, 'UTF-8')); ?>" onchange="nespUpdateApprovedRowCount();" />
                                                <?php else: ?>
                                                    <span class="nesp-badge nesp-badge-muted">Review</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php $this->_($reviewRow['source_sheet_name']); ?></td>
                                            <td><?php $this->_($reviewRow['source_row_number']); ?></td>
                                            <td><?php $this->_($reviewRow['event_date']); ?></td>
                                            <td>
                                                <strong><?php $this->_($reviewRow['event_name']); ?></strong><br />
                                                <span class="nesp-help-text"><?php $this->_($reviewRow['location']); ?></span>
                                            </td>
                                            <td><?php $this->_($reviewRow['staffing_text_original']); ?></td>
                                            <td><?php $this->_($reviewRow['photographers']); ?></td>
                                            <td><?php $this->_($reviewRow['leads']); ?></td>
                                            <td><?php $this->_($reviewRow['table_staff']); ?></td>
                                            <td><?php $this->_($reviewRow['assistants']); ?></td>
                                            <td><?php $this->_($reviewRow['total_required_staff']); ?></td>
                                            <td>
                                                <?php if (!empty($reviewRow['is_valid'])): ?>
                                                    <span class="nesp-badge nesp-badge-success">Valid</span>
                                                <?php else: ?>
                                                    <span class="nesp-badge nesp-badge-warning"><?php $this->_(count($reviewRow['warnings']) ? implode(', ', $reviewRow['warnings']) : $reviewRow['duplicate_status']); ?></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </table>
                                </div>
                            </div>
                            <div class="nesp-panel">
                                <h4>Step 3: Verify Backup and Import</h4>
                                <p class="nesp-help-text">Import writes only approved normalized staffing rows. It never changes the Google Sheet and never contacts applicants or staff.</p>
                                <label class="nesp-form-label" for="backupReference">Fresh encrypted backup reference</label>
                                <input type="text" id="backupReference" name="backupReference" class="nesp-form-control" placeholder="Backup timestamp or verified reference" />
                                <label class="nesp-checkbox-row">
                                    <input type="checkbox" name="backupVerified" value="1" />
                                    I verified the fresh encrypted production database backup and want to import only the approved rows above.
                                </label>
                                <button type="submit" class="nesp-primary-button" onclick="return confirm('Import only the approved staffing rows from this dry-run batch?');">Import Approved Rows</button>
                            </div>
                        </form>
                        <script type="text/javascript">
                        function nespSetStaffingApprovals(checked) {
                            var boxes = document.querySelectorAll('.nesp-staffing-approval-checkbox');
                            for (var i = 0; i < boxes.length; i++) {
                                boxes[i].checked = checked;
                            }
                            nespUpdateApprovedRowCount();
                        }
                        function nespUpdateApprovedRowCount() {
                            var boxes = document.querySelectorAll('.nesp-staffing-approval-checkbox');
                            var count = 0;
                            for (var i = 0; i < boxes.length; i++) {
                                if (boxes[i].checked) {
                                    count++;
                                }
                            }
                            var counter = document.getElementById('nesp-approved-row-count');
                            if (counter) {
                                counter.innerHTML = count;
                            }
                        }
                        nespUpdateApprovedRowCount();
                        </script>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>
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
                    <h3>Fall 2026 Preliminary Forecast</h3>
                    <p class="nesp-help-text">The gap is advisory and never opens, closes, or edits jobs.</p>
                    <table class="nesp-table">
                        <tr><th>Required headcount at peak</th><td><?php $this->_($this->forecast['metrics']['peak_day_staffing']); ?></td></tr>
                        <tr><th>Current confirmed staff</th><td>0</td></tr>
                        <tr><th>Recommended pool</th><td><?php $this->_($this->forecast['metrics']['recommended_pool']); ?></td></tr>
                        <tr><th>Recommended backup / on-call</th><td><?php $this->_($this->forecast['metrics']['recommended_backup']); ?></td></tr>
                        <tr><th>Preliminary hiring gap</th><td><?php $this->_($this->forecast['metrics']['hiring_gap']); ?></td></tr>
                        <tr><th>Average staff per event</th><td><?php $this->_($this->forecast['metrics']['average_staff_per_event']); ?></td></tr>
                    </table>
                    <?php if ($this->getUserAccessLevel('settings.administration') >= ACCESS_LEVEL_SA): ?>
                    <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=createStaffingRecommendation" class="nesp-inline-form">
                        <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                        <?php if ((int) $this->forecast['sourceStatus']['rows_imported'] === 0): ?>
                            <button type="button" class="nesp-secondary-button" disabled="disabled">Create Hiring Recommendation</button>
                            <span class="nesp-help-text">Import verified staffing history before creating a draft recommendation.</span>
                        <?php else: ?>
                            <button type="submit" class="nesp-secondary-button">Create Hiring Recommendation</button>
                        <?php endif; ?>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

            <div class="nesp-panel">
                <h3>Preliminary Fall 2026 Gap</h3>
                <p class="nesp-help-text">Uses approved September-November historical rows only.</p>
                <table class="nesp-table">
                    <tr><th>Season</th><td><?php $this->_($this->forecast['fall2026Gap']['season_label']); ?></td></tr>
                    <tr><th>Historical fall events</th><td><?php echo((int) $this->forecast['fall2026Gap']['historical_fall_events']); ?></td></tr>
                    <tr><th>Historical peak-day staffing</th><td><?php echo((int) $this->forecast['fall2026Gap']['historical_peak_day_staffing']); ?></td></tr>
                    <tr><th>Recommended pool</th><td><?php echo((int) $this->forecast['fall2026Gap']['recommended_pool']); ?></td></tr>
                    <tr><th>Recommended backup</th><td><?php echo((int) $this->forecast['fall2026Gap']['recommended_backup']); ?></td></tr>
                    <tr><th>Preliminary hiring gap</th><td><?php echo((int) $this->forecast['fall2026Gap']['preliminary_hiring_gap']); ?></td></tr>
                    <tr><th>Confidence</th><td><?php $this->_($this->forecast['fall2026Gap']['confidence']); ?></td></tr>
                </table>
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
