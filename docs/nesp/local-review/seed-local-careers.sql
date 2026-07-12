-- NESP local-only career portal seed for disposable OpenCATS review databases.
-- Do not run this against production. Do not use it to publish job ads.
-- It creates no applicant records and does not enable outbound email.

START TRANSACTION;

DELETE FROM candidate_joborder WHERE joborder_id BETWEEN 41001 AND 41005;
DELETE FROM activity WHERE data_item_type = 400 AND data_item_id BETWEEN 41001 AND 41005;
DELETE FROM attachment WHERE data_item_type = 400 AND data_item_id BETWEEN 41001 AND 41005;
DELETE FROM joborder WHERE joborder_id BETWEEN 41001 AND 41005;

DELETE FROM settings
WHERE settings_type = 4
  AND setting IN (
    'enabled',
    'allowBrowse',
    'candidateRegistration',
    'showDepartment',
    'showCompany',
    'activeBoard',
    'allowXMLSubmit',
    'useCATSTemplate'
  );

INSERT INTO settings (setting, value, settings_type) VALUES
  ('enabled', '1', 4),
  ('allowBrowse', '1', 4),
  ('candidateRegistration', '0', 4),
  ('showDepartment', '0', 4),
  ('showCompany', '0', 4),
  ('activeBoard', 'CATS 2.0', 4),
  ('allowXMLSubmit', '0', 4),
  ('useCATSTemplate', '', 4);

