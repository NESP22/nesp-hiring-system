<?php TemplateUtility::printHeader('Invite to Screening Questionnaire', array('modules/nesp/nespWorkflow.css')); ?>
<?php TemplateUtility::printHeaderBlock(); ?>
<?php TemplateUtility::printTabs($this->active, $this->subActive); ?>
    <div id="main" class="nesp-workflow">
        <?php TemplateUtility::printQuickSearch(); ?>
        <div id="contents">
            <div class="nesp-page-title">
                <h2>Invite to Screening Questionnaire</h2>
                <p>Prepare a secure questionnaire link. Nothing is sent automatically.</p>
            </div>

            <div class="nesp-safety-banner">
                This action creates copy-only invitation text. It does not email, text, call, rank, reject, approve, hire, or change stage.
            </div>

            <div class="nesp-panel">
                <h3>Candidate and Role</h3>
                <dl class="nesp-detail-list">
                    <dt>Candidate</dt>
                    <dd><?php $this->_($this->preview['candidate_name']); ?></dd>
                    <dt>Role</dt>
                    <dd><?php $this->_($this->preview['title']); ?></dd>
                    <dt>Question set</dt>
                    <dd><?php $this->_($this->preview['question_set_label']); ?></dd>
                    <dt>Estimated time</dt>
                    <dd>Approximately <?php $this->_($this->preview['estimated_minutes']); ?></dd>
                </dl>
            </div>

            <div class="nesp-panel">
                <h3>Questions</h3>
                <ol class="nesp-list">
                    <?php foreach ($this->preview['questions'] as $question): ?>
                    <li><?php $this->_($question['label']); ?><?php echo(!empty($question['required']) ? ' *' : ''); ?></li>
                    <?php endforeach; ?>
                </ol>
            </div>

            <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=requestQuestionnaire">
                <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                <input type="hidden" name="candidateID" value="<?php echo((int) $this->preview['candidate_id']); ?>" />
                <input type="hidden" name="jobOrderID" value="<?php echo((int) $this->preview['joborder_id']); ?>" />
                <button class="nesp-primary-action" type="submit">Generate Secure Questionnaire Link</button>
                <a class="nesp-secondary-action" href="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp">Cancel</a>
            </form>
        </div>
    </div>
<?php TemplateUtility::printFooter(); ?>
