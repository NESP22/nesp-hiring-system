<?php TemplateUtility::printHeader('NESP Staffing Forecast', array('modules/nesp/nespWorkflow.css')); ?>
<?php TemplateUtility::printHeaderBlock(); ?>
<?php TemplateUtility::printTabs($this->active, $this->subActive); ?>
    <div id="main" class="nesp-workflow">
        <?php TemplateUtility::printQuickSearch(); ?>
        <div id="contents">
            <div class="nesp-page-title">
                <h2>Staffing Forecast</h2>
                <p>Seasonal photographer planning view based on imported or fixture historical schedule rows.</p>
            </div>

            <div class="nesp-safety-banner">
                Forecasts are planning guidance only. This screen does not contact applicants, publish postings, send messages, or change production feature flags.
            </div>

            <div class="nesp-panel">
                <h3>Monthly Photographer Demand</h3>
                <table class="nesp-table">
                    <tr>
                        <th>Month</th>
                        <th>Weeks in History</th>
                        <th>Avg Events</th>
                        <th>Avg Photographer Slots</th>
                        <th>Avg Hours</th>
                        <th>Pipeline Target</th>
                        <th>Confidence</th>
                    </tr>
                    <?php foreach ($this->forecast['months'] as $month): ?>
                    <tr>
                        <td><?php $this->_($month['month']); ?></td>
                        <td><?php $this->_($month['weeks']); ?></td>
                        <td><?php $this->_($month['avg_events']); ?></td>
                        <td><?php $this->_($month['avg_slots']); ?></td>
                        <td><?php $this->_($month['avg_hours']); ?></td>
                        <td><?php $this->_($month['recommended_pipeline']); ?></td>
                        <td><?php $this->_($month['confidence']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!count($this->forecast['months'])): ?>
                    <tr><td colspan="7">No staffing history has been imported.</td></tr>
                    <?php endif; ?>
                </table>
            </div>

            <div class="nesp-two-column">
                <div class="nesp-panel">
                    <h3>Assumptions</h3>
                    <ul class="nesp-list">
                        <?php foreach ($this->forecast['assumptions'] as $assumption): ?>
                            <li><?php $this->_($assumption); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="nesp-panel">
                    <h3>History Rows</h3>
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
                        <tr><td colspan="5">No history rows are available.</td></tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
<?php TemplateUtility::printFooter(); ?>