INSERT INTO joborder (
  joborder_id,
  recruiter,
  contact_id,
  company_id,
  entered_by,
  owner,
  client_job_id,
  title,
  description,
  notes,
  type,
  duration,
  rate_max,
  salary,
  status,
  is_hot,
  openings,
  city,
  state,
  country,
  start_date,
  date_created,
  date_modified,
  public,
  company_department_id,
  is_admin_hidden,
  openings_available,
  questionnaire_id,
  import_id
) VALUES
  (
    41001,
    10008,
    NULL,
    20001,
    10008,
    10008,
    'NESP-LOCAL-CSR',
    'Part-Time Customer Service Representative',
    'LOCAL REVIEW ONLY - draft job listing, not published.

New England Sports Photo is hiring a part-time customer service representative to support parents, families, league coordinators, school coordinators, and NESP staff from the Methuen office.

Pay: $22-$25 per hour, based on experience, pending final payroll/legal confirmation.
Location: Methuen, MA - in office.
Schedule: Flexible weekday hours, with additional hours during busy spring and fall sports seasons.
Classification: W-2 part-time role, pending final payroll/legal confirmation.

What You Will Do:
- Respond to customer emails and support tickets.
- Help parents with online ordering and account questions.
- Handle shipping, delivery, and order-status questions.
- Process reprint and replacement requests according to company policy.
- Support league and school coordinators.
- Escalate complex or sensitive issues to Craig.
- Learn NESP products, packages, and ordering systems.
- Maintain friendly, professional customer communication.

What We Are Looking For:
- Excellent written and verbal communication.
- Strong computer skills.
- Organized and detail-oriented work habits.
- Ability to multitask during busy seasons.
- Customer service experience is preferred.
- Sports photography experience is helpful but not required.
- Ability to work in office from Methuen, MA.
- Good judgment about when to escalate sensitive or unusual issues.

To Apply:
Apply with your work history, Methuen office availability, customer service or office experience, computer skills, and a brief note about why you would be dependable in a customer-facing support role.

Internal Approval Notes:
Draft only. Do not publish until Craig approves the final posting. W-2 classification, payroll setup, overtime rules, schedule, and any background-check requirements remain pending final payroll/legal confirmation. Applicant communications remain draft-only until approved by a human. AI may summarize job-related application information, but NESP management makes every interview and hiring decision.',
    'Local preview seed. No real applicant data.',
    'H',
    'Part-time',
    '',
    '$22-$25 per hour',
    'Active',
    0,
    1,
    'Methuen',
    'MA',
    'US',
    NULL,
    NOW(),
    NOW(),
    1,
    0,
    0,
    1,
    NULL,
    0
  ),
  (
    41002,
    10008,
    NULL,
    20001,
    10008,
    10008,
    'NESP-LOCAL-W2-PHOTO',
    'W-2 Seasonal Youth Sports Photographer',
    'LOCAL REVIEW ONLY - draft job listing, not published.

New England Sports Photo is hiring dependable seasonal photographers for youth sports picture days across New England.

Pay: $22-$25 per hour, based on experience, pending final payroll/legal confirmation.
Locations: Massachusetts, Rhode Island, New Hampshire, and Connecticut.
Primary season: September through November.
Classification: Seasonal W-2 role, pending final payroll/legal confirmation.

Schedule:
- Most fall assignments run September through November.
- Weekday assignments are usually after approximately 4:00 PM.
- Weekend assignments are generally early morning through noon.
- Paid time begins when you leave home and continues through completion of the assignment.
- Return travel is paid when the drive home exceeds one hour.

What You Will Do:
- Photograph individual athletes, teams, coaches, and league staff.
- Use NESP-provided camera and lighting equipment for assigned W-2 work.
- Follow NESP standards for posing, lighting, camera settings, naming, and file handling.
- Direct children and groups clearly, respectfully, and efficiently.
- Work with assistants, table staff, coaches, league contacts, and NESP leads.
- Protect image files and complete end-of-assignment transfer steps.

What We Are Looking For:
- Portrait, sports, school, event, studio, or volume-photography experience.
- Reliable transportation to assigned photo locations.
- Availability on selected weekday evenings and weekend mornings during the fall season.
- Ability to complete required background-check steps for field work.

Internal Approval Notes:
Draft only. Do not publish until Craig approves the final posting. W-2 classification, payroll setup, overtime, travel pay, and background-check process remain pending final payroll/legal confirmation. AI may summarize job-related application information, but NESP management makes every interview, assignment, and hiring decision.',
    'Local preview seed. No real applicant data.',
    'H',
    'Seasonal',
    '',
    '$22-$25 per hour',
    'Active',
    0,
    1,
    'MA, RI, NH, CT',
    '',
    'US',
    NULL,
    NOW(),
    NOW(),
    1,
    0,
    0,
    1,
    NULL,
    0
  ),
  (
    41003,
    10008,
    NULL,
    20001,
    10008,
    10008,
    'NESP-LOCAL-FREELANCE',
    'Freelance/Contract Youth Sports Photographer',
    'LOCAL REVIEW ONLY - blocked from publication pending contractor-classification review.

This listing is visible only in the disposable local review database. It must not be published until the final independent-contractor classification review is complete for the actual work arrangement and state where the work is performed.

New England Sports Photo is building a network of experienced freelance photographers for youth sports picture-day assignments across New England.

Pay: $22-$27 per hour, based on experience, pending final payroll/legal classification confirmation.
Locations: Massachusetts, Rhode Island, New Hampshire, and Connecticut.
Primary season: September through November.
Classification: Seasonal contract assignments, pending final payroll/legal classification confirmation.

Schedule:
- Most fall assignments run September through November.
- Weekday assignments are usually after approximately 4:00 PM.
- Weekend assignments are generally early morning through noon.
- Assignments are offered based on location, experience, availability, and client schedule.
- Paid time begins when you leave home and continues through completion of the assignment.
- Return travel is paid when the drive home exceeds one hour.

Required Equipment:
Contract photographers must provide their own professional equipment, including a professional DSLR or mirrorless camera body, appropriate portrait and sports lenses, external flash or lighting equipment suitable for the assignment, memory cards, batteries, chargers, basic field supplies, and backup camera equipment in case of failure.

What We Are Looking For:
- Strong portfolio or sample images showing relevant work.
- Experience with portrait, sports, school, event, studio, or volume photography.
- Professional reliability and communication.
- Reliable transportation to assigned photo locations.
- Availability on selected weekday evenings and weekend mornings during the fall season.
- Ability to complete required background-check steps for field work.

Internal Approval Notes:
Draft only. Do not publish until Craig approves the final posting. Contractor classification, contract terms, insurance expectations, payment timing, travel pay, and background-check process remain pending final payroll/legal confirmation. AI may summarize job-related application information, but NESP management makes every assignment and hiring decision.',
    'Local preview seed. Blocked from publication pending classification review.',
    'FL',
    'Seasonal contract',
    '',
    '$22-$27 per hour',
    'Active',
    0,
    1,
    'MA, RI, NH, CT',
    '',
    'US',
    NULL,
    NOW(),
    NOW(),
    1,
    0,
    0,
    1,
    NULL,
    0
  ),
  (
    41004,
    10008,
    NULL,
    20001,
    10008,
    10008,
    'NESP-LOCAL-POSER',
    'Photography Assistant/Poser',
    'LOCAL REVIEW ONLY - draft job listing, not published.

New England Sports Photo is hiring dependable photography assistants/posers to help photographers create smooth, organized youth sports picture days.

Pay: $20 per hour, pending final payroll/legal confirmation.
Locations: Massachusetts, Rhode Island, New Hampshire, and Connecticut.
Primary season: September through November.

Schedule:
- Most fall assignments run September through November.
- Weekday assignments are usually after approximately 4:00 PM.
- Weekend assignments are generally early morning through noon.
- Paid time begins when you leave home and continues through completion of the assignment.
- Return travel is paid when the drive home exceeds one hour.

What You Will Do:
- Help set up and break down posing areas, lights, signs, and supplies.
- Organize athletes, teams, coaches, and families before they enter the camera area.
- Guide people into approved poses using NESP standards.
- Help photographers move efficiently from one athlete or team to the next.
- Keep lines safe, orderly, and positive.
- Support table staff and team leads when schedules become busy.

What We Are Looking For:
- Dependable attendance and punctuality.
- Confidence speaking with children, teenagers, adults, coaches, and league staff.
- Strong attention to visual details.
- Reliable transportation to assigned photo locations.
- Availability on selected weekday evenings and weekend mornings during the fall season.
- Ability to complete required background-check steps for field work.

Internal Approval Notes:
Draft only. Do not publish until Craig approves the final posting. Employment classification, payroll setup, overtime, travel pay, and background-check process remain pending final payroll/legal confirmation. AI may summarize job-related application information, but NESP management makes every interview, assignment, and hiring decision.',
    'Local preview seed. No real applicant data.',
    'H',
    'Seasonal',
    '',
    '$20 per hour',
    'Active',
    0,
    1,
    'MA, RI, NH, CT',
    '',
    'US',
    NULL,
    NOW(),
    NOW(),
    1,
    0,
    0,
    1,
    NULL,
    0
  ),
  (
    41005,
    10008,
    NULL,
    20001,
    10008,
    10008,
    'NESP-LOCAL-TABLE',
    'On-Site Picture Day Table Staff',
    'LOCAL REVIEW ONLY - draft job listing, not published.

New England Sports Photo is hiring organized, friendly on-site picture day table staff to help run smooth youth sports photo days.

This is not a photography position.

Pay: $20 per hour, pending final payroll/legal confirmation.
Locations: Massachusetts, Rhode Island, New Hampshire, and Connecticut.
Primary season: September through November.

Schedule:
- Most fall assignments run September through November.
- Weekday assignments are usually after approximately 4:00 PM.
- Weekend assignments are generally early morning through noon.
- Paid time begins when you leave home and continues through completion of the assignment.
- Return travel is paid when the drive home exceeds one hour.

What You Will Do:
- Set up and organize the registration, check-in, and information table.
- Greet families, athletes, coaches, and league staff.
- Help people understand the picture-day process and where to go next.
- Confirm names, teams, order information, and other required details.
- Keep paperwork and digital check-in information accurate and organized.
- Answer basic questions and direct support issues to the correct NESP team member.
- Help teams arrive in the correct order and keep the schedule moving.

What We Are Looking For:
- Excellent attendance and punctuality.
- Friendly, confident communication.
- Strong attention to names, teams, numbers, and written details.
- Comfort using a tablet, phone, or simple check-in system.
- Reliable transportation to assigned photo locations.
- Availability on selected weekday evenings and weekend mornings during the fall season.
- Ability to complete required background-check steps for field work.

Internal Approval Notes:
Draft only. Do not publish until Craig approves the final posting. Employment classification, payroll setup, overtime, travel pay, and background-check process remain pending final payroll/legal confirmation. AI may summarize job-related application information, but NESP management makes every interview, assignment, and hiring decision.',
    'Local preview seed. No real applicant data.',
    'H',
    'Seasonal',
    '',
    '$20 per hour',
    'Active',
    0,
    1,
    'MA, RI, NH, CT',
    '',
    'US',
    NULL,
    NOW(),
    NOW(),
    1,
    0,
    0,
    1,
    NULL,
    0
  );

COMMIT;

