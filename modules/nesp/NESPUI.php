<?php
/*
 * New England Sports Photo hiring workflow module.
 */

include_once(LEGACY_ROOT . '/lib/NESPWorkflow.php');
include_once(LEGACY_ROOT . '/lib/CommonErrors.php');

class NESPUI extends UserInterface
{
    private $_workflow;

    public function __construct()
    {
        parent::__construct();

        $this->_authenticationRequired = true;
        $this->_moduleDirectory = 'nesp';
        $this->_moduleName = 'nesp';
        $this->_moduleTabText = 'NESP Hiring*al=' . ACCESS_LEVEL_READ;
        $this->_subTabs = array(
            'Dashboard' => CATSUtility::getIndexName() . '?m=nesp*al=' . ACCESS_LEVEL_READ,
            'Feature Flags' => CATSUtility::getIndexName() . '?m=nesp&amp;a=featureFlags*al=' . ACCESS_LEVEL_SA,
            'Interviewer Access' => CATSUtility::getIndexName() . '?m=nesp&amp;a=interviewerAccess*al=' . ACCESS_LEVEL_SA,
            'Audit Log' => CATSUtility::getIndexName() . '?m=nesp&amp;a=auditLog*al=' . ACCESS_LEVEL_SA
        );

        $this->_workflow = new NESPWorkflow();
    }

    public function handleRequest()
    {
        $action = $this->getAction();

        if (!$this->_workflow->isSchemaInstalled())
        {
            $this->schemaMissing();
            return;
        }

        switch ($action)
        {
            case 'featureFlags':
                $this->adminOnly();
                $this->featureFlags();
                break;

            case 'interviewerAccess':
                $this->adminOnly();
                $this->interviewerAccess();
                break;

            case 'auditLog':
                $this->adminOnly();
                $this->auditLog();
                break;

            case 'dashboard':
            default:
                $this->dashboard();
                break;
        }
    }

    private function dashboard()
    {
        $this->_template->assign('active', $this);
        $this->_template->assign('subActive', 'Dashboard');
        $this->_template->assign('summary', $this->_workflow->getDashboardSummary());
        $this->_template->assign('integrationStatuses', $this->_workflow->getIntegrationStatuses());
        $this->_template->assign('workflowStages', $this->_workflow->getWorkflowStages());
        $this->_template->display('./modules/nesp/Dashboard.tpl');
    }

    private function featureFlags()
    {
        $this->_template->assign('active', $this);
        $this->_template->assign('subActive', 'Feature Flags');
        $this->_template->assign('featureFlags', $this->_workflow->getFeatureFlags());
        $this->_template->display('./modules/nesp/FeatureFlags.tpl');
    }

    private function interviewerAccess()
    {
        $this->_template->assign('active', $this);
        $this->_template->assign('subActive', 'Interviewer Access');
        $this->_template->assign('summary', $this->_workflow->getInterviewerAccessSummary());
        $this->_template->display('./modules/nesp/InterviewerAccess.tpl');
    }

    private function auditLog()
    {
        $this->_template->assign('active', $this);
        $this->_template->assign('subActive', 'Audit Log');
        $this->_template->assign('auditEvents', $this->_workflow->getRecentAuditEvents(50));
        $this->_template->display('./modules/nesp/AuditLog.tpl');
    }

    private function schemaMissing()
    {
        $this->_template->assign('active', $this);
        $this->_template->assign('subActive', 'Dashboard');
        $this->_template->display('./modules/nesp/SchemaMissing.tpl');
    }

    private function adminOnly()
    {
        if ($this->getUserAccessLevel('settings.administration') < ACCESS_LEVEL_SA)
        {
            CommonErrors::fatal(COMMONERROR_PERMISSION, $this, 'Administrator access is required.');
        }
    }
}

?>
