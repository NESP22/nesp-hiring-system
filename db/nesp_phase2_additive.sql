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
    ('NESP_AI_REVIEW_ENABLED', 'AI Candidate Review', 'Disabled integration flag. No model calls are made by this module.', 0, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    display_name = VALUES(display_name),
    description = VALUES(description),
    is_enabled = 0,
    requires_admin_approval = 1,
    date_modified = NOW();

ALTER TABLE `nesp_candidate_workflow`
    ADD COLUMN IF NOT EXISTS `waiting_on_key` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Craig',
    ADD COLUMN IF NOT EXISTS `summary` TEXT COLLATE utf8mb4_unicode_ci,
    ADD COLUMN IF NOT EXISTS `next_action_label` VARCHAR(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS `due_at` DATETIME;

ALTER TABLE `nesp_interviewer_profile`
    ADD COLUMN IF NOT EXISTS `can_add_notes` TINYINT(1) NOT NULL DEFAULT '1',
    ADD COLUMN IF NOT EXISTS `can_submit_scorecard` TINYINT(1) NOT NULL DEFAULT '1';

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
