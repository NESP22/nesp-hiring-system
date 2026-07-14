/* NESP Phase 2 additive migration.
 *
 * Safe defaults:
 * - All feature flags are inserted or reset disabled.
 * - Scorecard template is present but disabled.
 * - No production applicants, interviewer accounts, calls, meetings, AI jobs,
 *   emails, or SMS messages are created.
 */

INSERT INTO `nesp_feature_flag`
    (`flag_key`, `display_name`, `description`, `is_enabled`, `requires_admin_approval`, `date_created`, `date_modified`)
VALUES
    ('NESP_WORKFLOW_ENABLED', 'NESP Workflow', 'Craig-reviewed hiring workflow dashboard and task queues.', 0, 1, NOW(), NOW()),
    ('NESP_INTERVIEWER_POOL_ENABLED', 'Interviewer Pool', 'Scoped interviewer access to assigned candidates and interviews.', 0, 1, NOW(), NOW()),
    ('NESP_PRESCREEN_ENABLED', 'Prescreen Workflow', 'Craig-approved phone-screen workflow status and results.', 0, 1, NOW(), NOW()),
    ('NESP_VAPI_ENABLED', 'Vapi Phone Screens', 'Disabled integration flag. No calls are placed by this module.', 0, 1, NOW(), NOW()),
    ('NESP_ZOOM_ENABLED', 'Zoom Scheduling', 'Disabled integration flag. No meetings are created by this module.', 0, 1, NOW(), NOW()),
    ('NESP_AI_REVIEW_ENABLED', 'AI Candidate Review', 'Disabled integration flag. No model calls are made by this module.', 0, 1, NOW(), NOW()),
    ('NESP_STAFFING_FORECAST_ENABLED', 'Staffing Forecast', 'Seasonal staffing forecast screen and internal draft recommendations.', 0, 1, NOW(), NOW()),
    ('NESP_STAFFING_DRIVE_IMPORT_ENABLED', 'Staffing Drive Import', 'Google Drive staffing schedule discovery and import controls.', 0, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    display_name = VALUES(display_name),
    description = VALUES(description),
    is_enabled = 0,
    requires_admin_approval = 1,
    date_modified = NOW();

UPDATE `nesp_integration_status`
SET `message` = 'Disabled in Phase 2. No calls can be placed.',
    `date_modified` = NOW()
WHERE `integration_key` = 'vapi';

UPDATE `nesp_integration_status`
SET `message` = 'Disabled in Phase 2. No meetings can be created.',
    `date_modified` = NOW()
WHERE `integration_key` = 'zoom';

UPDATE `nesp_integration_status`
SET `message` = 'Disabled in Phase 2. No model calls can run.',
    `date_modified` = NOW()
WHERE `integration_key` = 'ai_review';

UPDATE `nesp_integration_status`
SET `message` = 'Disabled in Phase 2. No outbound applicant email can be sent.',
    `date_modified` = NOW()
WHERE `integration_key` = 'email';

ALTER TABLE `nesp_candidate_workflow`
    ADD COLUMN IF NOT EXISTS `waiting_on_key` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Craig',
    ADD COLUMN IF NOT EXISTS `summary` TEXT COLLATE utf8mb4_unicode_ci,
    ADD COLUMN IF NOT EXISTS `next_action_label` VARCHAR(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS `due_at` DATETIME;

ALTER TABLE `nesp_interviewer_profile`
    ADD COLUMN IF NOT EXISTS `can_add_notes` TINYINT(1) NOT NULL DEFAULT '1',
    ADD COLUMN IF NOT EXISTS `can_submit_scorecard` TINYINT(1) NOT NULL DEFAULT '1';

ALTER TABLE `nesp_candidate_workflow`
    ADD INDEX IF NOT EXISTS `IDX_nesp_dashboard_due` (`due_at`),
    ADD INDEX IF NOT EXISTS `IDX_nesp_waiting_on` (`waiting_on_key`);

ALTER TABLE `nesp_interview`
    ADD INDEX IF NOT EXISTS `IDX_nesp_interview_schedule` (`scheduled_start`, `status_key`);

ALTER TABLE `nesp_scorecard_response`
    ADD COLUMN IF NOT EXISTS `locked_at` DATETIME,
    ADD COLUMN IF NOT EXISTS `unlocked_at` DATETIME,
    ADD COLUMN IF NOT EXISTS `unlocked_by_user_id` INT(11),
    ADD COLUMN IF NOT EXISTS `lock_reason` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    ADD INDEX IF NOT EXISTS `IDX_nesp_scorecard_interview` (`interview_id`),
    ADD INDEX IF NOT EXISTS `IDX_nesp_scorecard_submitted` (`submitted_at`);

INSERT INTO `nesp_workflow_stage`
    (`stage_key`, `display_name`, `description`, `sort_order`, `is_terminal`, `is_enabled`, `date_created`, `date_modified`)
SELECT 'applicant_clarification_requested', 'Applicant Clarification Requested', 'Waiting on the applicant to clarify an application detail.', 35, 0, 1, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `nesp_workflow_stage` WHERE `stage_key` = 'applicant_clarification_requested');

INSERT INTO `nesp_workflow_stage`
    (`stage_key`, `display_name`, `description`, `sort_order`, `is_terminal`, `is_enabled`, `date_created`, `date_modified`)
SELECT 'interview_confirmation_pending', 'Interview Confirmation Pending', 'Waiting for applicant confirmation or reschedule response.', 65, 0, 1, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `nesp_workflow_stage` WHERE `stage_key` = 'interview_confirmation_pending');

