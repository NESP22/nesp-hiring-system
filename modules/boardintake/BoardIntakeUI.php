<?php

include_once(LEGACY_ROOT . '/lib/BoardApplicantIntake.php');
include_once(LEGACY_ROOT . '/lib/CommonErrors.php');

class BoardIntakeUI extends UserInterface
{
    private $_intake;

    public function __construct()
    {
        parent::__construct();
        $this->_authenticationRequired = true;
        $this->_moduleDirectory = 'boardintake';
        $this->_moduleName = 'boardintake';
        $this->_moduleTabText = 'Board Applicant Intake*al=' . ACCESS_LEVEL_SA;
        $this->_subTabs = array(
            'Review Intake' => CATSUtility::getIndexName() . '?m=boardintake*al=' . ACCESS_LEVEL_SA
        );
        $this->_intake = new BoardApplicantIntake();
        $this->_schema = array(
            1 => '
                CREATE TABLE IF NOT EXISTS nesp_board_intake_batch (
                    batch_id INT(11) NOT NULL AUTO_INCREMENT,
                    platform_key VARCHAR(32) NOT NULL,
                    joborder_id INT(11) NOT NULL,
                    source_label VARCHAR(128) NOT NULL,
                    source_checksum CHAR(64) NOT NULL,
                    status_key VARCHAR(32) NOT NULL DEFAULT "review",
                    row_count INT(11) NOT NULL DEFAULT 0,
                    imported_count INT(11) NOT NULL DEFAULT 0,
                    created_by_user_id INT(11) NOT NULL,
                    previewed_at DATETIME NULL,
                    previewed_by_user_id INT(11) NULL,
                    approved_at DATETIME NULL,
                    approved_by_user_id INT(11) NULL,
                    expires_at DATETIME NOT NULL,
                    date_created DATETIME NOT NULL DEFAULT "1000-01-01 00:00:00",
                    date_modified DATETIME NOT NULL DEFAULT "1000-01-01 00:00:00",
                    PRIMARY KEY (batch_id),
                    KEY IDX_board_intake_status (status_key),
                    KEY IDX_board_intake_source (platform_key, source_checksum)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                CREATE TABLE IF NOT EXISTS nesp_board_intake_row (
                    intake_row_id INT(11) NOT NULL AUTO_INCREMENT,
                    batch_id INT(11) NOT NULL,
                    platform_key VARCHAR(32) NOT NULL,
                    source_row_number INT(11) NOT NULL,
                    external_id VARCHAR(255) NULL,
                    first_name VARCHAR(128) NOT NULL,
                    last_name VARCHAR(128) NOT NULL,
                    email VARCHAR(255) NOT NULL,
                    phone VARCHAR(64) NOT NULL DEFAULT "",
                    row_hash CHAR(64) NOT NULL,
                    idempotency_key VARCHAR(320) NULL,
                    validation_status VARCHAR(32) NOT NULL DEFAULT "valid",
                    validation_message TEXT,
                    duplicate_status VARCHAR(32) NOT NULL DEFAULT "unchecked",
                    duplicate_candidate_id INT(11) NULL,
                    review_status VARCHAR(32) NOT NULL DEFAULT "pending",
                    candidate_id INT(11) NULL,
                    pii_redacted_at DATETIME NULL,
                    date_created DATETIME NOT NULL DEFAULT "1000-01-01 00:00:00",
                    date_modified DATETIME NOT NULL DEFAULT "1000-01-01 00:00:00",
                    PRIMARY KEY (intake_row_id),
                    UNIQUE KEY IDX_board_intake_row_hash (batch_id, row_hash),
                    KEY IDX_board_intake_external (platform_key, idempotency_key),
                    KEY IDX_board_intake_batch (batch_id),
                    KEY IDX_board_intake_duplicate (duplicate_status),
                    KEY IDX_board_intake_review (review_status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                CREATE TABLE IF NOT EXISTS nesp_board_intake_identity (
                    identity_id INT(11) NOT NULL AUTO_INCREMENT,
                    platform_key VARCHAR(32) NOT NULL,
                    external_id VARCHAR(255) NOT NULL,
                    intake_row_id INT(11) NOT NULL,
                    candidate_id INT(11) NULL,
                    date_created DATETIME NOT NULL DEFAULT "1000-01-01 00:00:00",
                    PRIMARY KEY (identity_id),
                    UNIQUE KEY IDX_board_intake_identity (platform_key, external_id),
                    KEY IDX_board_intake_identity_candidate (candidate_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            '
        );
    }

    public function handleRequest()
    {
        $this->adminOnly();
        $action = $this->getAction();
        switch ($action)
        {
            case 'upload':
                $this->requirePostCSRF();
                $this->upload();
                break;
            case 'approve':
                $this->requirePostCSRF();
                $this->approve();
                break;
            case 'recordPreview':
                $this->requirePostCSRF();
                $this->recordPreview();
                break;
            case 'importApproved':
                $this->requirePostCSRF();
                $this->importApproved();
                break;
            case 'importAllApproved':
                $this->requirePostCSRF();
                $this->importAllApproved();
                break;
            default:
                $this->review();
                break;
        }
    }

    private function review()
    {
        $batchID = isset($_GET['batchID']) ? (int) $_GET['batchID'] : 0;
        $batch = $batchID ? $this->_intake->getBatch($batchID) : array();
        $rows = $batch ? $this->_intake->getRows($batchID) : array();
        $this->assignView($batch, $rows);
    }

    private function upload()
    {
        $platform = isset($_POST['platform']) ? strtolower(trim($_POST['platform'])) : '';
        $jobOrderID = isset($_POST['jobOrderID']) ? (int) $_POST['jobOrderID'] : 0;
        $sourceLabel = isset($_POST['sourceLabel']) ? trim($_POST['sourceLabel']) : '';
        $contents = '';
        if (isset($_FILES['csv']) && $_FILES['csv']['error'] === UPLOAD_ERR_OK)
        {
            if ($_FILES['csv']['size'] > BoardApplicantIntake::MAX_CSV_BYTES)
            {
                $this->showError('CSV exceeds the 2 MB review limit.');
                return;
            }
            $contents = file_get_contents($_FILES['csv']['tmp_name']);
        }
        else if (isset($_POST['csvText']) && trim($_POST['csvText']) !== '')
        {
            $contents = trim($_POST['csvText']);
        }
        else
        {
            $this->showError('Choose a readable CSV file or paste CSV text.');
            return;
        }

        $parsed = BoardApplicantIntake::parseCsv($contents, $platform, $jobOrderID, $sourceLabel);
        if ($parsed['errors'])
        {
            $this->showError(implode(' ', $parsed['errors']));
            return;
        }

        $batchID = $this->_intake->createBatch(
            $this->_userID,
            $platform,
            $jobOrderID,
            BoardApplicantIntake::canonicalSourceLabel($platform, $sourceLabel),
            $parsed['rows'],
            hash('sha256', $contents)
        );
        if ($batchID <= 0)
        {
            $this->showError('The review batch could not be created.');
            return;
        }
        $this->_intake->applyDuplicateChecks($batchID);
        CATSUtility::transferRelativeURI('m=boardintake&batchID=' . $batchID);
    }

    private function approve()
    {
        $batchID = (int) $_POST['batchID'];
        try
        {
            $this->_intake->approveRows($batchID, isset($_POST['approvedRows']) ? $_POST['approvedRows'] : array(), $this->_userID);
        }
        catch (Throwable $e)
        {
            $this->showError('Approval stopped safely: ' . $e->getMessage());
            return;
        }
        CATSUtility::transferRelativeURI('m=boardintake&batchID=' . $batchID);
    }

    private function recordPreview()
    {
        $batchID = (int) $_POST['batchID'];
        try
        {
            $this->_intake->recordPreview($batchID, $this->_userID);
        }
        catch (Throwable $e)
        {
            $this->showError('Preview stopped safely: ' . $e->getMessage());
            return;
        }
        CATSUtility::transferRelativeURI('m=boardintake&batchID=' . $batchID);
    }

    private function importApproved()
    {
        $batchID = (int) $_POST['batchID'];
        try
        {
            $this->_intake->importApprovedRows($this->_userID, $batchID);
        }
        catch (Throwable $e)
        {
            $this->showError('Import stopped safely: ' . $e->getMessage());
            return;
        }
        CATSUtility::transferRelativeURI('m=boardintake&batchID=' . $batchID);
    }

    private function importAllApproved()
    {
        try
        {
            $summary = $this->_intake->importAllApprovedRows($this->_userID);
        }
        catch (Throwable $e)
        {
            $this->showError('Bulk import stopped safely: ' . $e->getMessage());
            return;
        }

        $this->assignView(array(), array(), $summary);
    }

    private function assignView($batch, $rows, $bulkImportSummary = null)
    {
        $this->_template->assign('active', $this);
        $this->_template->assign('batch', $batch);
        $this->_template->assign('rows', $rows);
        $this->_template->assign('batches', $this->_intake->getOpenBatches());
        $this->_template->assign('platforms', BoardApplicantIntake::allowedPlatforms());
        $this->_template->assign('jobOrders', BoardApplicantIntake::allowedJobOrders());
        $this->_template->assign('bulkImportSummary', $bulkImportSummary);
        $this->_template->display('./modules/boardintake/Review.tpl');
    }

    private function showError($message)
    {
        $this->_template->assign('active', $this);
        $this->_template->assign('errorMessage', $message);
        $this->_template->assign('batch', array());
        $this->_template->assign('rows', array());
        $this->_template->assign('batches', $this->_intake->getOpenBatches());
        $this->_template->assign('platforms', BoardApplicantIntake::allowedPlatforms());
        $this->_template->assign('jobOrders', BoardApplicantIntake::allowedJobOrders());
        $this->_template->display('./modules/boardintake/Review.tpl');
    }

    private function adminOnly()
    {
        if ($this->getUserAccessLevel('settings.administration') < ACCESS_LEVEL_SA)
        {
            CommonErrors::fatal(COMMONERROR_PERMISSION, $this, 'Administrator access is required.');
        }
    }

    private function requirePostCSRF()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        {
            CommonErrors::fatal(COMMONERROR_BADFIELDS, $this, 'Invalid request method.');
        }
        $token = isset($_POST['csrfToken']) ? $_POST['csrfToken'] : null;
        if (!isset($_SESSION['CATS']) || !$_SESSION['CATS']->isCSRFTokenValid($token))
        {
            CommonErrors::fatal(COMMONERROR_PERMISSION, $this, 'Invalid request token.');
        }
    }
}
