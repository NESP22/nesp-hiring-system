<?php TemplateUtility::printHeader('Scheduling Conflict Review', array('modules/nesp/nespWorkflow.css')); ?>
<?php TemplateUtility::printHeaderBlock(); ?>
<?php TemplateUtility::printTabs($this->active, $this->subActive); ?>
    <div id="main" class="nesp-workflow">
        <?php TemplateUtility::printQuickSearch(); ?>
        <div id="contents">
            <div class="nesp-page-title">
                <h2>Check a scheduling conflict</h2>
                <p>Review the issue, choose the safest next step, and record an override only when Craig approves it.</p>
            </div>

            <div class="nesp-safety-banner nesp-interviewer-note">
                This page does not schedule interviews, reassign candidates, send messages, or create Zoom meetings.
            </div>

            <div class="nesp-card-grid nesp-card-grid-tight">
                <div class="nesp-panel">
                    <h3>Conflict found</h3>
                    <ul class="nesp-list">
                        <?php foreach ($this->review['conflicts'] as $conflict): ?>
                            <li><?php $this->_($conflict); ?></li>
                        <?php endforeach; ?>
                        <?php if (!count($this->review['conflicts'])): ?>
                            <li>No conflict found for the provided time.</li>
                        <?php endif; ?>
                    </ul>
                </div>

                <div class="nesp-panel">
                    <h3>Try another time</h3>
                    <ul class="nesp-list">
                        <?php foreach ($this->review['alternate_times'] as $alternateTime): ?>
                            <li><?php $this->_($alternateTime); ?></li>
                        <?php endforeach; ?>
                        <?php if (!count($this->review['alternate_times'])): ?>
                            <li>No alternate time found from saved availability.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <div class="nesp-panel">
                <h3>Other eligible interviewers</h3>
                <?php if (count($this->review['eligible_interviewers'])): ?>
                    <div class="nesp-card-grid nesp-card-grid-tight">
                        <?php foreach ($this->review['eligible_interviewers'] as $eligible): ?>
                        <div class="nesp-card">
                            <h4><?php $this->_($eligible['display_name']); ?></h4>
                            <p class="nesp-muted"><?php $this->_($eligible['email']); ?></p>
                            <p><?php $this->_((int) $eligible['max_interviews_per_day'] . '/day, ' . (int) $eligible['max_interviews_per_week'] . '/week'); ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="nesp-empty">No other eligible open interviewer was found for this job.</div>
                <?php endif; ?>
            </div>

            <div class="nesp-panel">
                <h3>Record an approved override</h3>
                <div class="nesp-confirm-box">Use this only after Craig approves the decision. It records the reason in audit history; it does not schedule the interview.</div>
                <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=recordSchedulingOverride" class="nesp-form">
                    <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                    <input type="hidden" name="interviewerProfileID" value="<?php echo((int) $this->interviewerProfileID); ?>" />
                    <input type="hidden" name="candidateID" value="<?php echo((int) $this->candidateID); ?>" />
                    <input type="hidden" name="jobOrderID" value="<?php echo((int) $this->jobOrderID); ?>" />
                    <input type="hidden" name="startTime" value="<?php echo(htmlspecialchars($this->startTime, ENT_QUOTES, 'UTF-8')); ?>" />
                    <input type="hidden" name="endTime" value="<?php echo(htmlspecialchars($this->endTime, ENT_QUOTES, 'UTF-8')); ?>" />
                    <input type="hidden" name="conflictsJSON" value="<?php echo(htmlspecialchars(json_encode($this->review['conflicts']), ENT_QUOTES, 'UTF-8')); ?>" />
                    <label>
                        Override reason
                        <textarea name="overrideReason" rows="3"></textarea>
                    </label>
                        <button type="submit" class="nesp-primary-button">Continue to override confirmation</button>
                </form>
            </div>
        </div>
    </div>
<?php TemplateUtility::printFooter(); ?>
