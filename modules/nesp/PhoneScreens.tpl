<?php TemplateUtility::printHeader('NESP Phone Screens', array('modules/nesp/nespWorkflow.css')); ?>
<?php TemplateUtility::printHeaderBlock(); ?>
<?php TemplateUtility::printTabs($this->active, $this->subActive); ?>
    <div id="main" class="nesp-workflow">
        <?php TemplateUtility::printQuickSearch(); ?>
        <div id="contents">
            <div class="nesp-page-title">
                <h2>Vapi Phone Screens</h2>
                <p>Craig/admin-only review of phone-screen requests, call state, consent, and structured results.</p>
            </div>

            <div class="nesp-safety-banner">
                Phone screens never rank, reject, approve, hire, email, text, or move candidates automatically.
            </div>

            <div class="nesp-dashboard-nav">
                <?php foreach ($this->dashboardNavigation as $navItem): ?>
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

            <div class="nesp-panel">
                <h3>Configuration Status</h3>
                <div class="nesp-card-grid nesp-card-grid-tight">
                    <div class="nesp-card"><span class="nesp-card-label">Vapi API</span><strong><?php echo($this->vapiConfiguration['api_configured'] ? 'Yes' : 'No'); ?></strong></div>
                    <div class="nesp-card"><span class="nesp-card-label">Hiring Phone</span><strong><?php echo($this->vapiConfiguration['hiring_phone_configured'] ? 'Yes' : 'No'); ?></strong></div>
                    <div class="nesp-card"><span class="nesp-card-label">Hiring Assistant</span><strong><?php echo($this->vapiConfiguration['hiring_assistant_configured'] ? 'Yes' : 'No'); ?></strong></div>
                    <div class="nesp-card"><span class="nesp-card-label">Webhook Secret</span><strong><?php echo($this->vapiConfiguration['webhook_secret_configured'] ? 'Yes' : 'No'); ?></strong></div>
                    <div class="nesp-card"><span class="nesp-card-label">Recording Off</span><strong><?php echo($this->vapiConfiguration['recording_disabled'] ? 'Yes' : 'No'); ?></strong></div>
                    <div class="nesp-card"><span class="nesp-card-label">Feature Enabled</span><strong><?php echo($this->vapiConfiguration['feature_enabled'] ? 'Yes' : 'No'); ?></strong></div>
                </div>
            </div>

            <div class="nesp-panel">
                <h3>Recent Phone Screens</h3>
                <table class="nesp-table">
                    <tr>
                        <th>Candidate</th>
                        <th>Role</th>
                        <th>Destination</th>
                        <th>Status</th>
                        <th>Consent</th>
                        <th>Last Change</th>
                        <th>Action</th>
                    </tr>
                    <?php foreach ($this->phoneScreens as $screen): ?>
                    <tr>
                        <td><?php $this->_($screen['candidate_name']); ?></td>
                        <td><?php $this->_($screen['role_title']); ?></td>
                        <td><?php $this->_($screen['destination_phone_last4'] === '' ? 'Not stored' : '***-***-' . $screen['destination_phone_last4']); ?></td>
                        <td><?php $this->_($screen['status_key']); ?></td>
                        <td><?php $this->_($screen['consent_status']); ?></td>
                        <td><?php $this->_($screen['date_modified']); ?></td>
                        <td><a class="nesp-secondary-action" href="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=reviewPhoneScreen&amp;phoneScreenID=<?php echo((int) $screen['vapi_phone_screen_id']); ?>">Review</a></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!count($this->phoneScreens)): ?>
                    <tr>
                        <td colspan="7">No phone-screen requests have been prepared.</td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
<?php TemplateUtility::printFooter(); ?>
