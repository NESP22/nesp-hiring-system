<?php TemplateUtility::printHeader('Confirm Phone Screen', array('modules/nesp/nespWorkflow.css')); ?>
<?php TemplateUtility::printHeaderBlock(); ?>
<?php TemplateUtility::printTabs($this->active, $this->subActive); ?>
    <div id="main" class="nesp-workflow">
        <?php TemplateUtility::printQuickSearch(); ?>
        <div id="contents">
            <div class="nesp-page-title">
                <h2>Invite to Schedule Phone Screen</h2>
                <p>Craig/admin confirmation creates a secure scheduling link. It does not place a call.</p>
            </div>

            <div class="nesp-safety-banner">
                The candidate chooses an available Eastern Time appointment. Vapi can call only from the hosted scheduler at the selected time.
            </div>

            <div class="nesp-two-column">
                <div class="nesp-panel">
                        <h3>Scheduling Details</h3>
                    <dl class="nesp-detail-list">
                        <dt>Candidate</dt>
                        <dd><?php $this->_($this->preview['candidate_name']); ?></dd>
                        <dt>Role</dt>
                        <dd><?php $this->_($this->preview['title']); ?></dd>
                        <dt>Destination phone</dt>
                        <dd><?php $this->_($this->preview['destination_phone_redacted']); ?></dd>
                        <dt>Caller label</dt>
                        <dd>NESP Hiring</dd>
                        <dt>Assistant</dt>
                        <dd>NESP Hiring Phone Screen</dd>
                        <dt>Audio recording</dt>
                        <dd>Off</dd>
                        <dt>Transcription</dt>
                        <dd>After consent only</dd>
                        <dt>Expected length</dt>
                        <dd>Approximately 7–10 minutes</dd>
                    </dl>

                    <?php if ($this->preview['has_destination_phone']): ?>
                    <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=requestPhoneScreen">
                        <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                        <input type="hidden" name="candidateID" value="<?php echo((int) $this->preview['candidate_id']); ?>" />
                        <input type="hidden" name="jobOrderID" value="<?php echo((int) $this->preview['joborder_id']); ?>" />
                        <button type="submit" class="nesp-primary-button">Generate Scheduling Link</button>
                        <a class="nesp-secondary-action" href="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=phoneScreens">Cancel</a>
                    </form>
                    <?php else: ?>
                        <div class="nesp-empty">No candidate phone number is available.</div>
                    <?php endif; ?>
                </div>

                <div class="nesp-panel">
                    <h3>Consent Notice</h3>
                    <pre class="nesp-script-preview"><?php $this->_($this->preview['consent_notice']); ?></pre>
                    <h3>Role Questions</h3>
                    <ol class="nesp-list">
                        <?php foreach ($this->preview['role_script']['questions'] as $question): ?>
                            <li><?php $this->_($question); ?></li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            </div>
        </div>
    </div>
<?php TemplateUtility::printFooter(); ?>
