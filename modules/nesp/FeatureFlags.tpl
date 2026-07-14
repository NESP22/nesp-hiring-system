<?php TemplateUtility::printHeader('NESP Feature Flags', array('modules/nesp/nespWorkflow.css')); ?>
<?php TemplateUtility::printHeaderBlock(); ?>
<?php TemplateUtility::printTabs($this->active, $this->subActive); ?>
    <div id="main" class="nesp-workflow">
        <?php TemplateUtility::printQuickSearch(); ?>
        <div id="contents">
            <div class="nesp-page-title">
                <h2>NESP Feature Flags</h2>
                <p>Phase 2 feature state is disabled by default until Craig approves later activation work.</p>
            </div>

            <table class="nesp-table">
                <tr>
                    <th>Feature</th>
                    <th>Status</th>
                    <th>Approval</th>
                    <th>Purpose</th>
                </tr>
                <?php foreach ($this->featureFlags as $flag): ?>
                <tr>
                    <td><?php $this->_($flag['display_name']); ?></td>
                    <td>
                        <?php if ((int) $flag['is_enabled'] === 1): ?>
                            <span class="nesp-status nesp-status-on">Enabled</span>
                        <?php else: ?>
                            <span class="nesp-status nesp-status-off">Disabled</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo ((int) $flag['requires_admin_approval'] === 1) ? 'Craig approval required' : 'Standard'; ?></td>
                    <td><?php $this->_($flag['description']); ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
<?php TemplateUtility::printFooter(); ?>
