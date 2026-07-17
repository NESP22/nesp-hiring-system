<?php TemplateUtility::printHeader('NESP Screening Questionnaires', array('modules/nesp/nespWorkflow.css')); ?>
<?php TemplateUtility::printHeaderBlock(); ?>
<?php TemplateUtility::printTabs($this->active, $this->subActive); ?>
    <div id="main" class="nesp-workflow">
        <?php TemplateUtility::printQuickSearch(); ?>
        <div id="contents">
            <div class="nesp-page-title">
                <div class="nesp-brand-lockup">
                    <img src="images/nesp-logo.png" alt="New England Sports Photo" />
                    <div>
                        <span class="nesp-kicker">New England Sports Photo</span>
                        <h2>Screening Questionnaires</h2>
                        <p>Track secure applicant questionnaires from invite through human review.</p>
                    </div>
                </div>
            </div>

            <div class="nesp-safety-banner">
                Human-reviewed only: questionnaires do not rank, reject, approve, hire, email, text, call, publish ads, or move candidates automatically.
                Question wording is managed separately in <a href="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=questionSets">Manage Question Sets</a>.
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

            <?php
                $queueLabels = array(
                    'ready' => 'Questionnaire Links Ready',
                    'waiting' => 'Waiting for Questionnaire',
                    'completed' => 'Completed Questionnaires',
                    'human_follow_up' => 'Human Follow-Up Requested',
                    'revoked_expired' => 'Revoked or Expired'
                );
            ?>
            <div class="nesp-card-grid nesp-card-grid-tight">
                <?php foreach ($queueLabels as $queueKey => $queueLabel): ?>
                <div class="nesp-card">
                    <span class="nesp-card-label"><?php $this->_($queueLabel); ?></span>
                    <strong><?php echo(count($this->questionnaireQueues[$queueKey])); ?></strong>
                    <span class="nesp-card-hint">Current queue</span>
                </div>
                <?php endforeach; ?>
            </div>

            <?php foreach ($queueLabels as $queueKey => $queueLabel): ?>
            <div class="nesp-panel">
                <h3><?php $this->_($queueLabel); ?></h3>
                <table class="nesp-table">
                    <caption><?php $this->_($queueLabel); ?> applicant questionnaire queue</caption>
                    <tr>
                        <th>Candidate</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Reviewer</th>
                        <th>Submitted</th>
                        <th>Action</th>
                    </tr>
                    <?php foreach ($this->questionnaireQueues[$queueKey] as $questionnaire): ?>
                    <tr>
                        <td data-label="Candidate"><?php $this->_($questionnaire['candidate_name']); ?></td>
                        <td data-label="Role"><?php $this->_($questionnaire['role_title']); ?></td>
                        <td data-label="Status"><?php $this->_($questionnaire['status_label']); ?></td>
                        <td data-label="Reviewer"><?php $this->_($questionnaire['reviewer_name']); ?></td>
                        <td data-label="Submitted"><?php $this->_(empty($questionnaire['submitted_at']) ? 'Not submitted' : $questionnaire['submitted_at']); ?></td>
                        <td data-label="Action"><a class="nesp-secondary-action" href="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=reviewQuestionnaire&amp;questionnaireID=<?php echo((int) $questionnaire['screening_questionnaire_id']); ?>">Review</a></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!count($this->questionnaireQueues[$queueKey])): ?>
                    <tr>
                        <td data-label="Queue" colspan="6">No items in this queue.</td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php TemplateUtility::printFooter(); ?>
