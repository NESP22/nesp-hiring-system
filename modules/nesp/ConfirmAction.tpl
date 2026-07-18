<?php TemplateUtility::printHeader($this->title, array('modules/nesp/nespWorkflow.css')); ?>
<?php TemplateUtility::printHeaderBlock(); ?>
<?php TemplateUtility::printTabs($this->active, $this->subActive); ?>
    <div id="main" class="nesp-workflow">
        <?php TemplateUtility::printQuickSearch(); ?>
        <div id="contents">
            <div class="nesp-page-title">
                <h2><?php $this->_($this->title); ?></h2>
                <p>Review this action before continuing.</p>
            </div>

            <div class="nesp-panel">
                <div class="nesp-confirm-box"><?php $this->_($this->body); ?></div>
                <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=<?php $this->_($this->action); ?>" class="nesp-form">
                    <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                    <input type="hidden" name="confirmed" value="1" />
                    <?php foreach ($this->fields as $fieldName => $fieldValue): ?>
                        <input type="hidden" name="<?php echo(htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8')); ?>" value="<?php echo(htmlspecialchars($fieldValue, ENT_QUOTES, 'UTF-8')); ?>" />
                    <?php endforeach; ?>
                    <button type="submit" class="nesp-primary-button">Confirm</button>
                    <a class="nesp-secondary-action" href="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=settings">Cancel</a>
                </form>
            </div>
        </div>
    </div>
<?php TemplateUtility::printFooter(); ?>
