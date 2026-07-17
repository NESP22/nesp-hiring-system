<?php TemplateUtility::printHeader('NESP Manage Question Sets', array('modules/nesp/nespWorkflow.css')); ?>
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
                        <h2>Manage Question Sets</h2>
                        <p>Draft, preview, publish, and archive NESP screening question versions.</p>
                    </div>
                </div>
            </div>

            <div class="nesp-safety-banner">
                Published versions are immutable for issued links. Editing starts a draft/new version and never changes old applicant links.
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

            <div class="nesp-panel">
                <h3>Question Sets</h3>
                <table class="nesp-table">
                    <tr>
                        <th>Name</th>
                        <th>Key</th>
                        <th>Current Version</th>
                        <th>Status</th>
                        <th>Role / Job Matching</th>
                        <th>Issued Links</th>
                        <th>Drafts</th>
                        <th>Action</th>
                    </tr>
                    <?php foreach ($this->questionSets as $set): ?>
                    <tr>
                        <td data-label="Name"><?php $this->_($set['display_name']); ?></td>
                        <td data-label="Key"><?php $this->_($set['set_key']); ?></td>
                        <td data-label="Current Version">v<?php echo((int) $set['current_version_number']); ?></td>
                        <td data-label="Status"><?php $this->_($set['status_key']); ?></td>
                        <td data-label="Role / Job Matching"><?php $this->_($set['role_matches']); ?></td>
                        <td data-label="Issued Links"><?php echo((int) $set['issued_count']); ?></td>
                        <td data-label="Drafts"><?php echo((int) $set['draft_count']); ?></td>
                        <td data-label="Action">
                            <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=duplicateQuestionSetDraft">
                                <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                                <input type="hidden" name="questionSetID" value="<?php echo((int) $set['question_set_id']); ?>" />
                                <input type="hidden" name="sourceVersionID" value="<?php echo((int) $set['current_version_id']); ?>" />
                                <button type="submit" class="nesp-secondary-button">Create Draft</button>
                            </form>
                            <?php if ($set['status_key'] !== 'archived'): ?>
                            <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=archiveQuestionSet">
                                <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                                <input type="hidden" name="questionSetID" value="<?php echo((int) $set['question_set_id']); ?>" />
                                <button type="submit" class="nesp-secondary-button">Archive</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>

            <?php if (!empty($this->selectedVersion)): ?>
                <div class="nesp-panel">
                    <h3><?php $this->_($this->selectedVersion['display_name']); ?> v<?php echo((int) $this->selectedVersion['version_number']); ?></h3>
                    <?php if ($this->selectedVersion['status_key'] !== 'draft'): ?>
                        <div class="nesp-confirm-box">This version is published and read-only. Create a draft to edit future wording.</div>
                    <?php else: ?>
                        <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=saveQuestionSetDraft" class="nesp-form nesp-form-wide">
                            <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                            <input type="hidden" name="versionID" value="<?php echo((int) $this->selectedVersion['question_set_version_id']); ?>" />
                            <label>
                                Name
                                <input type="text" name="displayName" value="<?php echo(htmlspecialchars($this->selectedVersion['display_name'], ENT_QUOTES, 'UTF-8')); ?>" />
                            </label>
                            <label>
                                Description
                                <textarea name="description" rows="2"><?php echo(htmlspecialchars($this->selectedVersion['description'], ENT_QUOTES, 'UTF-8')); ?></textarea>
                            </label>

                            <h4>Role / Job Matching</h4>
                            <?php
                                $roleMatches = $this->selectedVersion['role_matches'];
                                $roleMatches[] = array('match_text' => '', 'joborder_id' => '');
                            ?>
                            <?php foreach ($roleMatches as $match): ?>
                                <div class="nesp-inline-fields">
                                    <label>Role text <input type="text" name="roleMatchText[]" value="<?php echo(htmlspecialchars($match['match_text'], ENT_QUOTES, 'UTF-8')); ?>" /></label>
                                    <label>Job ID <input type="text" name="roleMatchJobOrderID[]" value="<?php echo((int) $match['joborder_id']); ?>" /></label>
                                </div>
                            <?php endforeach; ?>

                            <h4>Questions</h4>
                            <?php foreach ($this->selectedVersion['questions'] as $question): ?>
                                <div class="nesp-question-editor-row">
                                    <label>Order <input type="text" name="questionSortOrder[]" value="<?php echo((int) $question['sort_order']); ?>" /></label>
                                    <label>Key <input type="text" name="questionKey[]" value="<?php echo(htmlspecialchars($question['key'], ENT_QUOTES, 'UTF-8')); ?>" /></label>
                                    <label>Type
                                        <select name="questionType[]">
                                            <?php foreach (array('text' => 'Short text', 'textarea' => 'Long text', 'yes_no' => 'Yes / No', 'single_choice' => 'Single choice', 'multiple_choice' => 'Multiple choice', 'number' => 'Number') as $type => $label): ?>
                                                <option value="<?php $this->_($type); ?>"<?php if ($question['type'] === $type): ?> selected="selected"<?php endif; ?>><?php $this->_($label); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <label>Required
                                        <select name="questionRequired[]">
                                            <option value="1"<?php if (!empty($question['required'])): ?> selected="selected"<?php endif; ?>>Yes</option>
                                            <option value="0"<?php if (empty($question['required'])): ?> selected="selected"<?php endif; ?>>No</option>
                                        </select>
                                    </label>
                                    <label>Question text <textarea name="questionLabel[]" rows="2"><?php echo(htmlspecialchars($question['label'], ENT_QUOTES, 'UTF-8')); ?></textarea></label>
                                    <label>Help text <textarea name="questionHelp[]" rows="2"><?php echo(htmlspecialchars($question['help'], ENT_QUOTES, 'UTF-8')); ?></textarea></label>
                                    <label>Choices <textarea name="questionChoices[]" rows="2"><?php echo(htmlspecialchars(implode("\n", $question['choices']), ENT_QUOTES, 'UTF-8')); ?></textarea></label>
                                </div>
                            <?php endforeach; ?>
                            <div class="nesp-question-editor-row">
                                <label>Order <input type="text" name="questionSortOrder[]" value="999" /></label>
                                <label>Key <input type="text" name="questionKey[]" /></label>
                                <label>Type <select name="questionType[]"><option value="text">Short text</option><option value="textarea">Long text</option><option value="yes_no">Yes / No</option><option value="single_choice">Single choice</option><option value="multiple_choice">Multiple choice</option><option value="number">Number</option></select></label>
                                <label>Required <select name="questionRequired[]"><option value="1">Yes</option><option value="0">No</option></select></label>
                                <label>Question text <textarea name="questionLabel[]" rows="2"></textarea></label>
                                <label>Help text <textarea name="questionHelp[]" rows="2"></textarea></label>
                                <label>Choices <textarea name="questionChoices[]" rows="2"></textarea></label>
                            </div>
                            <button type="submit" class="nesp-primary-button">Save Draft</button>
                        </form>

                        <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=publishQuestionSetDraft">
                            <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                            <input type="hidden" name="versionID" value="<?php echo((int) $this->selectedVersion['question_set_version_id']); ?>" />
                            <button type="submit" class="nesp-primary-button">Publish Immutable Version</button>
                        </form>
                    <?php endif; ?>

                    <h4>Preview</h4>
                    <table class="nesp-table">
                        <tr><th>Order</th><th>Question</th><th>Type</th><th>Required</th></tr>
                        <?php foreach ($this->selectedVersion['questions'] as $question): ?>
                        <tr>
                            <td data-label="Order"><?php echo((int) $question['sort_order']); ?></td>
                            <td data-label="Question"><?php $this->_($question['label']); ?></td>
                            <td data-label="Type"><?php $this->_($question['type']); ?></td>
                            <td data-label="Required"><?php echo(!empty($question['required']) ? 'Yes' : 'No'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php TemplateUtility::printFooter(); ?>
