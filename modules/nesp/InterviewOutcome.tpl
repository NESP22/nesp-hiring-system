<?php TemplateUtility::printHeader('Interview Outcome', array('modules/nesp/nespWorkflow.css')); ?>
<?php TemplateUtility::printHeaderBlock(); ?>
<?php TemplateUtility::printTabs($this->active, $this->subActive); ?>
    <div id="main" class="nesp-workflow">
        <?php TemplateUtility::printQuickSearch(); ?>
        <div id="contents">
            <div class="nesp-page-title">
                <h2>Interview Tracking</h2>
                <p>Review the invitation preview, reschedule or cancel if needed, and record the human outcome.</p>
            </div>

            <div class="nesp-two-column">
                <div class="nesp-panel">
                    <h3>Current Interview</h3>
                    <dl class="nesp-detail-list">
                        <dt>Candidate</dt>
                        <dd><?php $this->_($this->interview['candidate_name']); ?></dd>
                        <dt>Role</dt>
                        <dd><?php $this->_($this->interview['role_title']); ?></dd>
                        <dt>Interviewer</dt>
                        <dd><?php $this->_($this->interview['interviewer_name']); ?></dd>
                        <dt>Status</dt>
                        <dd><?php $this->_($this->interview['status_label']); ?></dd>
                        <dt>Scheduled</dt>
                        <dd><?php $this->_(empty($this->interview['scheduled_start']) ? 'Not scheduled' : date('M j, Y g:i A', strtotime($this->interview['scheduled_start'])) . ' ' . $this->interview['timezone']); ?></dd>
                        <dt>Zoom link</dt>
                        <dd><?php $this->_($this->interview['zoom_join_url_masked']); ?></dd>
                        <dt>Interview link tracking</dt>
                        <dd><?php $this->_($this->interview['participant_link_tracking_label']); ?></dd>
                    </dl>
                    <div class="nesp-button-row">
                        <a class="nesp-secondary-action" href="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=scheduleInterview&amp;interviewID=<?php echo((int) $this->interview['interview_id']); ?>">Reschedule</a>
                        <a class="nesp-secondary-action" href="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=cancelInterview&amp;interviewID=<?php echo((int) $this->interview['interview_id']); ?>">Cancel</a>
                    </div>
                </div>

                <div class="nesp-panel">
                    <h3>Invitation Preview</h3>
                    <textarea class="nesp-copy-box" rows="11" readonly><?php echo(htmlspecialchars($this->invitationPreview, ENT_QUOTES, 'UTF-8')); ?></textarea>
                    <p class="nesp-help-text">No invitation has been sent from this screen. Review and copy manually only after Craig approves.</p>
                    <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=regenerateTrackedInterviewLink" class="nesp-inline-form">
                        <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                        <input type="hidden" name="interviewID" value="<?php echo((int) $this->interview['interview_id']); ?>" />
                        <button type="submit" class="nesp-secondary-button">Generate New Tracked Interview Link</button>
                    </form>
                    <p class="nesp-help-text">This replaces the invitation preview with a new NESP tracking link. It does not send anything; the older tracked link stops working.</p>
                    <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=markManualInterviewInvitationSent" class="nesp-inline-form">
                        <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                        <input type="hidden" name="interviewID" value="<?php echo((int) $this->interview['interview_id']); ?>" />
                        <button type="submit" class="nesp-secondary-button">Mark Invitation Sent Manually</button>
                    </form>
                </div>
            </div>

            <div class="nesp-panel">
                <h3>Record Outcome</h3>
                <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=saveInterviewOutcome" class="nesp-form nesp-form-wide">
                    <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                    <input type="hidden" name="interviewID" value="<?php echo((int) $this->interview['interview_id']); ?>" />
                    <label>
                        Outcome
                        <select name="outcomeKey" required>
                            <option value="">Choose outcome</option>
                            <?php foreach ($this->outcomeLabels as $key => $label): ?>
                                <option value="<?php echo(htmlspecialchars($key, ENT_QUOTES, 'UTF-8')); ?>"><?php $this->_($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        Human notes
                        <textarea name="outcomeNotes" rows="5"></textarea>
                    </label>
                    <div class="nesp-confirm-box">This records a human outcome only. It does not rank, reject, hire, email, or text anyone.</div>
                    <button type="submit" class="nesp-primary-button">Save Human Outcome</button>
                </form>
            </div>
        </div>
    </div>
<?php TemplateUtility::printFooter(); ?>
