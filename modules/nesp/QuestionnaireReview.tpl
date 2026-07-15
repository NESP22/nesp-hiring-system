<?php TemplateUtility::printHeader('Review Screening Questionnaire', array('modules/nesp/nespWorkflow.css')); ?>
<?php TemplateUtility::printHeaderBlock(); ?>
<?php TemplateUtility::printTabs($this->active, $this->subActive); ?>
    <div id="main" class="nesp-workflow">
        <?php TemplateUtility::printQuickSearch(); ?>
        <div id="contents">
            <div class="nesp-page-title">
                <h2>Review Screening Questionnaire</h2>
                <p>Human-reviewed answers and copy-only invitation controls.</p>
            </div>

            <div class="nesp-safety-banner">
                Reviewing a questionnaire does not change candidate stage or make an automated hiring decision.
            </div>

            <div class="nesp-panel">
                <h3>Summary</h3>
                <dl class="nesp-detail-list">
                    <dt>Candidate</dt>
                    <dd><?php $this->_($this->questionnaire['candidate_name']); ?></dd>
                    <dt>Role</dt>
                    <dd><?php $this->_($this->questionnaire['role_title']); ?></dd>
                    <dt>Status</dt>
                    <dd><?php $this->_($this->questionnaire['status_label']); ?></dd>
                    <dt>Question set</dt>
                    <dd><?php $this->_($this->questionnaire['question_set_label']); ?></dd>
                    <dt>Reviewer</dt>
                    <dd><?php $this->_($this->questionnaire['reviewer_name']); ?></dd>
                    <dt>Submitted</dt>
                    <dd><?php $this->_(empty($this->questionnaire['submitted_at']) ? 'Not submitted' : $this->questionnaire['submitted_at']); ?></dd>
                </dl>
            </div>

            <?php if ($this->isAdmin): ?>
            <div class="nesp-panel">
                <h3>Copy-Only Invitation</h3>
                <?php if ($this->oneTimeInvitationCopy !== ''): ?>
                <textarea id="questionnaireInvitationCopy" rows="5" readonly><?php $this->_($this->oneTimeInvitationCopy); ?></textarea>
                <p>
                    <button type="button" class="nesp-secondary-action" onclick="document.getElementById('questionnaireInvitationCopy').select(); document.execCommand('copy');">Copy Invitation</button>
                </p>
                <?php else: ?>
                <div class="nesp-empty">The secure invitation text is shown only immediately after link generation. Regenerate the link if Craig needs a fresh copy.</div>
                <?php endif; ?>
                <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=markQuestionnaireInvitationCopied">
                    <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                    <input type="hidden" name="questionnaireID" value="<?php echo((int) $this->questionnaire['screening_questionnaire_id']); ?>" />
                    <button class="nesp-primary-action" type="submit">Mark Invitation Copied</button>
                </form>
            </div>

            <div class="nesp-two-column">
                <div class="nesp-panel">
                    <h3>Link Controls</h3>
                    <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=revokeQuestionnaireLink">
                        <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                        <input type="hidden" name="questionnaireID" value="<?php echo((int) $this->questionnaire['screening_questionnaire_id']); ?>" />
                        <button class="nesp-secondary-action" type="submit">Revoke Link</button>
                    </form>
                    <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=regenerateQuestionnaireLink">
                        <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                        <input type="hidden" name="questionnaireID" value="<?php echo((int) $this->questionnaire['screening_questionnaire_id']); ?>" />
                        <button class="nesp-secondary-action" type="submit">Regenerate Link</button>
                    </form>
                </div>

                <div class="nesp-panel">
                    <h3>Assign Reviewer</h3>
                    <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=assignQuestionnaireReviewer">
                        <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                        <input type="hidden" name="questionnaireID" value="<?php echo((int) $this->questionnaire['screening_questionnaire_id']); ?>" />
                        <label for="interviewerProfileID">Interviewer</label>
                        <select id="interviewerProfileID" name="interviewerProfileID">
                            <?php foreach ($this->interviewerProfiles as $profile): ?>
                                <option value="<?php echo((int) $profile['interviewer_profile_id']); ?>"><?php $this->_($profile['display_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="nesp-primary-action" type="submit">Assign</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <div class="nesp-panel">
                <h3>Answers</h3>
                <?php if (count($this->questionnaire['answers'])): ?>
                    <table class="nesp-table">
                        <tr>
                            <th>Question</th>
                            <th>Answer</th>
                        </tr>
                        <?php foreach ($this->questionnaire['answers'] as $answer): ?>
                        <tr>
                            <td><?php $this->_($answer['question_label']); ?></td>
                            <td><?php $this->_($answer['answer_text']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                <?php else: ?>
                    <div class="nesp-empty">No final questionnaire answers have been submitted yet.</div>
                <?php endif; ?>
            </div>

            <div class="nesp-panel">
                <h3>Review Notes</h3>
                <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=saveQuestionnaireReview">
                    <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                    <input type="hidden" name="questionnaireID" value="<?php echo((int) $this->questionnaire['screening_questionnaire_id']); ?>" />
                    <textarea name="reviewNote" rows="5"><?php $this->_($this->questionnaire['review_notes']); ?></textarea>
                    <label><input type="checkbox" name="markComplete" value="1" /> Mark review complete</label>
                    <button class="nesp-primary-action" type="submit">Save Review</button>
                </form>
            </div>
        </div>
    </div>
<?php TemplateUtility::printFooter(); ?>
