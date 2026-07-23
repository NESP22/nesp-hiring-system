<?php TemplateUtility::printHeader('NESP Hiring Hub', array('modules/nespSimple/admin/nespSimpleAdmin.css')); ?>
<?php TemplateUtility::printHeaderBlock(); ?>
<?php TemplateUtility::printTabs($this->active, $this->subActive); ?>
<div id="main" class="nesp-simple-admin">
    <?php TemplateUtility::printQuickSearch(); ?>
    <main id="contents">
        <header class="hub-title">
            <div>
                <p class="hub-eyebrow">NESP Hiring</p>
                <h2>All Applicants</h2>
                <p>Search, review, assign, and track every active NESP applicant from one screen.</p>
            </div>
            <a class="hub-button secondary" href="<?php echo CATSUtility::getIndexName(); ?>?m=nespSimple&amp;a=export">Export All Applicants CSV</a>
        </header>

        <form class="hub-filters" method="get" action="<?php echo CATSUtility::getIndexName(); ?>">
            <input type="hidden" name="m" value="nespSimple" />
            <input type="hidden" name="a" value="list" />
            <label>
                Search
                <input type="search" name="search" value="<?php $this->_($this->filters['search']); ?>" placeholder="Name, job, source, interviewer" />
            </label>
            <label>
                Job
                <select name="role">
                    <option value="">All jobs</option>
                    <?php foreach ($this->filterOptions['roles'] as $jobOrderID => $roleTitle): ?>
                    <option value="<?php echo (int) $jobOrderID; ?>"<?php echo $this->filters['role'] === (string) $jobOrderID ? ' selected="selected"' : ''; ?>><?php $this->_($roleTitle); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Source
                <select name="source">
                    <option value="">All sources</option>
                    <?php foreach ($this->filterOptions['sources'] as $source): ?>
                    <option value="<?php $this->_($source); ?>"<?php echo $this->filters['source'] === $source ? ' selected="selected"' : ''; ?>><?php $this->_($source); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Status
                <select name="status">
                    <option value="">All statuses</option>
                    <?php foreach ($this->filterOptions['statuses'] as $statusKey => $statusLabel): ?>
                    <option value="<?php $this->_($statusKey); ?>"<?php echo $this->filters['status'] === $statusKey ? ' selected="selected"' : ''; ?>><?php $this->_($statusLabel); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="hub-button primary" type="submit">Show Applicants</button>
            <a class="hub-clear" href="<?php echo CATSUtility::getIndexName(); ?>?m=nespSimple">Clear</a>
        </form>

        <div class="hub-count"><?php echo count($this->applicants); ?> applicant<?php echo count($this->applicants) === 1 ? '' : 's'; ?></div>

        <?php if (count($this->applicants)): ?>
        <div class="hub-table-wrap">
            <table class="hub-table">
                <thead>
                    <tr>
                        <th>Applicant</th>
                        <th>Contact</th>
                        <th>Job / Source</th>
                        <th>Status</th>
                        <th>Questionnaire</th>
                        <th>Resume</th>
                        <th>Interviewer</th>
                        <th>Interview</th>
                        <th><span class="sr-only">Open</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($this->applicants as $applicant): ?>
                    <tr>
                        <td data-label="Applicant"><strong><?php $this->_($applicant['candidate_name']); ?></strong></td>
                        <td data-label="Contact">
                            <strong><?php $this->_($applicant['email1'] !== '' ? $applicant['email1'] : 'No email'); ?></strong>
                            <span><?php $this->_($applicant['contact_phone'] !== '' ? $applicant['contact_phone'] : 'No phone'); ?></span>
                        </td>
                        <td data-label="Job / Source">
                            <strong><?php $this->_($applicant['role_title']); ?></strong>
                            <span><?php $this->_($applicant['source_label']); ?></span>
                        </td>
                        <td data-label="Status"><span class="hub-status"><?php $this->_($applicant['stage_name']); ?></span></td>
                        <td data-label="Questionnaire"><?php $this->_($applicant['questionnaire_status_label']); ?></td>
                        <td data-label="Resume"><?php echo $applicant['has_resume'] ? 'Yes' : 'No'; ?></td>
                        <td data-label="Interviewer"><?php $this->_($applicant['assigned_interviewer_names'] !== '' ? $applicant['assigned_interviewer_names'] : 'Unassigned'); ?></td>
                        <td data-label="Interview"><?php $this->_($applicant['interview_status_label']); ?></td>
                        <td class="hub-action">
                            <a class="hub-button primary compact" href="<?php echo CATSUtility::getIndexName(); ?>?m=nespSimple&amp;a=detail&amp;candidateID=<?php echo (int) $applicant['candidate_id']; ?>&amp;jobOrderID=<?php echo (int) $applicant['joborder_id']; ?>">Open</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="hub-empty">No applicants match these filters.</div>
        <?php endif; ?>
    </main>
</div>
<?php TemplateUtility::printFooter(); ?>
