/* NESP Phase 2 fake fixture data.
 *
 * Use only in local/test databases. Do not run against production. Names,
 * emails, schedules, and staffing history are synthetic.
 */

DELETE FROM `nesp_staffing_schedule_history`
WHERE `source_label` = 'synthetic Craig schedule fixture';

DELETE FROM `nesp_staffing_import_issue`
WHERE `import_batch_id` = 920001;

DELETE FROM `nesp_staffing_import_row`
WHERE `import_batch_id` = 920001;

DELETE FROM `nesp_staffing_import_batch`
WHERE `import_batch_id` = 920001;

DELETE FROM `nesp_scorecard_response`
WHERE `candidate_id` IN (920001, 920002, 920003, 920004);

DELETE FROM `nesp_interview`
WHERE `candidate_id` IN (920001, 920002, 920003, 920004);

DELETE FROM `nesp_interview_slot`
WHERE `interviewer_profile_id` IN (920001, 920002);

DELETE FROM `nesp_interviewer_availability`
WHERE `interviewer_profile_id` IN (920001, 920002);

DELETE FROM `nesp_interviewer_role_rule`
WHERE `interviewer_profile_id` IN (920001, 920002);

DELETE FROM `nesp_interviewer_candidate_grant`
WHERE `candidate_id` IN (920001, 920002, 920003, 920004);

DELETE FROM `nesp_candidate_workflow`
WHERE `candidate_id` IN (920001, 920002, 920003, 920004);

DELETE FROM `candidate_joborder`
WHERE `candidate_id` IN (920001, 920002, 920003, 920004);

INSERT INTO `joborder`
    (`joborder_id`, `entered_by`, `owner`, `title`, `description`, `type`, `status`, `openings`, `city`, `state`, `country`, `date_created`, `date_modified`, `public`, `openings_available`)
VALUES
    (920001, 1, 1, 'Fixture Staff Photographer', 'Synthetic NESP Phase 2 fixture role.', 'C', 'Active', 8, 'Methuen', 'MA', 'US', NOW(), NOW(), 0, 8),
    (920002, 1, 1, 'Fixture Photo Day Assistant', 'Synthetic NESP Phase 2 fixture role.', 'C', 'Active', 6, 'Methuen', 'MA', 'US', NOW(), NOW(), 0, 6)
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    date_modified = NOW();

INSERT INTO `candidate`
    (`candidate_id`, `last_name`, `first_name`, `phone_cell`, `source`, `notes`, `key_skills`, `entered_by`, `owner`, `date_created`, `date_modified`, `email1`, `is_active`)
VALUES
    (920001, 'Applicant', 'Avery', '555-0101', 'NESP fixture', 'Synthetic candidate for dashboard queue review.', 'Weekend availability; youth sports experience.', 1, 1, NOW(), NOW(), 'avery.fixture@example.test', 1),
    (920002, 'Candidate', 'Jordan', '555-0102', 'NESP fixture', 'Synthetic candidate waiting on applicant clarification.', 'Customer service; reliable transportation.', 1, 1, NOW(), NOW(), 'jordan.fixture@example.test', 1),
    (920003, 'Interviewer', 'Morgan', '555-0103', 'NESP fixture', 'Synthetic candidate assigned to interviewer.', 'School portraits; early morning availability.', 1, 1, NOW(), NOW(), 'morgan.fixture@example.test', 1),
    (920004, 'Complete', 'Taylor', '555-0104', 'NESP fixture', 'Synthetic completed scorecard candidate.', 'Photo day lead experience.', 1, 1, NOW(), NOW(), 'taylor.fixture@example.test', 1)
ON DUPLICATE KEY UPDATE
    notes = VALUES(notes),
    key_skills = VALUES(key_skills),
    date_modified = NOW();

INSERT INTO `candidate_joborder`
    (`candidate_id`, `joborder_id`, `status`, `date_submitted`, `date_created`, `date_modified`, `added_by`)
