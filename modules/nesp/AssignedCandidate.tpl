<?php TemplateUtility::printHeader('Assigned Candidate', array('modules/nesp/nespWorkflow.css')); ?>
<?php TemplateUtility::printHeaderBlock(); ?>
<?php TemplateUtility::printTabs($this->active, $this->subActive); ?>
    <div id="main" class="nesp-workflow">
        <?php TemplateUtility::printQuickSearch(); ?>
        <div id="contents">
            <div class="nesp-page-title">
                <h2><?php $this->_($this->candidate['candidate_name']); ?></h2>
                <p><?php $this->_($this->candidate['role_title']); ?> interview assignment</p>
            </div>

            <div class="nesp-dashboard-nav">
                <a href="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=assignedCandidates">My Next Actions</a>
                <a href="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=myAvailability">My Availability</a>
            </div>

            <div class="nesp-panel nesp-interview-hero">
                <div>
                    <span class="nesp-card-label">Interview checklist</span>
                    <h3>Review, talk, then score</h3>
                    <p>Use the candidate details below during the conversation. Save a draft if you need to come back later; submitting locks the scorecard for Craig to review.</p>
                </div>
                <div class="nesp-contact-actions">
                    <?php if (!empty($this->candidate['phone_cell'])): ?>
                        <a class="nesp-secondary-button" href="tel:<?php echo(htmlspecialchars(preg_replace('/[^0-9+]/', '', $this->candidate['phone_cell']), ENT_QUOTES, 'UTF-8')); ?>">Call candidate</a>
                    <?php endif; ?>
                    <?php if (!empty($this->candidate['email1'])): ?>
                        <a class="nesp-secondary-button" href="mailto:<?php echo(htmlspecialchars($this->candidate['email1'], ENT_QUOTES, 'UTF-8')); ?>">Email candidate</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="nesp-two-column">
                <div class="nesp-panel">
                    <h3>Assignment Snapshot</h3>
                    <table class="nesp-table">
                        <tr><th>Stage</th><td><?php $this->_($this->candidate['stage_name']); ?></td></tr>
                        <tr><th>Waiting on</th><td><?php $this->_($this->candidate['waiting_on_key']); ?></td></tr>
                        <tr><th>Last activity</th><td><?php $this->_($this->candidate['last_activity']); ?></td></tr>
                        <tr><th>Summary</th><td><?php $this->_($this->candidate['summary']); ?></td></tr>
                        <?php if (!empty($this->candidate['email1'])): ?><tr><th>Email</th><td><?php $this->_($this->candidate['email1']); ?></td></tr><?php endif; ?>
                        <?php if (!empty($this->candidate['phone_cell'])): ?><tr><th>Phone</th><td><?php $this->_($this->candidate['phone_cell']); ?></td></tr><?php endif; ?>
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
                <?php if (!empty($this->candidate['scorecard']) && $this->candidate['scorecard']['locked_at'] !== null && $this->candidate['scorecard']['unlocked_at'] === null): ?>
                    <div class="nesp-empty">Scorecard locked after submission: <?php $this->_($this->candidate['scorecard']['overall_recommendation']); ?></div>
                    <?php if ($this->getUserAccessLevel('settings.administration') >= ACCESS_LEVEL_SA): ?>
                        <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=unlockScorecard" class="nesp-inline-form">
                            <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                            <input type="hidden" name="scorecardResponseID" value="<?php echo((int) $this->candidate['scorecard']['scorecard_response_id']); ?>" />
                            <input type="hidden" name="candidateID" value="<?php echo((int) $this->candidate['candidate_id']); ?>" />
                            <input type="hidden" name="jobOrderID" value="<?php echo((int) $this->candidate['joborder_id']); ?>" />
                            <button type="submit" class="nesp-secondary-button">Unlock for Correction</button>
                        </form>
                    <?php endif; ?>
                <?php elseif ((int) $this->candidate['can_submit_scorecard'] === 1): ?>
                    <?php if (!empty($this->candidate['scorecard']) && $this->candidate['scorecard']['status_key'] === 'draft'): ?>
                        <div class="nesp-empty">Draft saved. Submission will lock the scorecard until Craig/admin reopens it.</div>
                    <?php endif; ?>
                    <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=submitScorecard" class="nesp-scorecard-form">
                        <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                        <input type="hidden" name="candidateID" value="<?php echo((int) $this->candidate['candidate_id']); ?>" />
                        <input type="hidden" name="jobOrderID" value="<?php echo((int) $this->candidate['joborder_id']); ?>" />
                        <?php foreach ($this->scorecardQuestions as $question): ?>
                            <?php $answerValue = isset($this->candidate['scorecard_answers'][$question['key']]) ? $this->candidate['scorecard_answers'][$question['key']] : ''; ?>
                            <label>
                                <?php $this->_($question['label']); ?>
                                <?php if ($question['type'] === 'rating'): ?>
                                    <select name="answers[<?php $this->_($question['key']); ?>]">
                                        <option value="">Select</option>
                                        <option value="1"<?php if ((string) $answerValue === '1'): ?> selected="selected"<?php endif; ?>>1 - concern</option>
                                        <option value="2"<?php if ((string) $answerValue === '2'): ?> selected="selected"<?php endif; ?>>2 - mixed</option>
                                        <option value="3"<?php if ((string) $answerValue === '3'): ?> selected="selected"<?php endif; ?>>3 - solid</option>
                                        <option value="4"<?php if ((string) $answerValue === '4'): ?> selected="selected"<?php endif; ?>>4 - strong</option>
                                        <option value="5"<?php if ((string) $answerValue === '5'): ?> selected="selected"<?php endif; ?>>5 - excellent</option>
                                    </select>
                                <?php else: ?>
                                    <textarea name="answers[<?php $this->_($question['key']); ?>]" rows="4"><?php $this->_($answerValue); ?></textarea>
                                <?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                        <label>
                            Overall recommendation
                            <select name="overallRecommendation">
                                <?php $recommendation = !empty($this->candidate['scorecard']) ? $this->candidate['scorecard']['overall_recommendation'] : ''; ?>
                                <option value="advance"<?php if ($recommendation === 'advance'): ?> selected="selected"<?php endif; ?>>Advance</option>
                                <option value="hold"<?php if ($recommendation === 'hold'): ?> selected="selected"<?php endif; ?>>Hold</option>
                                <option value="not_selected"<?php if ($recommendation === 'not_selected'): ?> selected="selected"<?php endif; ?>>Not selected</option>
                                <option value="needs_craig_review"<?php if ($recommendation === 'needs_craig_review'): ?> selected="selected"<?php endif; ?>>Needs Craig review</option>
                            </select>
                        </label>
                        <div class="nesp-button-row">
                            <button type="submit" name="scorecardAction" value="saveDraft" class="nesp-secondary-button">Save Draft</button>
                            <button type="submit" name="scorecardAction" value="submit" class="nesp-primary-button">Submit Scorecard</button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="nesp-empty">This assignment does not allow scorecard submission.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php TemplateUtility::printFooter(); ?>
