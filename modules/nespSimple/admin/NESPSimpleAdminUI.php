<?php

include_once(LEGACY_ROOT . '/lib/CommonErrors.php');
include_once(LEGACY_ROOT . '/modules/nespSimple/admin/NESPSimpleAdminHub.php');

class NESPSimpleAdminUI extends UserInterface
{
    private $_hub;

    public function __construct($hub = null)
    {
        parent::__construct();
        $this->_authenticationRequired = true;
        $this->_moduleDirectory = 'nespSimple/admin';
        $this->_moduleName = 'nespSimple';
        $this->_moduleTabText = 'Hiring Hub*al=' . ACCESS_LEVEL_SA;
        $this->_subTabs = array(
            'All Applicants' => CATSUtility::getIndexName() . '?m=nespSimple*al=' . ACCESS_LEVEL_SA
        );
        $this->_hub = $hub === null ? new NESPSimpleAdminHub() : $hub;
    }

    public function handleRequest()
    {
        $this->adminOnly();
        $action = $this->getAction();

        switch ($action)
        {
            case 'detail':
                $this->detail();
                break;

            case 'assignInterviewer':
                $this->requirePostCSRF();
                $this->assignInterviewer();
                break;

            case 'export':
                $this->export();
                break;

            case '':
            case 'list':
                $this->listing();
                break;

            default:
                CommonErrors::fatal(COMMONERROR_BADINDEX, $this, 'Invalid Hiring Hub action.');
        }
    }

    private function listing()
    {
        $filters = $this->requestFilters();
        $allRows = $this->_hub->getApplicantRows();

        $this->_template->assign('active', $this);
        $this->_template->assign('subActive', 'All Applicants');
        $this->_template->assign('applicants', NESPSimpleAdminHub::filterApplicantRows($allRows, $filters));
        $this->_template->assign('filterOptions', NESPSimpleAdminHub::filterOptions($allRows));
        $this->_template->assign('filters', $filters);
        $this->_template->display('./modules/nespSimple/admin/Hub.tpl');
    }

    private function detail()
    {
        $candidateID = isset($_GET['candidateID']) ? (int) $_GET['candidateID'] : 0;
        $jobOrderID = isset($_GET['jobOrderID']) ? (int) $_GET['jobOrderID'] : 0;
        $detail = $this->_hub->getApplicantDetail($candidateID, $jobOrderID);
        if (empty($detail))
        {
            CommonErrors::fatal(COMMONERROR_BADINDEX, $this, 'Applicant not found.');
        }

        $this->_template->assign('active', $this);
        $this->_template->assign('subActive', 'All Applicants');
        $this->_template->assign('applicant', $detail);
        $this->_template->assign('assignmentSaved', isset($_GET['assigned']) && $_GET['assigned'] === '1');
        $this->_template->assign('assignmentError', isset($_GET['error']) ? trim((string) $_GET['error']) : '');
        $this->_template->display('./modules/nespSimple/admin/Detail.tpl');
    }

    private function assignInterviewer()
    {
        $candidateID = isset($_POST['candidateID']) ? (int) $_POST['candidateID'] : 0;
        $jobOrderID = isset($_POST['jobOrderID']) ? (int) $_POST['jobOrderID'] : 0;
        $interviewerProfileID = isset($_POST['interviewerProfileID']) ? (int) $_POST['interviewerProfileID'] : 0;
        $result = $this->_hub->assignInterviewer(
            $interviewerProfileID,
            $candidateID,
            $jobOrderID,
            $this->_userID
        );

        $uri = 'm=nespSimple&a=detail&candidateID=' . $candidateID . '&jobOrderID=' . $jobOrderID;
        if (!empty($result['ok']))
        {
            $uri .= '&assigned=1';
        }
        else
        {
            $uri .= '&error=' . urlencode(isset($result['error']) ? $result['error'] : 'Assignment failed.');
        }
        CATSUtility::transferRelativeURI($uri);
    }

    private function export()
    {
        $csv = $this->_hub->buildExportCSV();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="nesp-applicants-' . date('Y-m-d') . '.csv"');
        header('X-Content-Type-Options: nosniff');
        echo $csv;
    }

    private function requestFilters()
    {
        return array(
            'search' => isset($_GET['search']) ? trim((string) $_GET['search']) : '',
            'role' => isset($_GET['role']) ? trim((string) $_GET['role']) : '',
            'source' => isset($_GET['source']) ? trim((string) $_GET['source']) : '',
            'status' => isset($_GET['status']) ? trim((string) $_GET['status']) : ''
        );
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
        if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST')
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
