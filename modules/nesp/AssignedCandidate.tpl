<?php TemplateUtility::printHeader('Assigned Candidate', array('modules/nesp/nespWorkflow.css')); ?>
<?php TemplateUtility::printHeaderBlock(); ?>
<?php TemplateUtility::printTabs($this->active, $this->subActive); ?>
    <div id="main" class="nesp-workflow">
        <?php TemplateUtility::printQuickSearch(); ?>
        <div id="contents">
            <div class="nesp-page-title">
                <h2><?php $this->_($this->candidate['candidate_name']); ?></h2>
                <p><?php $this->_($this->candidate['role_title']); ?> assignment. Candidate access is limited to this explicit grant.</p>
            </div>

            <div class="nesp-two-column">
                <div class="nesp-panel">
                    <h3>Assignment Snapshot</h3>
                    <table class="nesp-table">
                        <tr><th>Stage</th><td><?php $this->_($this->candidate['stage_name']); ?></td></tr>
                        <tr><th>Waiting on</th><td><?php $this->_($this->candidate['waiting_on_key']); ?></td></tr>
                        <tr><th>Last activity</th><td><?php $this->_($this->candidate['last_activity']); ?></td></tr>
                        <tr><th>Summary</th><td><?php $this->_($this->candidate['summary']); ?></td></tr>
                        <?php if ((int) $this->candidate['can_view_resume'] === 1): ?>
                        <tr><th>Skills</th><td><?php $this->_($this->candidate['key_skills']); ?></td></tr>
                        <tr><th>Notes</th><td><?php $this->_($this->candidate['notes']); ?></td></tr>
                        <?php endif; ?>
                    </table>
                </div>

                <div class="nesp-panel">
                    <h3>Interviews</h3>
                    <table class="nesp-table">
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Status</th>
                        </tr>
                        <?php foreach ($this->candidate['interviews'] as $interview): ?>
                        <tr>
                            <td><?php $this->_($interview['scheduled_start'] ? date('M j, Y', strtotime($interview['scheduled_start'])) : ''); ?></td>
                            <td><?php $this->_($interview['scheduled_start'] ? date('g:i A', strtotime($interview['scheduled_start'])) : ''); ?></td>
                            <td><?php $this->_($interview['status_key']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (!count($this->candidate['interviews'])): ?>
                        <tr><td colspan="3">No interview record is attached to this assignment.</td></tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>

            <div class="nesp-panel">
                <h3>Scorecard</h3>
                <?php if (!empty($this->candidate['scorecard']) && $this->candidate['scorecard']['status_key'] === 'submitted'): ?>
                    <div class="nesp-empty">Scorecard submitted: <?php $this->_($this->candidate['scorecard']['overall_recommendation']); ?></div>
                <?php elseif ((int) $this->candidate['can_submit_scorecard'] === 1): ?>
                    <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=submitScorecard" class="nesp-scorecard-form">
                        <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                        <input type="hidden" name="candidateID" value="<?php echo((int) $this->candidate['candidate_id']); ?>" />
                        <input type="hidden" name="jobOrderID" value="<?php echo((int) $this->candidate['joborder_id']); ?>" />
                        <?php foreach ($this->scorecardQuestions as $question): ?>
                            <label>
                                <?php $this->_($question['label']); ?>
                                <?php if ($question['type'] === 'rating'): ?>
                                    <select name="answers[<?php $this->_($question['key']); ?>]">
                                        <option value="">Select</option>
                                        <option value="1">1 - concern</option>
                                        <option value="2">2 - mixed</option>
                                        <option value="3">3 - solid</option>
                                        <option value="4">4 - strong</option>
                                        <option value="5">5 - excellent</option>
                                    </select>
                                <?php else: ?>
                                    <textarea name="answers[<?php $this->_($question['key']); ?>]" rows="4"></textarea>
                                <?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                        <label>
                            Overall recommendation
                            <select name="overallRecommendation">
                                <option value="advance">Advance</option>
                                <option value="hold">Hold</option>
                                <option value="not_selected">Not selected</option>
                                <option value="needs_craig_review">Needs Craig review</option>
                            </select>
                        </label>
                        <button type="submit" class="nesp-primary-button">Submit Scorecard</button>
                    </form>
                <?php else: ?>
                    <div class="nesp-empty">This assignment does not allow scorecard submission.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php TemplateUtility::printFooter(); ?>
