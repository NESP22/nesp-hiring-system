/* NESP Phase 2 fake fixture data.
 *
 * Use only in local/test databases. Do not run against production. Names,
 * emails, schedules, and staffing history are synthetic.
 */

DELETE FROM `nesp_staffing_schedule_history`
WHERE `source_label` = 'synthetic Craig schedule fixture';

DELETE FROM `nesp_scorecard_response`
WHERE `candidate_id` IN (920001, 920002, 920003, 920004);

DELETE FROM `nesp_interview`
WHERE `candidate_id` IN (920001, 920002, 920003, 920004);

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
    (920001, 1, 'Fixture Interviewer', 'fixture.interviewer@example.test', 'interviewer', 1, 1, 1, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    display_name = VALUES(display_name),
    is_active = 1,
    date_modified = NOW();

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
