<?php TemplateUtility::printHeader('Review Phone Screen', array('modules/nesp/nespWorkflow.css')); ?>
<?php TemplateUtility::printHeaderBlock(); ?>
<?php TemplateUtility::printTabs($this->active, $this->subActive); ?>
    <div id="main" class="nesp-workflow">
        <?php TemplateUtility::printQuickSearch(); ?>
        <div id="contents">
            <div class="nesp-page-title">
                <h2>Review Phone Screen</h2>
                <p>Human review only. No phone-screen result changes candidate status automatically.</p>
            </div>

            <div class="nesp-safety-banner">
                Audio recording must remain off. Transcript content is retained only after affirmative consent.
            </div>

            <div class="nesp-two-column">
                <div class="nesp-panel">
                    <h3>Call Status</h3>
                    <dl class="nesp-detail-list">
                        <dt>Candidate</dt>
                        <dd><a href="<?php echo(CATSUtility::getIndexName()); ?>?m=candidates&amp;a=show&amp;candidateID=<?php echo((int) $this->screen['candidate_id']); ?>"><?php $this->_($this->screen['candidate_name']); ?></a></dd>
                        <dt>Role</dt>
                        <dd><a href="<?php echo(CATSUtility::getIndexName()); ?>?m=joborders&amp;a=show&amp;jobOrderID=<?php echo((int) $this->screen['joborder_id']); ?>"><?php $this->_($this->screen['role_title']); ?></a></dd>
                        <dt>Destination</dt>
                        <dd><?php $this->_($this->screen['destination_phone_last4'] === '' ? 'Not stored' : '***-***-' . $this->screen['destination_phone_last4']); ?></dd>
                        <dt>Status</dt>
                        <dd><?php $this->_($this->screen['status_key']); ?></dd>
                        <dt>Consent</dt>
                        <dd><?php $this->_($this->screen['consent_status']); ?></dd>
                        <dt>Caller label</dt>
                        <dd><?php $this->_($this->screen['caller_label']); ?></dd>
                        <dt>Assistant</dt>
                        <dd><?php $this->_($this->screen['assistant_label']); ?></dd>
                        <dt>Provider end reason</dt>
                        <dd><?php $this->_($this->screen['provider_end_reason']); ?></dd>
                    </dl>

                    <?php if (in_array($this->screen['status_key'], array('ready_for_call', 'provider_error', 'no_answer', 'cancelled'))): ?>
                    <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=startPhoneScreen" class="nesp-inline-form">
                        <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                        <input type="hidden" name="phoneScreenID" value="<?php echo((int) $this->screen['vapi_phone_screen_id']); ?>" />
                        <button type="submit" class="nesp-primary-button">Start Call</button>
                    </form>
                    <?php endif; ?>

                    <?php if (in_array($this->screen['status_key'], array('ready_for_call', 'call_requested', 'ringing'))): ?>
                    <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=cancelPhoneScreen" class="nesp-inline-form">
                        <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                        <input type="hidden" name="phoneScreenID" value="<?php echo((int) $this->screen['vapi_phone_screen_id']); ?>" />
                        <button type="submit" class="nesp-secondary-button">Cancel Pending Call</button>
                    </form>
                    <?php endif; ?>
                </div>

                <div class="nesp-panel">
                    <h3>Structured Result</h3>
                    <?php if (count($this->screen['structured_result'])): ?>
                    <table class="nesp-table">
                        <?php foreach ($this->screen['structured_result'] as $key => $value): ?>
                        <tr>
                            <th><?php $this->_($key); ?></th>
                            <td><?php $this->_(is_scalar($value) ? (string) $value : json_encode($value)); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                    <?php else: ?>
                        <div class="nesp-empty">No structured result has arrived.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="nesp-panel">
                <h3>Permitted Transcript</h3>
                <?php if (trim($this->screen['transcript_text']) !== ''): ?>
                    <pre class="nesp-script-preview"><?php $this->_($this->screen['transcript_text']); ?></pre>
                <?php else: ?>
                    <div class="nesp-empty">No substantive transcript is stored for this phone screen.</div>
                <?php endif; ?>
            </div>

            <div class="nesp-two-column">
                <div class="nesp-panel">
                    <h3>Craig Review Note</h3>
                    <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=savePhoneScreenReview" class="nesp-form">
                        <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                        <input type="hidden" name="phoneScreenID" value="<?php echo((int) $this->screen['vapi_phone_screen_id']); ?>" />
                        <label>
                            Confirmed human review note
                            <textarea name="reviewNote" rows="4"></textarea>
                        </label>
                        <button type="submit" class="nesp-secondary-button">Save Review Note</button>
                    </form>
                </div>

                <div class="nesp-panel">
                    <h3>Webhook Events</h3>
                    <table class="nesp-table">
                        <tr>
                            <th>Event</th>
                            <th>Received</th>
                        </tr>
                        <?php foreach ($this->screen['webhook_events'] as $event): ?>
                        <tr>
                            <td><?php $this->_($event['event_type']); ?></td>
                            <td><?php $this->_($event['date_created']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (!count($this->screen['webhook_events'])): ?>
                        <tr>
                            <td colspan="2">No webhook events have arrived.</td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
<?php TemplateUtility::printFooter(); ?>
