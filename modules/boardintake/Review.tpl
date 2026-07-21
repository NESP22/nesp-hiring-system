<?php TemplateUtility::printHeader('Board Applicant Intake', array()); ?>
<?php TemplateUtility::printHeaderBlock(); ?>
<?php TemplateUtility::printTabs($this->active); ?>
<div id="main">
    <?php TemplateUtility::printQuickSearch(); ?>
    <div id="contents">
        <h2>Bring Board Applicants Into NESP</h2>
        <p>Use this page for Indeed, LinkedIn, MassHire, and other board exports. Nothing is public and no applicant is contacted from this page.</p>
        <div class="noticeBox">
            <strong>Three simple steps:</strong>
            <ol>
                <li><strong>Upload:</strong> export applicants from the job board, then upload or paste the CSV below.</li>
                <li><strong>Review:</strong> open the new batch, check the rows, record the preview, and approve only the correct people.</li>
                <li><strong>Import:</strong> import approved rows. Each person is added once to OpenCATS and appears in <strong>Needs Craig</strong>.</li>
            </ol>
            <p><strong>Resume:</strong> after import, attach a downloaded resume here. Board links are not fetched automatically.</p>
        </div>
        <?php if (isset($this->errorMessage)): ?><p class="warning"><?php $this->_($this->errorMessage); ?></p><?php endif; ?>
        <?php if (!empty($this->resumeUploaded)): ?><p>Resume attached to the confirmed candidate.</p><?php endif; ?>

        <h3>1. Upload applicants from one job board</h3>
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
            <label>CSV file <input type="file" name="csv" accept=".csv,text/csv" /></label>
            <label>Or paste CSV text
                <textarea name="csvText" rows="4" cols="80" placeholder="external_id,first_name,last_name,email"><?php if (isset($_POST['csvText'])) $this->_($_POST['csvText']); ?></textarea>
            </label>
            <p>Required columns: external_id, first_name, last_name, email. LinkedIn rows may omit email when external_id is present. Optional: phone. Resume and attachment URLs are not accepted.</p>
            <button type="submit">Continue to Review</button>
        </form>

        <?php if (count($this->batches)): ?>
        <h3>2. Open a batch to review</h3>
        <ul>
            <?php foreach ($this->batches as $openBatch): ?>
                <li><a href="<?php echo CATSUtility::getIndexName(); ?>?m=boardintake&amp;batchID=<?php echo (int) $openBatch['batch_id']; ?>">Batch <?php echo (int) $openBatch['batch_id']; ?> - <?php $this->_($openBatch['platform_key']); ?> - <?php echo (int) $openBatch['row_count']; ?> rows</a></li>
            <?php endforeach; ?>
        </ul>
        <form action="<?php echo CATSUtility::getIndexName(); ?>?m=boardintake&amp;a=importAllApproved" method="post" onsubmit="return confirm('Import every already approved, valid, non-duplicate review batch? No applicant will be contacted.');">
            <input type="hidden" name="csrfToken" value="<?php echo Template::escapeAttr($_SESSION['CATS']->getCSRFToken()); ?>" />
            <p>Use this only after you have reviewed and approved the rows in every batch listed above. Invalid, unapproved, and duplicate rows are skipped.</p>
            <button type="submit">Import All Reviewed Applicants</button>
        </form>
        <?php endif; ?>

        <?php if (count($this->importedBatches)): ?>
        <h3>Recent imported batches</h3>
        <ul>
            <?php foreach ($this->importedBatches as $importedBatch): ?>
                <li><a href="<?php echo CATSUtility::getIndexName(); ?>?m=boardintake&amp;batchID=<?php echo (int) $importedBatch['batch_id']; ?>">Batch <?php echo (int) $importedBatch['batch_id']; ?> - <?php $this->_($importedBatch['platform_key']); ?> - <?php echo (int) $importedBatch['imported_count']; ?> imported</a></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <?php if (!empty($this->bulkImportSummary)): ?>
        <h3>Bulk import result</h3>
        <p><?php echo (int) $this->bulkImportSummary['imported']; ?> imported, <?php echo (int) $this->bulkImportSummary['skipped']; ?> skipped, <?php echo (int) $this->bulkImportSummary['failed']; ?> stopped.</p>
        <ul>
            <?php foreach ($this->bulkImportSummary['batches'] as $result): ?>
                <li>Batch <?php echo (int) $result['batch_id']; ?>: <?php $this->_($result['status']); ?> - <?php $this->_($result['message']); ?></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <?php if (!empty($this->batch)): ?>
            <h3>3. <?php echo $this->batch['status_key'] === 'imported' ? 'Imported applicants' : 'Review these applicants'; ?></h3>
        <?php if ($this->batch['status_key'] === 'review' && empty($this->batch['previewed_at'])): ?>
            <p>Review the rows below, then record that you completed the preview. Approval stays locked until the server records that step.</p>
            <form action="<?php echo CATSUtility::getIndexName(); ?>?m=boardintake&amp;a=recordPreview" method="post">
                <input type="hidden" name="csrfToken" value="<?php echo Template::escapeAttr($_SESSION['CATS']->getCSRFToken()); ?>" />
                <input type="hidden" name="batchID" value="<?php echo (int) $this->batch['batch_id']; ?>" />
                <button type="submit">Record Preview Complete</button>
            </form>
        <?php elseif ($this->batch['status_key'] === 'review'): ?>
            <p>Preview recorded. Only valid, keyed rows with duplicate status <strong>none</strong> may be approved.</p>
        <?php endif; ?>
        <?php if ($this->batch['status_key'] === 'review'): ?>
        <form action="<?php echo CATSUtility::getIndexName(); ?>?m=boardintake&amp;a=approve" method="post">
            <input type="hidden" name="csrfToken" value="<?php echo Template::escapeAttr($_SESSION['CATS']->getCSRFToken()); ?>" />
            <input type="hidden" name="batchID" value="<?php echo (int) $this->batch['batch_id']; ?>" />
        <?php endif; ?>
            <table class="searchTable" width="100%">
                <tr><th>Approve</th><th>Row</th><th>Name</th><th>Email</th><th>External ID</th><th>Validation</th><th>Duplicate</th><th>Status</th></tr>
                <?php foreach ($this->rows as $row): ?>
                <tr>
                    <td><?php if ($this->batch['status_key'] === 'review' && !empty($this->batch['previewed_at']) && $row['validation_status'] === 'valid' && $row['duplicate_status'] === 'none' && !empty($row['external_id'])): ?><input type="checkbox" name="approvedRows[]" value="<?php echo (int) $row['intake_row_id']; ?>" <?php if ($row['review_status'] === 'approved'): ?>checked<?php endif; ?> /><?php endif; ?></td>
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
        <?php if ($this->batch['status_key'] === 'review'): ?>
            <button type="submit">Save Explicit Row Approvals</button>
        </form>
        <?php endif; ?>

        <?php if ($this->batch['status_key'] === 'review'): ?>
            <h3>4. Import approved applicants</h3>
            <p>This is the only action that creates candidates and attaches them to job order <?php echo (int) $this->batch['joborder_id']; ?>. The server requires a recorded preview and approval, keyed external IDs, and a transaction. Imported staging PII is redacted after success.</p>
            <form action="<?php echo CATSUtility::getIndexName(); ?>?m=boardintake&amp;a=importApproved" method="post" onsubmit="return confirm('Import only the approved rows and attach each candidate once?');">
                <input type="hidden" name="csrfToken" value="<?php echo Template::escapeAttr($_SESSION['CATS']->getCSRFToken()); ?>" />
                <input type="hidden" name="batchID" value="<?php echo (int) $this->batch['batch_id']; ?>" />
            <button type="submit">Import Approved Applicants to Needs Craig</button>
            </form>
        <?php elseif ($this->batch['status_key'] === 'imported'): ?>
            <h3>5. Attach a downloaded resume</h3>
            <p>The server reconfirms the imported board identity, candidate, and job order <?php echo (int) $this->batch['joborder_id']; ?> before attaching a resume. Candidate contact fields are not changed.</p>
            <?php foreach ($this->rows as $row): ?>
                <?php if ($row['review_status'] === 'imported' && (int) $row['candidate_id'] > 0): ?>
                <form action="<?php echo CATSUtility::getIndexName(); ?>?m=boardintake&amp;a=uploadResume" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrfToken" value="<?php echo Template::escapeAttr($_SESSION['CATS']->getCSRFToken()); ?>" />
                    <input type="hidden" name="batchID" value="<?php echo (int) $this->batch['batch_id']; ?>" />
                    <input type="hidden" name="intakeRowID" value="<?php echo (int) $row['intake_row_id']; ?>" />
                    <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo (int) BoardApplicantIntake::MAX_RESUME_BYTES; ?>" />
                    <label>Candidate <?php echo (int) $row['candidate_id']; ?> resume
                        <input type="file" name="resume" accept=".pdf,.doc,.docx,.rtf,.odt" required />
                    </label>
                    <button type="submit">Attach Resume</button>
                </form>
                <?php endif; ?>
            <?php endforeach; ?>
            <p>Local PDF, DOC, DOCX, RTF, or ODT files only, up to 10 MB. Resume URLs and board retrieval are not supported.</p>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php TemplateUtility::printFooter(); ?>
