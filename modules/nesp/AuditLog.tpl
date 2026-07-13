<?php TemplateUtility::printHeader('NESP Audit Log', array('modules/nesp/nespWorkflow.css')); ?>
<?php TemplateUtility::printHeaderBlock(); ?>
<?php TemplateUtility::printTabs($this->active, $this->subActive); ?>
    <div id="main" class="nesp-workflow">
        <?php TemplateUtility::printQuickSearch(); ?>
        <div id="contents">
            <div class="nesp-page-title">
                <h2>NESP Audit Log</h2>
                <p>Durable audit events are stored in MariaDB. This read-only view shows the latest workflow events.</p>
            </div>

            <table class="nesp-table">
                <tr>
                    <th>Date</th>
                    <th>Actor</th>
                    <th>Event</th>
                    <th>Entity</th>
                    <th>Metadata</th>
                </tr>
                <?php foreach ($this->auditEvents as $event): ?>
                <tr>
                    <td><?php $this->_($event['date_created']); ?></td>
                    <td><?php $this->_($event['actor_user_id']); ?></td>
                    <td><?php $this->_($event['event_type']); ?></td>
                    <td><?php $this->_($event['entity_type'] . ' ' . $event['entity_id']); ?></td>
                    <td><?php $this->_($event['metadata_json']); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!count($this->auditEvents)): ?>
                <tr>
                    <td colspan="5">No NESP workflow audit events have been recorded yet.</td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
    </div>
<?php TemplateUtility::printFooter(); ?>
