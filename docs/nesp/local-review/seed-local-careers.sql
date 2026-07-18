-- NESP protected career portal seed for disposable local OpenCATS review databases.
-- Do not use real applicant data. Do not enable outbound email.

START TRANSACTION;

DELETE FROM career_portal_template_site
WHERE career_portal_name IN ('NESP Local Review', 'NESP Careers');

UPDATE site
SET name = 'New England Sports Photo'
WHERE site_id = 1;

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
  ('activeBoard', 'NESP Careers', 4),
  ('allowXMLSubmit', '0', 4),
  ('useCATSTemplate', '', 4);

INSERT INTO career_portal_template_site (career_portal_name, setting, value) VALUES
  ('NESP Careers', 'Header', '<nav class="nesp-nav" aria-label="Careers navigation">
    <a class="nesp-button nesp-button-secondary" href="index.php?m=careers&p=showAll">Back to Current Opportunities</a>
  </nav>'),
  ('NESP Careers', 'Footer', ''),
  ('NESP Careers', 'Content - Main', '<main id="careerContent" class="nesp-main">
  <registeredCandidate>
  <section class="nesp-intro">
    <p class="nesp-kicker">Careers</p>
    <h2>Join the New England Sports Photo Team</h2>
    <p>Family-owned since 1975, New England Sports Photo works with youth sports organizations throughout New England. We are looking for reliable, energetic team members who enjoy working with athletes, families, and local sports communities.</p>
    <a class="nesp-button" href="index.php?m=careers&p=showAll">View Current Opportunities</a>
  </section>
</main>'),
  ('NESP Careers', 'Content - Search Results', '<main id="careerContent" class="nesp-main">
  <registeredCandidate>
  <section class="nesp-section-heading">
    <p class="nesp-kicker">Careers</p>
    <h2>Current Opportunities</h2>
    <p>Family-owned since 1975, New England Sports Photo works with youth sports organizations throughout New England. We are looking for reliable, energetic team members who enjoy working with athletes, families, and local sports communities.</p>
  </section>
  <searchResultsTable cards >
</main>'),
  ('NESP Careers', 'Content - Job Details', '<main id="careerContent" class="nesp-main nesp-job-detail">
  <registeredCandidate>
  <a class="nesp-link" href="index.php?m=careers&p=showAll">Back to Current Opportunities</a>
  <section class="nesp-section-heading">
    <p class="nesp-kicker">Position details</p>
    <h2><title></h2>
  </section>
  <div class="nesp-detail-grid">
    <section class="nesp-detail-body">
      <dl class="nesp-facts">
        <div><dt>Pay</dt><dd><salary></dd></div>
        <div><dt>Location</dt><dd><location></dd></div>
        <div><dt>Classification</dt><dd><classification></dd></div>
        <div><dt>Schedule</dt><dd><schedule></dd></div>
        <div><dt>Season</dt><dd><season></dd></div>
      </dl>
      <div class="nesp-description"><description></div>
    </section>
    <aside class="nesp-action-panel">
      <p>Ready to apply?</p>
      <a-applyToJob class="nesp-button">Apply Now</a>
      <a class="nesp-button nesp-button-secondary" href="index.php?m=careers&p=showAll">Back to Current Opportunities</a>
    </aside>
  </div>
</main>'),
  ('NESP Careers', 'Content - Apply for Position', '<main id="careerContent" class="nesp-main nesp-application">
  <a class="nesp-link" href="index.php?m=careers&p=showJob&ID=<jobid>">Back to Position Details</a>
  <section class="nesp-section-heading">
    <p class="nesp-kicker">Application</p>
    <h2>Applying to: <title></h2>
    <p>Applications are reviewed by the New England Sports Photo hiring team.</p>
  </section>
  <catsform>
  <div class="nesp-form-grid">
    <section class="nesp-form-panel">
      <h3>Resume or work history</h3>
      <input-resumeUpload>
    </section>
    <section class="nesp-form-panel">
      <h3>About you</h3>
      <label id="firstNameLabel" for="firstName">First Name *</label>
      <input-firstName>
      <label id="lastNameLabel" for="lastName">Last Name *</label>
      <input-lastName>
      <label id="emailLabel" for="email">Email Address *</label>
      <input-email>
      <label id="emailConfirmLabel" for="emailconfirm">Confirm Email *</label>
      <input-emailconfirm>
    </section>
    <section class="nesp-form-panel">
      <h3>Contact details</h3>
      <label id="homePhoneLabel" for="homePhone">Home Phone</label>
      <input-phone-home>
      <label id="mobilePhoneLabel" for="mobilePhone">Mobile Phone</label>
      <input-phone-cell>
      <label id="workPhoneLabel" for="workPhone">Work Phone</label>
      <input-phone>
      <label id="bestTimeLabel" for="bestTime">Best time to call *</label>
      <input-best-time-to-call>
      <label id="mailingAddressLabel" for="mailingAddress">Mailing Address</label>
      <input-address>
      <label id="cityProvinceLabel" for="cityProvince">City/Province *</label>
      <input-city>
      <label id="stateCountryLabel" for="stateCountry">State/Country *</label>
      <input-state>
      <input-country>
      <label id="zipPostalLabel" for="zipPostal">Zip/Postal Code *</label>
      <input-zip>
    </section>
    <section class="nesp-form-panel">
      <h3>Additional information</h3>
      <label id="keySkillsLabel" for="keySkills">Relevant skills or availability *</label>
      <input-keySkills>
      <label id="captchaLabel" for="captcha">Captcha *</label>
      <input-captcha req>
      <div class="nesp-form-actions">
        <submit value="Apply Now">
        <a class="nesp-button nesp-button-secondary" href="index.php?m=careers&p=showAll">Back to Current Opportunities</a>
      </div>
    </section>
  </div>
  </form>
</main>'),
  ('NESP Careers', 'Content - Questionnaire', '<main id="careerContent" class="nesp-main">
  <section class="nesp-form-panel">
    <questionnaire>
    <div class="nesp-form-actions"><submit value="Continue"></div>
  </section>
</main>'),
  ('NESP Careers', 'Content - Thanks for your Submission', '<main id="careerContent" class="nesp-main">
  <section class="nesp-section-heading">
    <h2>Application Submitted For: <title></h2>
    <p>Applications are reviewed by the New England Sports Photo hiring team.</p>
    <a class="nesp-button" href="index.php?m=careers&p=showAll">Back to Current Opportunities</a>
  </section>
</main>'),
  ('NESP Careers', 'Content - Candidate Registration', ''),
  ('NESP Careers', 'Content - Candidate Profile', ''),
  ('NESP Careers', 'CSS', '/* NESP visual styles are loaded from modules/careers/nespCareers.css. */');

INSERT INTO joborder (
  joborder_id, recruiter, contact_id, company_id, entered_by, owner, client_job_id,
  title, description, notes, type, duration, rate_max, salary, status, is_hot,
  openings, city, state, country, start_date, date_created, date_modified, public,
  company_department_id, is_admin_hidden, openings_available, questionnaire_id, import_id
) VALUES
  (41001, 10008, NULL, 20001, 10008, 10008, 'NESP-CSR',
   'Part-Time Customer Service Representative',
   'Quick Facts

Pay: $22-$25 per hour, based on experience
Location: In person at the NESP office in Methuen, Massachusetts
Employment type: Part-time, year-round W-2
Typical schedule: Monday-Friday, agreed set schedule, generally 9:00 a.m.-3:00 p.m. or 10:00 a.m.-4:00 p.m.
Availability: Year-round, approximately 20-30 hours per week

New England Sports Photo is a family-owned youth sports photography company with more than 50 years of experience serving leagues and families throughout New England.

We are looking for a friendly, organized customer service representative to help parents, families, league coordinators, and school contacts get clear answers about orders, delivery, accounts, and reprints. This is a good fit for someone who enjoys solving practical problems, writing clear messages, and working with a small supportive office team.

Why This Role May Be a Good Fit

- Year-round weekday schedule with approximately 20-30 hours per week
- No cold sales calls
- Paid training and clear internal processes
- Support from Craig and the NESP office team when issues need escalation
- A chance to help families resolve real order and delivery questions
- In-person work with a steady, focused customer-service rhythm

What You''ll Do

- Respond to customer emails and support tickets
- Answer and return customer calls
- Help with online ordering and account questions
- Research shipping, delivery, and order-status questions
- Process approved reprints, replacements, and order corrections
- Enter and update order information accurately
- Assist league and school coordinators
- Escalate complex or sensitive issues to Craig
- Process payments only when authorized and trained
- Complete light administrative tasks and occasionally help with packing or shipping during peak periods

What We''re Looking For

- Customer-service or administrative experience preferred
- Strong written and verbal communication
- Friendly, patient, and professional approach
- Comfort with email, spreadsheets, support systems, and web applications
- Organized, detail-oriented work habits
- Ability to manage several tasks during busy periods
- Comfort working with a small office team
- Bilingual ability helpful but not required

What NESP Provides

NESP provides paid training, established order-support processes, office systems, escalation support, and guidance on company products, packages, ordering tools, reprints, and shipping questions.

Schedule and Work Expectations

This role is based in the Methuen office. The schedule is set by agreement and is generally daytime weekday work throughout the year. Packing and shipping may come up occasionally during busy periods, but this is a customer-service role first.

Apply Now

Apply with your work history, weekday availability, customer-service or office experience, and a brief note about why you would be dependable in a customer-facing support role.

Applications are reviewed by the New England Sports Photo hiring team.',
   'Protected review seed. No real applicant data.', 'H', 'Part-time year-round W-2', '', '$22-$25 per hour', 'Active', 0, 1, 'Methuen', 'MA', 'US', NULL, NOW(), NOW(), 1, 0, 0, 1, NULL, 0),
  (41002, 10008, NULL, 20001, 10008, 10008, 'NESP-W2-PHOTO',
   'Weekend Staff Portrait & Team Photographer - Youth Sports',
   'Quick Facts

Pay: $22-$25 per hour, based on experience
Territory: Primarily Massachusetts, with additional assignments in Connecticut, Rhode Island, Vermont, and New Hampshire
Employment type: Part-time, temporary, seasonal W-2
Typical schedule: Most assignments are on Saturdays, with some Sundays and early-morning weekend availability
Season: September-November and April-June; optional after-school weekday assignments and future seasonal opportunities may be available

New England Sports Photo is a family-owned youth sports photography company with more than 50 years of experience serving leagues and families throughout New England.

We are hiring weekend staff photographers to create individual portraits and team photographs at youth-sports Picture Day events. Photography experience is helpful, but the bigger requirements are reliability, a professional attitude, comfort working with children and families, and willingness to learn a structured Picture Day system.

Why This Role May Be a Good Fit

- No personal camera equipment required
- Professional equipment supplied by NESP
- Paid training provided
- Learn a repeatable Picture Day workflow
- Work with youth athletes and community sports leagues
- Seasonal schedule with opportunities to return

What You''ll Do

- Photograph individual athletes and teams using NESP equipment
- Set up cameras, lighting, and related gear
- Represent NESP professionally at assigned locations
- Work comfortably with athletes, parents, coaches, and league staff
- Assist with setup and breakdown
- Follow the NESP Picture Day workflow
- Help keep events organized and on schedule

What We''re Looking For

- Valid driver''s license
- Reliable personal transportation
- Ability to travel to assigned locations
- Comfort working with children and families
- Strong communication and organizational skills
- Dependability for accepted assignments
- Ability to learn technical skills and follow a structured process
- Ability to pass required background checks and youth-sports screenings

What NESP Provides

NESP provides the camera equipment and lighting used for staff photographer assignments, along with paid training, event workflow guidance, and Picture Day processes. You do not need to provide your own camera gear for this W-2 staff role.

Schedule and Travel Expectations

Most assignments are Saturday mornings, with some Sundays. Optional weekday after-school assignments may be available. Paid drive time is handled in accordance with NESP policy.

Apply Now

Apply with your availability, location, transportation information, and any photography, youth-sports, school, event, or customer-facing experience.

Applications are reviewed by the New England Sports Photo hiring team.',
   'Protected review seed. No real applicant data.', 'H', 'Seasonal W-2', '', '$22-$25 per hour', 'Active', 0, 1, 'New England', '', 'US', NULL, NOW(), NOW(), 1, 0, 0, 1, NULL, 0),
  (41003, 10008, NULL, 20001, 10008, 10008, 'NESP-FREELANCE',
   'Freelance/Contract Youth Sports Photographer',
   'Quick Facts

Pay: $22-$27 per hour, based on experience
Territory: Massachusetts, Rhode Island, New Hampshire, and Connecticut
Employment type: Freelance/Independent Contractor
Typical schedule: Primarily weekends, usually from morning through early or mid-afternoon; optional after-school weekday assignments may be available
Season: Part-time seasonal contract assignments are generally available September-November and April-June

New England Sports Photo is a family-owned youth sports photography company with more than 50 years of experience serving leagues and families throughout New England.

We are looking for experienced freelance photographers who enjoy efficient portrait and team photography in active outdoor youth-sports settings. This role is best suited for photographers who already own approved professional equipment, understand camera and flash settings, and want recurring seasonal assignments with a structured workflow.

Why This Role May Be a Good Fit

- Use your own approved professional equipment
- Add recurring seasonal weekend assignments
- Work with established youth-sports organizations
- Follow a clear Picture Day process instead of guessing on site
- Build on portrait, school, event, sports, or volume-photography experience
- Work with clear expectations and organized event procedures

What You''ll Do

- Photograph individual player portraits and team photographs
- Use on-camera flash appropriately during outdoor sessions
- Travel to assigned youth-sports locations
- Follow NESP''s structured Picture Day process
- Adjust camera and flash settings as conditions change
- Work efficiently while maintaining a positive experience for families and leagues

What We''re Looking For

- Working knowledge of shutter speed, aperture, ISO, and flash
- Comfort working with children and families
- Friendly, energetic, professional, and dependable approach
- Ability to work early weekend assignments
- Ability to follow structured processes
- Willingness to learn NESP techniques
- Reliable transportation

Equipment You Provide

Freelance photographers provide their own approved equipment. Expected equipment includes an approved professional or advanced camera body, mirrorless preferred with appropriate DSLR bodies also considered; approximately 20MP or higher; an external speedlight with manual and TTL operation; and a portrait zoom covering approximately 24-70mm through 24-120mm full-frame equivalent.

You also need a reliable vehicle capable of safely transporting required equipment.

What NESP Provides

NESP provides the Picture Day workflow, assignment details, on-site process expectations, and guidance on NESP techniques. Staff/W-2 photographers use NESP equipment; freelance photographers provide their own approved equipment.

Schedule and Travel Expectations

Assignments are primarily weekends and are commonly about 60-90 minutes from home. Optional after-school weekday work may be available. Travel and paid-time terms are handled according to the approved contractor assignment terms for the work offered.

Apply Now

Apply with your location, availability, equipment list, transportation information, and a link or description of relevant portrait, event, school, sports, or volume-photography experience.

Applications are reviewed by the New England Sports Photo hiring team.',
   'Protected review seed. No real applicant data.', 'FL', 'Freelance/Independent Contractor', '', '$22-$27 per hour', 'Active', 0, 1, 'MA, RI, NH, CT', '', 'US', NULL, NOW(), NOW(), 1, 0, 0, 1, NULL, 0),
  (41005, 10008, NULL, 20001, 10008, 10008, 'NESP-FIELD',
   'Weekend Table Greeter / Field Assistant',
   'Quick Facts

Pay: $18 per hour
Territory: Primarily Massachusetts, with additional events in Connecticut, Rhode Island, and New Hampshire
Work location: Youth-sports fields and facilities
Employment type: Part-time, temporary, seasonal W-2
Typical schedule: Primarily Saturdays, with some Sundays and early-morning availability
Season: September-November and April-June; training and early assignments may begin in April

New England Sports Photo is a family-owned youth sports photography company with more than 50 years of experience serving leagues and families throughout New England.

We are hiring friendly, organized field assistants to help Picture Day run smoothly at youth-sports events. This is an active weekend role for someone who enjoys helping people, keeping details straight, and supporting a photographer and event team in the field.

You will often be one of the first NESP team members families meet when they arrive. A calm, welcoming attitude and careful attention to names, teams, forms, and player numbers help the whole event stay on track.

Why This Role May Be a Good Fit

- No prior experience required
- Training provided
- Active weekend work
- Paid drive time both directions
- Work with a supportive Picture Day team
- Clear posing and workflow guides
- Flexible seasonal scheduling

What You''ll Do

- Welcome and check in families
- Organize teams and manage Picture Day flow
- Guide athletes using NESP posing guides
- Review order forms for completeness and accuracy
- Assign player number codes carefully
- Help keep portrait and team sessions on schedule
- Support photographers
- Assist with reasonable setup and breakdown duties

What We''re Looking For

- Friendly and professional communication
- Organized, detail-oriented work habits
- Comfort directing groups
- Reliability and punctuality
- Comfort working outdoors
- Reliable transportation and a valid driver''s license
- Ability to stand for extended periods
- Ability to lift up to 25 pounds
- Ability to pass required background checks

What NESP Provides

NESP provides training, Picture Day workflow guidance, posing guides, and support from the event team. You do not need previous photography experience for this role.

Schedule and Travel Expectations

Most assignments are early weekend events, especially Saturdays, during the busiest late-April through early-June period. Paid drive time is provided both directions.

Apply Now

Apply with your location, weekend availability, transportation information, and any customer-service, event, coaching, childcare, camp, school, retail, or team-support experience that shows reliability and comfort helping people.

Applications are reviewed by the New England Sports Photo hiring team.',
   'Protected review seed. No real applicant data.', 'H', 'Seasonal W-2', '', '$18 per hour', 'Active', 0, 1, 'New England', '', 'US', NULL, NOW(), NOW(), 1, 0, 0, 1, NULL, 0)
ON DUPLICATE KEY UPDATE
  client_job_id = VALUES(client_job_id),
  title = VALUES(title),
  description = VALUES(description),
  notes = VALUES(notes),
  type = VALUES(type),
  duration = VALUES(duration),
  salary = VALUES(salary),
  status = VALUES(status),
  city = VALUES(city),
  state = VALUES(state),
  country = VALUES(country),
  public = VALUES(public),
  openings_available = VALUES(openings_available),
  date_modified = NOW();

UPDATE joborder
SET status = 'Inactive',
    public = 0,
    openings_available = 0,
    date_modified = NOW()
WHERE joborder_id = 41004;

COMMIT;
