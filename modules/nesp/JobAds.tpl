<?php TemplateUtility::printHeader('NESP Job Ads', array('modules/nesp/nespWorkflow.css')); ?>
<?php TemplateUtility::printHeaderBlock(); ?>
<?php TemplateUtility::printTabs($this->active, $this->subActive); ?>
    <div id="main" class="nesp-workflow">
        <?php TemplateUtility::printQuickSearch(); ?>
        <div id="contents">
            <div class="nesp-page-title">
                <h2>NESP Job Ads Control Center</h2>
                <p>Draft ads, tracking links, campaign notes, and source reporting. Nothing here publishes ads, spends money, sends applicant messages, or changes candidate stages.</p>
            </div>

            <div class="nesp-safety-banner">
                Stop before publishing, paid promotion, billing, account creation, identity verification, accepting terms, or applicant messaging unless Craig explicitly approves that exact action.
            </div>

            <div class="nesp-dashboard-nav">
                <?php foreach ($this->dashboardNavigation as $navItem): ?>
                    <?php if ($navItem['key'] === 'settings' && $this->getUserAccessLevel('settings.administration') < ACCESS_LEVEL_SA): ?>
                        <?php continue; ?>
                    <?php endif; ?>
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
                <h3>Campaign Controls</h3>
                <p class="nesp-help-text">These fields are Craig's internal notes only. They do not publish ads or activate paid campaigns.</p>
                <table class="nesp-table">
                    <tr>
                        <th>Platform</th>
                        <th>Status</th>
                        <th>Renewal / Expiration</th>
                        <th>Manual Spend</th>
                        <th>Notes</th>
                        <th>Save</th>
                    </tr>
                    <?php foreach ($this->campaignControls as $control): ?>
                    <tr>
                        <form method="post" action="<?php echo(CATSUtility::getIndexName()); ?>?m=nesp&amp;a=saveRecruitingCampaignControl">
                            <input type="hidden" name="csrfToken" value="<?php echo(htmlspecialchars($_SESSION['CATS']->getCSRFToken(), ENT_QUOTES, 'UTF-8')); ?>" />
                            <input type="hidden" name="platformKey" value="<?php echo(htmlspecialchars($control['platform_key'], ENT_QUOTES, 'UTF-8')); ?>" />
                            <td>
                                <strong><?php $this->_($control['display_name']); ?></strong><br />
                                <span class="nesp-muted">Approval required before publishing or spending.</span>
                            </td>
                            <td>
                                <select name="campaignStatus">
                                    <?php foreach (array('draft' => 'Draft', 'ready_for_review' => 'Ready for Craig Review', 'approved_to_publish_manually' => 'Approved to Publish Manually', 'published_manually' => 'Published Manually', 'paused' => 'Paused', 'expired' => 'Expired', 'removed' => 'Removed') as $key => $label): ?>
                                        <option value="<?php echo($key); ?>"<?php if ($control['campaign_status'] === $key): ?> selected="selected"<?php endif; ?>><?php $this->_($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="text" name="renewalDate" value="<?php $this->_($control['renewal_date']); ?>" placeholder="YYYY-MM-DD" /></td>
                            <td><input type="text" name="manualSpend" value="<?php $this->_($control['manual_spend']); ?>" /></td>
                            <td><textarea name="notes" rows="2"><?php echo(htmlspecialchars($control['notes'], ENT_QUOTES, 'UTF-8')); ?></textarea></td>
                            <td><button type="submit" class="nesp-secondary-button">Save</button></td>
                        </form>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>

            <div class="nesp-panel">
                <h3>Source Reporting</h3>
                <p class="nesp-help-text">Qualified means a human moved the applicant beyond initial review. The system does not rank or reject candidates automatically.</p>
                <table class="nesp-table">
                    <tr>
                        <th>Platform</th>
                        <th>Stored Source</th>
                        <th>Applications</th>
                        <th>Qualified</th>
                        <th>Scheduled Screens</th>
                        <th>Completed Screens</th>
                        <th>Spend</th>
                        <th>Cost / Applicant</th>
                    </tr>
                    <?php foreach ($this->sourceReport as $row): ?>
                    <tr>
                        <td><?php $this->_($row['platform']); ?></td>
                        <td><?php $this->_($row['candidate_source_label']); ?></td>
                        <td><?php $this->_($row['applications']); ?></td>
                        <td><?php $this->_($row['qualified_applicants']); ?></td>
                        <td><?php $this->_($row['scheduled_phone_screens']); ?></td>
                        <td><?php $this->_($row['completed_phone_screens']); ?></td>
                        <td>$<?php $this->_($row['manual_spend']); ?></td>
                        <td><?php echo($row['cost_per_applicant'] === '' ? '-' : '$' . htmlspecialchars($row['cost_per_applicant'], ENT_QUOTES, 'UTF-8')); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>

            <div class="nesp-panel">
                <h3>Platform Matrix</h3>
                <table class="nesp-table">
                    <tr>
                        <th>Platform</th>
                        <th>Best Roles</th>
                        <th>Cost</th>
                        <th>Access</th>
                        <th>Method</th>
                        <th>Images / Limits</th>
                        <th>Tracking / Destination</th>
                        <th>Approval</th>
                    </tr>
                    <?php foreach ($this->platformMatrix as $platform): ?>
                    <tr>
                        <td><strong><?php $this->_($platform['platform']); ?></strong></td>
                        <td><?php $this->_($platform['role_types']); ?></td>
                        <td><?php $this->_($platform['cost_type']); ?></td>
                        <td><?php $this->_($platform['account_access']); ?></td>
                        <td><?php $this->_($platform['posting_method']); ?></td>
                        <td><?php $this->_($platform['image_dimensions']); ?><br /><span class="nesp-muted"><?php $this->_($platform['character_limits']); ?></span></td>
                        <td><?php $this->_($platform['tracking_support']); ?><br /><span class="nesp-muted"><?php $this->_($platform['application_destination']); ?></span></td>
                        <td><?php $this->_($platform['approval_required']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>

            <div class="nesp-panel">
                <h3>Reusable Ad Templates</h3>
                <?php foreach ($this->adTemplates as $template): ?>
                    <div class="nesp-reference-list">
                        <h4><?php $this->_($template['title']); ?> - <?php $this->_($template['status']); ?></h4>
                        <dl>
                            <dt>Headline</dt>
                            <dd><?php $this->_($template['headline']); ?></dd>
                            <dt>Compensation</dt>
                            <dd><?php $this->_($template['compensation']); ?></dd>
                            <dt>Location</dt>
                            <dd><?php $this->_($template['location']); ?></dd>
                            <dt>Schedule</dt>
                            <dd><?php $this->_($template['schedule']); ?></dd>
                            <dt>Tracking</dt>
                            <dd><?php $this->_($template['tracking_source_code']); ?></dd>
                        </dl>
                        <label>
                            Short version
                            <textarea rows="3" readonly="readonly"><?php echo(htmlspecialchars($template['short_version'], ENT_QUOTES, 'UTF-8')); ?></textarea>
                        </label>
                        <label>
                            Full version
                            <textarea rows="8" readonly="readonly"><?php echo(htmlspecialchars($template['full_version'], ENT_QUOTES, 'UTF-8')); ?></textarea>
                        </label>
                        <?php if (count($template['platform_versions'])): ?>
                            <table class="nesp-table">
                                <tr>
                                    <th>Platform</th>
                                    <th>Tracked Link</th>
                                    <th>Short Platform Copy</th>
                                </tr>
                                <?php foreach ($template['platform_versions'] as $version): ?>
                                <tr>
                                    <td><?php $this->_($version['platform']); ?></td>
                                    <td><code><?php $this->_($version['tracking_link']); ?></code></td>
                                    <td><?php $this->_($version['copy']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </table>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php TemplateUtility::printFooter(); ?>