VALUES
    (920001, 920001, 400, NOW(), NOW(), NOW(), 1),
    (920002, 920002, 300, NOW(), NOW(), NOW(), 1),
    (920003, 920001, 500, NOW(), NOW(), NOW(), 1),
    (920004, 920001, 500, NOW(), NOW(), NOW(), 1);

INSERT INTO `nesp_candidate_workflow`
    (`candidate_id`, `joborder_id`, `workflow_stage_id`, `assigned_owner_user_id`, `waiting_on_key`, `summary`, `next_action_label`, `due_at`, `date_created`, `date_modified`)
SELECT 920001, 920001, `workflow_stage_id`, 1, 'Craig', 'New fixture application needs review for weekend photographer availability.', 'Review application', DATE_ADD(NOW(), INTERVAL 1 DAY), NOW(), NOW()
FROM `nesp_workflow_stage` WHERE `stage_key` = 'needs_review'
ON DUPLICATE KEY UPDATE `workflow_stage_id` = VALUES(`workflow_stage_id`), `summary` = VALUES(`summary`), `date_modified` = NOW();

INSERT INTO `nesp_candidate_workflow`
    (`candidate_id`, `joborder_id`, `workflow_stage_id`, `assigned_owner_user_id`, `waiting_on_key`, `summary`, `next_action_label`, `due_at`, `date_created`, `date_modified`)
SELECT 920002, 920002, `workflow_stage_id`, 1, 'Applicant', 'Waiting for fixture applicant to clarify Saturday and Sunday availability.', 'Check applicant reply', DATE_ADD(NOW(), INTERVAL 3 DAY), NOW(), NOW()
FROM `nesp_workflow_stage` WHERE `stage_key` = 'applicant_clarification_requested'
ON DUPLICATE KEY UPDATE `workflow_stage_id` = VALUES(`workflow_stage_id`), `summary` = VALUES(`summary`), `date_modified` = NOW();

INSERT INTO `nesp_candidate_workflow`
    (`candidate_id`, `joborder_id`, `workflow_stage_id`, `assigned_owner_user_id`, `waiting_on_key`, `summary`, `next_action_label`, `due_at`, `date_created`, `date_modified`)
SELECT 920003, 920001, `workflow_stage_id`, 1, 'Interviewer', 'Fixture interview is scheduled and needs scorecard completion.', 'Check scorecard', DATE_ADD(NOW(), INTERVAL 2 DAY), NOW(), NOW()
FROM `nesp_workflow_stage` WHERE `stage_key` = 'scorecard_pending'
ON DUPLICATE KEY UPDATE `workflow_stage_id` = VALUES(`workflow_stage_id`), `summary` = VALUES(`summary`), `date_modified` = NOW();

INSERT INTO `nesp_candidate_workflow`
    (`candidate_id`, `joborder_id`, `workflow_stage_id`, `assigned_owner_user_id`, `waiting_on_key`, `summary`, `next_action_label`, `due_at`, `date_created`, `date_modified`)
SELECT 920004, 920001, `workflow_stage_id`, 1, 'Craig', 'Completed fixture scorecard is ready for Craig decision.', 'Make decision', NOW(), NOW(), NOW()
FROM `nesp_workflow_stage` WHERE `stage_key` = 'scorecard_complete'
ON DUPLICATE KEY UPDATE `workflow_stage_id` = VALUES(`workflow_stage_id`), `summary` = VALUES(`summary`), `date_modified` = NOW();

INSERT INTO `nesp_interviewer_profile`
    (`interviewer_profile_id`, `user_id`, `display_name`, `email`, `role_key`, `is_active`, `can_view_resume`, `can_add_notes`, `can_submit_scorecard`, `date_created`, `date_modified`)
