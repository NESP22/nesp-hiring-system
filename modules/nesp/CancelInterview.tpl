<?php TemplateUtility::printHeader('Cancel Interview', array('modules/nesp/nespWorkflow.css')); ?>
<?php TemplateUtility::printHeaderBlock(); ?>
<?php TemplateUtility::printTabs($this->active, $this->subActive); ?>
    <div id="main" class="nesp-workflow">
        <?php TemplateUtility::printQuickSearch(); ?>
        <div id="contents">
            <div class="nesp-page-title">
                <h2>Cancel Interview</h2>
                <p>Cancel the dashboard record and keep the history. Zoom itself must be cancelled manually.</p>
            </div>
            <div class="nesp-safety-banner">
                This will not cancel the Zoom meeting for you and will not send a cancellation notice automatically.
            </div>

            <div class="nesp-panel">
                <h3>Interview</h3>
                <dl class="nesp-detail-list">
                    <dt>Candidate</dt>
                    <dd><?php $this->_($this->interview['candidate_name']); ?></dd>
                    <dt>Role</dt>
                    <dd><?php $this->_($this->interview['role_title']); ?></dd>
                    <dt>Interviewer</dt>
                    <dd><?php $this->_($this->interview['interviewer_name']); ?></dd>
                    <dt>Scheduled</dt>
                    <dd><?php $this->_(date('M j, Y g:i A', strtotime($this->interview['scheduled_start'])) . ' ' . $this->interview['timezone']); ?></dd>
                    <dt>Zoom link</dt>
                    <dd><?php $this->_($this->interview['zoom_join_url_masked']); ?></dd>
                </dl>
            </div>

            <div class="nesp-panel">
                <h3>Confirm Cancellation</h3>
                <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=confirmCancelInterview" class="nesp-form">
                    <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                    <input type="hidden" name="interviewID" value="<?php echo((int) $this->interview['interview_id']); ?>" />
                    <label>
                        Reason or next step
                        <textarea name="cancelReason" rows="4"></textarea>
                    </label>
                    <div class="nesp-confirm-box">After saving, cancel or delete the actual meeting in Zoom manually.</div>
                    <div class="nesp-button-row">
                        <button type="submit" class="nesp-primary-button">Mark Cancelled</button>
                        <a class="nesp-secondary-action" href="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=interviews">Keep Interview</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php TemplateUtility::printFooter(); ?>