INSERT INTO `nesp_workflow_stage`
    (`stage_key`, `display_name`, `description`, `sort_order`, `is_terminal`, `is_enabled`, `date_created`, `date_modified`)
SELECT 'scorecard_complete', 'Scorecard Complete', 'Completed scorecard is ready for Craig decision.', 85, 0, 1, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `nesp_workflow_stage` WHERE `stage_key` = 'scorecard_complete');

INSERT INTO `nesp_workflow_stage`
    (`stage_key`, `display_name`, `description`, `sort_order`, `is_terminal`, `is_enabled`, `date_created`, `date_modified`)
SELECT 'hold', 'Hold', 'Candidate is intentionally paused for future seasonal review.', 105, 1, 1, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `nesp_workflow_stage` WHERE `stage_key` = 'hold');

INSERT INTO `nesp_workflow_stage`
    (`stage_key`, `display_name`, `description`, `sort_order`, `is_terminal`, `is_enabled`, `date_created`, `date_modified`)
SELECT 'not_selected', 'Not Selected', 'Final human decline decision recorded.', 110, 1, 1, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `nesp_workflow_stage` WHERE `stage_key` = 'not_selected');

INSERT INTO `nesp_workflow_stage`
    (`stage_key`, `display_name`, `description`, `sort_order`, `is_terminal`, `is_enabled`, `date_created`, `date_modified`)
SELECT 'withdrawn', 'Withdrawn', 'Candidate withdrew or stopped the process.', 120, 1, 1, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `nesp_workflow_stage` WHERE `stage_key` = 'withdrawn');

INSERT INTO `nesp_scorecard_template`
    (`template_key`, `display_name`, `questions_json`, `is_enabled`, `date_created`, `date_modified`)
