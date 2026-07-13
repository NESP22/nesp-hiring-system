<?php TemplateUtility::printHeader('NESP Settings', array('modules/nesp/nespWorkflow.css')); ?>
<?php TemplateUtility::printHeaderBlock(); ?>
<?php TemplateUtility::printTabs($this->active, $this->subActive); ?>
    <div id="main" class="nesp-workflow">
        <?php TemplateUtility::printQuickSearch(); ?>
        <div id="contents">
            <div class="nesp-page-title">
                <h2>NESP Settings</h2>
                <p>Feature flags, scoped interviewer setup, and audit-reviewed controls. All Phase 2 production flags default off.</p>
            </div>

            <div class="nesp-safety-banner">
                Turning on a flag here changes only database feature state. It does not deploy, run migrations, create Zoom meetings, initiate Vapi calls, send messages, or run AI review.
            </div>

            <div class="nesp-two-column">
                <div class="nesp-panel">
                    <h3>Feature Flags</h3>
                    <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=saveFeatureFlags">
                        <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                        <table class="nesp-table">
                            <tr>
                                <th>Enabled</th>
                                <th>Feature</th>
                                <th>Purpose</th>
                            </tr>
                            <?php foreach ($this->featureFlags as $flag): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="featureFlags[]" value="<?php $this->_($flag['flag_key']); ?>"<?php if ((int) $flag['is_enabled'] === 1): ?> checked="checked"<?php endif; ?> />
                                </td>
                                <td>
                                    <strong><?php $this->_($flag['display_name']); ?></strong><br />
                                    <span class="nesp-muted"><?php $this->_($flag['flag_key']); ?></span>
                                </td>
                                <td><?php $this->_($flag['description']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                        <button type="submit" class="nesp-primary-button">Save Feature Flags</button>
                    </form>
                </div>

                <div class="nesp-panel">
                    <h3>Interviewer Pool</h3>
                    <div class="nesp-card-grid nesp-card-grid-tight">
                        <div class="nesp-card">
                            <span class="nesp-card-label">Active Interviewers</span>
                            <strong><?php $this->_($this->summary['activeInterviewers']); ?></strong>
                        </div>
                        <div class="nesp-card">
                            <span class="nesp-card-label">Candidate Grants</span>
                            <strong><?php $this->_($this->summary['candidateGrants']); ?></strong>
                        </div>
                    </div>

                    <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=createInterviewer" class="nesp-form">
                        <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                        <label>
                            Display name
                            <input type="text" name="displayName" />
                        </label>
                        <label>
                            Email
                            <input type="text" name="email" />
                        </label>
                        <label>
                            Role
                            <select name="roleKey">
                                <option value="interviewer">Interviewer</option>
                                <option value="lead_interviewer">Lead interviewer</option>
                                <option value="field_trainer">Field trainer</option>
                            </select>
                        </label>
                        <button type="submit" class="nesp-secondary-button">Create Inactive Profile</button>
                    </form>
                </div>
            </div>

            <div class="nesp-panel">
                <h3>Interviewer Profiles</h3>
                <table class="nesp-table">
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Active Grants</th>
                        <th>Last Change</th>
                    </tr>
                    <?php foreach ($this->interviewerProfiles as $profile): ?>
                    <tr>
                        <td><?php $this->_($profile['display_name']); ?></td>
                        <td><?php $this->_($profile['email']); ?></td>
                        <td><?php $this->_($profile['role_key']); ?></td>
                        <td><?php echo(((int) $profile['is_active'] === 1) ? 'Active' : 'Inactive'); ?></td>
                        <td><?php $this->_($profile['active_grants']); ?></td>
                        <td><?php $this->_($profile['date_modified']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!count($this->interviewerProfiles)): ?>
                    <tr>
                        <td colspan="6">No interviewer profiles have been created.</td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
<?php TemplateUtility::printFooter(); ?>
