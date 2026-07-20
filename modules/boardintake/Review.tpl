<?php TemplateUtility::printHeader('Board Applicant Intake', array()); ?>
<?php TemplateUtility::printHeaderBlock(); ?>
<?php TemplateUtility::printTabs($this->active); ?>
<div id="main">
    <?php TemplateUtility::printQuickSearch(); ?>
    <div id="contents">
        <h2>Board Applicant Intake Review</h2>
        <p>Admin-only staging. Nothing is public and no applicant is contacted.</p>
        <?php if (isset($this->errorMessage)): ?><p class="warning"><?php $this->_($this->errorMessage); ?></p><?php endif; ?>

        <h3>1. Upload a review batch</h3>
        <form action="<?php echo CATSUtility::getIndexName(); ?>?m=boardintake&amp;a=upload" method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrfToken" value="<?php echo Template::escapeAttr($_SESSION['CATS']->getCSRFToken()); ?>" />
            <label>Board
                <select name="platform">
                    <?php foreach ($this->platforms as $platform): ?><option value="<?php $this->_($platform); ?>"><?php $this->_(ucfirst($platform)); ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>Job order
                <select name="jobOrderID">
                    <?php foreach ($this->jobOrders as $jobOrderID => $title): ?><option value="<?php echo (int) $jobOrderID; ?>"><?php echo (int) $jobOrderID; ?> - <?php $this->_($title); ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>Source label <input name="sourceLabel" value="NESP Ad: Indeed" required /></label>
            <label>CSV <input type="file" name="csv" accept=".csv,text/csv" required /></label>
            <p>Required columns: external_id, first_name, last_name, email. Optional: phone. Resume and attachment URLs are not accepted.</p>
            <button type="submit">Create Review Batch</button>
        </form>

        <?php if (count($this->batches)): ?>
        <h3>Open review batches</h3>
        <ul>
            <?php foreach ($this->batches as $openBatch): ?>
                <li><a href="<?php echo CATSUtility::getIndexName(); ?>?m=boardintake&amp;batchID=<?php echo (int) $openBatch['batch_id']; ?>">Batch <?php echo (int) $openBatch['batch_id']; ?> - <?php $this->_($openBatch['platform_key']); ?> - <?php echo (int) $openBatch['row_count']; ?> rows</a></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <?php if (!empty($this->batch)): ?>
        <h3>2. Review rows</h3>
        <?php if (empty($this->batch['previewed_at'])): ?>
            <p>Review the rows below, then record that you completed the preview. Approval stays locked until the server records that step.</p>
            <form action="<?php echo CATSUtility::getIndexName(); ?>?m=boardintake&amp;a=recordPreview" method="post">
                <input type="hidden" name="csrfToken" value="<?php echo Template::escapeAttr($_SESSION['CATS']->getCSRFToken()); ?>" />
                <input type="hidden" name="batchID" value="<?php echo (int) $this->batch['batch_id']; ?>" />
                <button type="submit">Record Preview Complete</button>
            </form>
        <?php else: ?>
            <p>Preview recorded. Only valid, keyed rows with duplicate status <strong>none</strong> may be approved.</p>
        <?php endif; ?>
        <form action="<?php echo CATSUtility::getIndexName(); ?>?m=boardintake&amp;a=approve" method="post">
            <input type="hidden" name="csrfToken" value="<?php echo Template::escapeAttr($_SESSION['CATS']->getCSRFToken()); ?>" />
            <input type="hidden" name="batchID" value="<?php echo (int) $this->batch['batch_id']; ?>" />
            <table class="searchTable" width="100%">
                <tr><th>Approve</th><th>Row</th><th>Name</th><th>Email</th><th>External ID</th><th>Validation</th><th>Duplicate</th><th>Status</th></tr>
                <?php foreach ($this->rows as $row): ?>
                <tr>
                    <td><?php if (!empty($this->batch['previewed_at']) && $row['validation_status'] === 'valid' && $row['duplicate_status'] === 'none' && !empty($row['external_id'])): ?><input type="checkbox" name="approvedRows[]" value="<?php echo (int) $row['intake_row_id']; ?>" <?php if ($row['review_status'] === 'approved'): ?>checked<?php endif; ?> /><?php endif; ?></td>
                    <td><?php echo (int) $row['source_row_number']; ?></td>
                    <td><?php $this->_($row['first_name'] . ' ' . $row['last_name']); ?></td>
                    <td><?php $this->_($row['email']); ?></td>
                    <td><?php $this->_($row['external_id'] ?: '-'); ?></td>
                    <td><?php $this->_($row['validation_status']); ?> <?php $this->_($row['validation_message']); ?></td>
                    <td><?php $this->_($row['duplicate_status']); ?></td>
                    <td><?php $this->_($row['review_status']); ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
            <button type="submit">Save Explicit Row Approvals</button>
        </form>

        <h3>3. Import approved rows</h3>
        <p>This is the only action that creates candidates and attaches them to job order <?php echo (int) $this->batch['joborder_id']; ?>. The server requires a recorded preview and approval, keyed external IDs, and a transaction. Imported staging PII is redacted after success.</p>
        <form action="<?php echo CATSUtility::getIndexName(); ?>?m=boardintake&amp;a=importApproved" method="post" onsubmit="return confirm('Import only the approved rows and attach each candidate once?');">
            <input type="hidden" name="csrfToken" value="<?php echo Template::escapeAttr($_SESSION['CATS']->getCSRFToken()); ?>" />
            <input type="hidden" name="batchID" value="<?php echo (int) $this->batch['batch_id']; ?>" />
            <button type="submit">Import Approved Rows</button>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php TemplateUtility::printFooter(); ?>