VALUES
    ('nesp_standard_interview', 'NESP Standard Interview Scorecard', '[{"key":"reliability","label":"Reliability and schedule fit","type":"rating"},{"key":"people_skills","label":"Comfort with athletes, families, coaches, and staff","type":"rating"},{"key":"role_fit","label":"Role-specific skills or trainability","type":"rating"},{"key":"notes","label":"Factual notes from the conversation","type":"textarea"}]', 0, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    display_name = VALUES(display_name),
    questions_json = VALUES(questions_json),
    is_enabled = 0,
    date_modified = NOW();

CREATE TABLE IF NOT EXISTS `nesp_session_security_event` (
  `session_security_event_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11),
  `event_type` VARCHAR(96) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` VARCHAR(45) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `user_agent` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `metadata_json` TEXT COLLATE utf8mb4_unicode_ci,
  `date_created` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  PRIMARY KEY (`session_security_event_id`),
  KEY `IDX_user_id` (`user_id`),
  KEY `IDX_event_type` (`event_type`),
  KEY `IDX_date_created` (`date_created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `nesp_interviewer_role_rule` (
  `role_rule_id` INT(11) NOT NULL AUTO_INCREMENT,
  `interviewer_profile_id` INT(11) NOT NULL,
  `joborder_id` INT(11),
  `role_match_text` VARCHAR(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `assignment_mode` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'suggest_only',
  `priority` INT(11) NOT NULL DEFAULT '50',
  `is_active` TINYINT(1) NOT NULL DEFAULT '1',
  `notes` TEXT COLLATE utf8mb4_unicode_ci,
  `created_by_user_id` INT(11),
  `date_created` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  `date_modified` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  PRIMARY KEY (`role_rule_id`),
  KEY `IDX_interviewer_profile_id` (`interviewer_profile_id`),
  KEY `IDX_joborder_id` (`joborder_id`),
  KEY `IDX_role_match_text` (`role_match_text`),
  KEY `IDX_priority` (`priority`),
  KEY `IDX_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `nesp_interviewer_availability` (
  `availability_id` INT(11) NOT NULL AUTO_INCREMENT,
  `interviewer_profile_id` INT(11) NOT NULL,
  `weekday_key` VARCHAR(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `timezone` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'America/New_York',
  `slot_minutes` INT(11) NOT NULL DEFAULT '30',
  `buffer_minutes` INT(11) NOT NULL DEFAULT '10',
  `is_active` TINYINT(1) NOT NULL DEFAULT '1',
  `notes` TEXT COLLATE utf8mb4_unicode_ci,
  `created_by_user_id` INT(11),
  `date_created` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  `date_modified` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  PRIMARY KEY (`availability_id`),
  KEY `IDX_interviewer_profile_id` (`interviewer_profile_id`),
  KEY `IDX_weekday_time` (`weekday_key`, `start_time`),
  KEY `IDX_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `nesp_interview_slot` (
  `interview_slot_id` INT(11) NOT NULL AUTO_INCREMENT,
  `interviewer_profile_id` INT(11) NOT NULL,
  `availability_id` INT(11),
  `candidate_id` INT(11),
  `joborder_id` INT(11),
  `scheduled_start` DATETIME NOT NULL,
  `scheduled_end` DATETIME NOT NULL,
  `slot_status_key` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `source_key` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `zoom_status_key` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'disabled',
  `booking_token_hash` CHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `notes` TEXT COLLATE utf8mb4_unicode_ci,
  `created_by_user_id` INT(11),
  `date_created` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  `date_modified` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  PRIMARY KEY (`interview_slot_id`),
  KEY `IDX_interviewer_profile_id` (`interviewer_profile_id`),
  KEY `IDX_availability_id` (`availability_id`),
  KEY `IDX_candidate_id` (`candidate_id`),
  KEY `IDX_joborder_id` (`joborder_id`),
  KEY `IDX_schedule_status` (`scheduled_start`, `slot_status_key`),
  KEY `IDX_zoom_status_key` (`zoom_status_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `nesp_staffing_schedule_history` (
  `schedule_history_id` INT(11) NOT NULL AUTO_INCREMENT,
  `season_year` INT(4) NOT NULL,
  `season_name` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `week_start` DATE NOT NULL,
  `event_count` INT(11) NOT NULL DEFAULT '0',
  `photographer_slots` INT(11) NOT NULL DEFAULT '0',
  `photographer_hours` DECIMAL(8,2) NOT NULL DEFAULT '0.00',
  `source_label` VARCHAR(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `notes` TEXT COLLATE utf8mb4_unicode_ci,
  `date_created` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  `date_modified` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  PRIMARY KEY (`schedule_history_id`),
  KEY `IDX_week_start` (`week_start`),
  KEY `IDX_season` (`season_year`, `season_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `nesp_staffing_import_batch` (
  `import_batch_id` INT(11) NOT NULL AUTO_INCREMENT,
  `source_type` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `source_identifier` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `source_checksum` CHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `source_label` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `status_key` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `discovered_file_count` INT(11) NOT NULL DEFAULT '0',
  `imported_file_count` INT(11) NOT NULL DEFAULT '0',
  `rows_imported` INT(11) NOT NULL DEFAULT '0',
  `rows_requiring_review` INT(11) NOT NULL DEFAULT '0',
  `created_by_user_id` INT(11),
  `last_imported_at` DATETIME,
  `undone_at` DATETIME,
  `undone_by_user_id` INT(11),
  `date_created` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  `date_modified` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  PRIMARY KEY (`import_batch_id`),
  UNIQUE KEY `IDX_nesp_import_source` (`source_type`, `source_identifier`, `source_checksum`),
  KEY `IDX_status_key` (`status_key`),
  KEY `IDX_last_imported_at` (`last_imported_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `nesp_staffing_import_row` (
  `import_row_id` INT(11) NOT NULL AUTO_INCREMENT,
  `import_batch_id` INT(11) NOT NULL,
  `source_row_hash` CHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `source_sheet_name` VARCHAR(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `source_row_number` INT(11) NOT NULL DEFAULT '0',
  `event_date` DATE,
  `event_start_time` TIME,
  `event_end_time` TIME,
  `state` VARCHAR(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `sport` VARCHAR(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `event_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `role_key` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `staff_name` VARCHAR(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `staff_count` INT(11) NOT NULL DEFAULT '0',
  `staff_hours` DECIMAL(8,2) NOT NULL DEFAULT '0.00',
  `raw_source_text` TEXT COLLATE utf8mb4_unicode_ci,
  `unresolved_json` TEXT COLLATE utf8mb4_unicode_ci,
  `issue_count` INT(11) NOT NULL DEFAULT '0',
  `status_key` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normalized',
  `date_created` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  PRIMARY KEY (`import_row_id`),
  UNIQUE KEY `IDX_nesp_import_row_lineage` (`import_batch_id`, `source_row_hash`),
  KEY `IDX_event_date` (`event_date`),
  KEY `IDX_state` (`state`),
  KEY `IDX_role_key` (`role_key`),
  KEY `IDX_status_key` (`status_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `nesp_staffing_import_issue` (
  `import_issue_id` INT(11) NOT NULL AUTO_INCREMENT,
  `import_batch_id` INT(11) NOT NULL,
  `import_row_id` INT(11),
  `issue_key` VARCHAR(96) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `severity_key` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'review',
  `message` TEXT COLLATE utf8mb4_unicode_ci,
  `status_key` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `date_created` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  `date_resolved` DATETIME,
  PRIMARY KEY (`import_issue_id`),
  KEY `IDX_import_batch_id` (`import_batch_id`),
  KEY `IDX_import_row_id` (`import_row_id`),
  KEY `IDX_status_key` (`status_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `nesp_staffing_forecast` (
  `staffing_forecast_id` INT(11) NOT NULL AUTO_INCREMENT,
  `forecast_year` INT(4) NOT NULL,
  `forecast_name` VARCHAR(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `target_week_start` DATE NOT NULL,
  `expected_event_count` INT(11) NOT NULL DEFAULT '0',
  `required_photographers` INT(11) NOT NULL DEFAULT '0',
  `expected_hours` DECIMAL(8,2) NOT NULL DEFAULT '0.00',
  `confidence_key` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'low',
  `notes` TEXT COLLATE utf8mb4_unicode_ci,
  `date_created` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  `date_modified` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  PRIMARY KEY (`staffing_forecast_id`),
  KEY `IDX_target_week_start` (`target_week_start`),
  KEY `IDX_forecast_year` (`forecast_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `nesp_staffing_recommendation` (
  `staffing_recommendation_id` INT(11) NOT NULL AUTO_INCREMENT,
  `created_by_user_id` INT(11),
  `title` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `recommendation_json` TEXT COLLATE utf8mb4_unicode_ci,
  `status_key` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `date_created` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  `date_modified` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  PRIMARY KEY (`staffing_recommendation_id`),
  KEY `IDX_status_key` (`status_key`),
  KEY `IDX_created_by_user_id` (`created_by_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