VALUES
    (920001, 1, 'Fixture Photographer Lead', 'fixture.photographer.lead@example.test', 'lead_interviewer', 1, 1, 1, 1, NOW(), NOW()),
    (920002, 1, 'Fixture Customer Service Reviewer', 'fixture.customer.service@example.test', 'interviewer', 1, 1, 1, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    display_name = VALUES(display_name),
    is_active = 1,
    date_modified = NOW();

INSERT INTO `nesp_interviewer_role_rule`
    (`interviewer_profile_id`, `joborder_id`, `role_match_text`, `assignment_mode`, `priority`, `is_active`, `notes`, `created_by_user_id`, `date_created`, `date_modified`)
VALUES
    (920001, NULL, 'photographer', 'suggest_only', 10, 1, 'Synthetic rule for staff and freelance photographer routing.', 1, NOW(), NOW()),
    (920002, NULL, 'customer service', 'suggest_only', 20, 1, 'Synthetic rule for customer service routing.', 1, NOW(), NOW());

INSERT INTO `nesp_interviewer_availability`
    (`interviewer_profile_id`, `weekday_key`, `start_time`, `end_time`, `timezone`, `slot_minutes`, `buffer_minutes`, `is_active`, `notes`, `created_by_user_id`, `date_created`, `date_modified`)
VALUES
    (920001, 'Tuesday', '17:00:00', '20:00:00', 'America/New_York', 30, 10, 1, 'Synthetic evening interview block.', 1, NOW(), NOW()),
    (920001, 'Saturday', '10:00:00', '13:00:00', 'America/New_York', 30, 10, 1, 'Synthetic weekend interview block.', 1, NOW(), NOW()),
    (920002, 'Wednesday', '09:00:00', '11:00:00', 'America/New_York', 30, 10, 1, 'Synthetic customer service interview block.', 1, NOW(), NOW());

INSERT INTO `nesp_interviewer_candidate_grant`
    (`interviewer_profile_id`, `candidate_id`, `joborder_id`, `granted_by_user_id`, `access_level_key`, `can_view_resume`, `can_add_notes`, `can_submit_scorecard`, `date_granted`, `date_revoked`)
VALUES
    (920001, 920003, 920001, 1, 'interview', 1, 1, 1, NOW(), NULL),
    (920001, 920004, 920001, 1, 'interview', 1, 1, 1, NOW(), NULL);

INSERT INTO `nesp_interview`
    (`candidate_id`, `joborder_id`, `interviewer_profile_id`, `scheduled_start`, `scheduled_end`, `status_key`, `date_created`, `date_modified`)
VALUES
    (920003, 920001, 920001, DATE_ADD(NOW(), INTERVAL 2 DAY), DATE_ADD(DATE_ADD(NOW(), INTERVAL 2 DAY), INTERVAL 30 MINUTE), 'scheduled', NOW(), NOW()),
    (920004, 920001, 920001, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_ADD(DATE_SUB(NOW(), INTERVAL 1 DAY), INTERVAL 30 MINUTE), 'complete', NOW(), NOW());

INSERT INTO `nesp_interview_slot`
    (`interviewer_profile_id`, `availability_id`, `candidate_id`, `joborder_id`, `scheduled_start`, `scheduled_end`, `slot_status_key`, `source_key`, `zoom_status_key`, `booking_token_hash`, `notes`, `created_by_user_id`, `date_created`, `date_modified`)
SELECT 920001, `availability_id`, NULL, NULL, DATE_ADD(DATE_ADD(CURDATE(), INTERVAL 5 DAY), INTERVAL 10 HOUR), DATE_ADD(DATE_ADD(DATE_ADD(CURDATE(), INTERVAL 5 DAY), INTERVAL 10 HOUR), INTERVAL 30 MINUTE), 'open', 'fixture', 'disabled', '', 'Synthetic open interview slot. No Zoom meeting exists.', 1, NOW(), NOW()
FROM `nesp_interviewer_availability`
WHERE `interviewer_profile_id` = 920001
  AND `weekday_key` = 'Saturday'
LIMIT 1;

INSERT INTO `nesp_staffing_schedule_history`
    (`season_year`, `season_name`, `week_start`, `event_count`, `photographer_slots`, `photographer_hours`, `source_label`, `notes`, `date_created`, `date_modified`)
VALUES
    (2024, 'spring fixture', '2024-04-15', 5, 11, 58.0, 'synthetic Craig schedule fixture', 'Opening spring fixture week.', NOW(), NOW()),
    (2024, 'spring fixture', '2024-04-22', 8, 18, 94.5, 'synthetic Craig schedule fixture', 'Busy youth sports picture days.', NOW(), NOW()),
    (2024, 'spring fixture', '2024-04-29', 10, 24, 128.0, 'synthetic Craig schedule fixture', 'Peak spring fixture week.', NOW(), NOW()),
    (2024, 'spring fixture', '2024-05-06', 12, 30, 162.0, 'synthetic Craig schedule fixture', 'Peak spring fixture week.', NOW(), NOW()),
    (2024, 'spring fixture', '2024-05-13', 9, 22, 118.0, 'synthetic Craig schedule fixture', 'Weather movement week.', NOW(), NOW()),
    (2024, 'fall fixture', '2024-09-09', 7, 16, 82.0, 'synthetic Craig schedule fixture', 'Fall ramp fixture week.', NOW(), NOW()),
    (2024, 'fall fixture', '2024-09-16', 9, 21, 112.0, 'synthetic Craig schedule fixture', 'Fall peak fixture week.', NOW(), NOW()),
    (2024, 'fall fixture', '2024-09-23', 11, 27, 143.5, 'synthetic Craig schedule fixture', 'Fall peak fixture week.', NOW(), NOW());

INSERT INTO `nesp_staffing_import_batch`
    (`import_batch_id`, `source_type`, `source_identifier`, `source_checksum`, `source_label`, `status_key`, `discovered_file_count`, `imported_file_count`, `rows_imported`, `rows_requiring_review`, `created_by_user_id`, `last_imported_at`, `date_created`, `date_modified`)
VALUES
    (920001, 'fixture_csv', 'src/OpenCATS/Tests/Fixtures/nesp/staffing_dates_in_rows.csv', SHA2('nesp fixture staffing import', 256), 'Synthetic local staffing import fixture', 'imported', 1, 1, 4, 1, 1, NOW(), NOW(), NOW());

INSERT INTO `nesp_staffing_import_row`
    (`import_batch_id`, `source_row_hash`, `source_sheet_name`, `source_row_number`, `event_date`, `event_start_time`, `event_end_time`, `state`, `sport`, `event_name`, `role_key`, `staff_name`, `staff_count`, `staff_hours`, `raw_source_text`, `unresolved_json`, `issue_count`, `status_key`, `date_created`)
VALUES
    (920001, SHA2('fixture-row-1', 256), 'fixture', 2, '2024-04-20', '08:00:00', '12:00:00', 'MA', 'Soccer', 'Fixture League A', 'photographer', 'Alex Fixture; Sam Fixture', 2, 8.00, 'synthetic row 1', '{}', 0, 'normalized', NOW()),
    (920001, SHA2('fixture-row-2', 256), 'fixture', 3, '2024-04-21', '09:00:00', '13:00:00', 'NH', 'Baseball', 'Fixture League B', 'assistant', 'Jordan Fixture', 1, 4.00, 'synthetic row 2', '{}', 0, 'normalized', NOW()),
    (920001, SHA2('fixture-row-3', 256), 'fixture', 4, '2025-05-10', '07:30:00', '12:30:00', 'MA', 'Lacrosse', 'Fixture League C', 'table_staff', 'Taylor Fixture; Casey Fixture', 2, 10.00, 'synthetic row 3', '{}', 0, 'normalized', NOW()),
    (920001, SHA2('fixture-row-4', 256), 'fixture', 5, NULL, '08:00:00', '11:00:00', 'RI', 'Softball', 'Fixture Needs Review', 'photographer', 'Review Fixture', 1, 3.00, 'synthetic malformed date row', '{"date":"malformed"}', 1, 'needs_review', NOW());

INSERT INTO `nesp_staffing_import_issue`
    (`import_batch_id`, `import_row_id`, `issue_key`, `severity_key`, `message`, `status_key`, `date_created`)
SELECT 920001, `import_row_id`, 'missing_or_malformed_date', 'review', 'Synthetic fixture row has a malformed date for import issue review.', 'open', NOW()
FROM `nesp_staffing_import_row`
WHERE `import_batch_id` = 920001
  AND `source_row_number` = 5;
