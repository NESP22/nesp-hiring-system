<?php TemplateUtility::printHeader('NESP Applicant Detail', array('modules/nespSimple/admin/nespSimpleAdmin.css')); ?>
<?php TemplateUtility::printHeaderBlock(); ?>
<?php TemplateUtility::printTabs($this->active, $this->subActive); ?>
<div id="main" class="nesp-simple-admin">
    <?php TemplateUtility::printQuickSearch(); ?>
    <main id="contents">
        <a class="hub-back" href="<?php echo CATSUtility::getIndexName(); ?>?m=nespSimple">&larr; All Applicants</a>
        <header class="hub-title detail-title">
            <div>
                <p class="hub-eyebrow"><?php $this->_($this->applicant['role_title']); ?></p>
                <h2><?php $this->_($this->applicant['candidate_name']); ?></h2>
                <p><?php $this->_($this->applicant['source_label']); ?> &middot; <?php $this->_($this->applicant['stage_name']); ?></p>
            </div>
            <a class="hub-button secondary" href="<?php echo $this->applicant['candidate_url']; ?>">Open Full OpenCATS Record</a>
        </header>

        <?php if ($this->assignmentSaved): ?>
        <div class="hub-notice success">Interviewer assignment saved.</div>
        <?php endif; ?>
        <?php if ($this->assignmentError !== ''): ?>
        <div class="hub-notice error"><?php $this->_($this->assignmentError); ?></div>
        <?php endif; ?>

        <section class="hub-summary-grid">
            <div>
                <span>Questionnaire</span>
                <strong><?php $this->_($this->applicant['questionnaire_status_label']); ?></strong>
            </div>
            <div>
                <span>Contact</span>
                <strong><?php $this->_($this->applicant['email1'] !== '' ? $this->applicant['email1'] : 'No email'); ?></strong>
                <small><?php $this->_($this->applicant['contact_phone'] !== '' ? $this->applicant['contact_phone'] : 'No phone'); ?></small>
            </div>
            <div>
                <span>Resume</span>
                <strong><?php echo $this->applicant['has_resume'] ? 'Available' : 'Not attached'; ?></strong>
            </div>
            <div>
                <span>Assigned interviewer</span>
                <strong><?php $this->_($this->applicant['assigned_interviewer_names'] !== '' ? $this->applicant['assigned_interviewer_names'] : 'Unassigned'); ?></strong>
            </div>
            <div>
                <span>Interview</span>
                <strong><?php $this->_($this->applicant['interview_status_label']); ?></strong>
            </div>
        </section>

        <div class="hub-two-column">
            <section class="hub-panel">
                <h3>Assign Interviewer</h3>
                <?php if (count($this->applicant['eligible_interviewers'])): ?>
                <form method="post" action="<?php echo CATSUtility::getIndexName(); ?>?m=nespSimple&amp;a=assignInterviewer">
                    <input type="hidden" name="csrfToken" value="<?php $this->_($_SESSION['CATS']->getCSRFToken()); ?>" />
                    <input type="hidden" name="candidateID" value="<?php echo (int) $this->applicant['candidate_id']; ?>" />
                    <input type="hidden" name="jobOrderID" value="<?php echo (int) $this->applicant['joborder_id']; ?>" />
                    <label>
                        Interviewer
                        <select name="interviewerProfileID" required="required">
                            <option value="">Choose interviewer</option>
                            <?php foreach ($this->applicant['eligible_interviewers'] as $profile): ?>
                            <option value="<?php echo (int) $profile['interviewer_profile_id']; ?>"><?php $this->_($profile['display_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button class="hub-button primary" type="submit">Assign Interviewer</button>
                </form>
                <?php else: ?>
                <p>No active interviewer is approved and open for this role.</p>
                <?php endif; ?>
            </section>

            <section class="hub-panel">
                <h3>Interview Tracking</h3>
                <p class="hub-large-status"><?php $this->_($this->applicant['interview_status_label']); ?></p>
                <?php if ($this->applicant['interview_url'] !== ''): ?>
                <a class="hub-button primary" href="<?php echo $this->applicant['interview_url']; ?>">Update Interview Outcome</a>
                <?php else: ?>
                <a class="hub-button primary" href="<?php echo $this->applicant['schedule_url']; ?>">Schedule Interview</a>
                <?php endif; ?>
            </section>
        </div>

        <section class="hub-panel">
            <h3>Resume</h3>
            <?php if (count($this->applicant['resumes'])): ?>
            <ul class="hub-file-list">
                <?php foreach ($this->applicant['resumes'] as $resume): ?>
                <li>
                    <a href="<?php echo htmlspecialchars($resume['retrieval_url'], ENT_QUOTES, 'UTF-8'); ?>">
                        <?php $this->_($resume['original_filename'] !== '' ? $resume['original_filename'] : $resume['title']); ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <p>No resume is attached to this applicant.</p>
            <?php endif; ?>
        </section>

        <section class="hub-panel">
            <h3>Issued Questionnaire Snapshot and Answers</h3>
            <?php if (!empty($this->applicant['questionnaire']['exact_snapshot_available'])): ?>
            <dl class="hub-answers">
                <?php foreach ($this->applicant['questionnaire']['snapshot_rows'] as $answer): ?>
                <div>
                    <dt><?php $this->_($answer['question_label']); ?></dt>
                    <dd><?php $this->_($answer['answer_text'] !== '' ? $answer['answer_text'] : 'No answer submitted'); ?></dd>
                </div>
                <?php endforeach; ?>
            </dl>
            <?php if (!empty($this->applicant['questionnaire']['answers'])): ?>
            <details class="hub-stored-answers">
                <summary>View stored answer rows</summary>
                <dl class="hub-answers">
                    <?php foreach ($this->applicant['questionnaire']['answers'] as $answer): ?>
                    <div>
                        <dt><?php $this->_($answer['question_label']); ?></dt>
                        <dd><?php $this->_($answer['answer_text']); ?></dd>
                    </div>
                    <?php endforeach; ?>
                </dl>
            </details>
            <?php endif; ?>
            <?php elseif (!empty($this->applicant['questionnaire_id'])): ?>
            <p>No immutable question snapshot is stored for this questionnaire. The hub will not substitute the current question set.</p>
            <?php if (!empty($this->applicant['questionnaire']['answers'])): ?>
            <dl class="hub-answers">
                <?php foreach ($this->applicant['questionnaire']['answers'] as $answer): ?>
                <div>
                    <dt><?php $this->_($answer['question_label']); ?></dt>
                    <dd><?php $this->_($answer['answer_text']); ?></dd>
                </div>
                <?php endforeach; ?>
            </dl>
            <?php endif; ?>
            <?php else: ?>
            <p>No questionnaire exists for this applicant and role.</p>
            <?php endif; ?>
        </section>
    </main>
</div>
<?php TemplateUtility::printFooter(); ?>
