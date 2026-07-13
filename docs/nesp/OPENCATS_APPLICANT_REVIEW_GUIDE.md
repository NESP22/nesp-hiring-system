# OpenCATS Applicant Review Guide

This guide is for Craig's manual review of applicants in the NESP hiring portal.
It does not authorize automatic rejection, selection, hiring, status changes,
assignment, or outbound email.

Backend login:

`https://careers.nesportsphoto.com/index.php?m=login`

## Where New Applicants Appear

1. Log in to OpenCATS with the administrator account.
2. Open the main dashboard.
3. Check the Candidates area for newly submitted applicants.
4. Check the Jobs / Job Orders area for applicant activity under each public
   role:
   - `41001` Part-Time Customer Service Representative
   - `41002` Weekend Staff Portrait & Team Photographer - Youth Sports
   - `41003` Freelance/Contract Youth Sports Photographer
   - `41005` Weekend Table Greeter / Field Assistant

## Opening An Application

1. From Candidates, search by applicant name, email, or recent submission date.
2. Open the candidate profile.
3. Review contact details, resume or attachment if provided, application notes,
   and the job association.
4. Confirm the candidate is attached to the expected job before taking notes or
   changing any status.

## Adding Notes

1. Open the candidate profile.
2. Use the notes/activity area to add a factual review note.
3. Keep notes job-related and neutral:
   - availability
   - transportation or travel range
   - relevant work or volunteer experience
   - customer-service, event, sports, school, or photography experience
   - missing information to ask about
4. Do not record protected characteristics or guesses about protected
   characteristics.

Example note:

`Reviewed application for job 41002. Candidate reports Saturday availability, reliable transportation, and prior school/event photography experience. Need to confirm Sunday availability and comfort with background check.`

## Moving A Candidate Through Stages

Use OpenCATS status/stage controls only after a human decision.

Recommended manual stages:

1. `New` - application received.
2. `Needs Review` - Craig or staff needs to read details.
3. `Follow Up Needed` - missing availability, transportation, background-check,
   or experience information.
4. `Interview Requested` - Craig has decided to request an interview.
5. `Interview Scheduled` - interview date/time is set.
6. `Offer / Assignment Review` - final human review before hire or assignment.
7. `Hired` - only after Craig's final approval.
8. `Declined` - only after Craig's final approval.

Do not let AI or automation move a candidate between stages.

## Associating Candidates With Jobs

1. Open the candidate profile.
2. Check current job association.
3. If a candidate applied to the wrong role or should be considered for another
   approved role, add the additional job association manually.
4. Add a note explaining why the association was made.
5. For the freelance role, confirm the candidate applied to or is being
   considered for the contractor role specifically. Do not combine contractor
   candidates with the W-2 staff photographer role.

## Scheduling Interviews

1. Confirm the candidate's availability manually.
2. Create an interview activity or event in OpenCATS if available.
3. Add the planned interview date, time, role, and interviewer.
4. Send any message manually or through a draft-only process that Craig approves.
5. Do not enable automatic email.

## Marking Hired Or Declined

1. Review the candidate's application, notes, interview notes, availability,
   background-check status, and job fit.
2. Confirm the decision with Craig.
3. Change the status manually.
4. Add a factual decision note.
5. If communication is needed, prepare it as a draft for human approval.

## Safe AI Candidate Review Feature

Recommended button label:

`Analyze Candidate`

The feature should run only when Craig clicks the button on a candidate profile.

Allowed output:

- Role applied for
- Summary of job-related experience
- Schedule and availability summary
- Travel/transportation summary
- Equipment summary for photographer roles, if relevant
- Missing or unclear information
- Suggested interview questions
- Neutral human-review note

Required safeguards:

- Never automatically reject, rank, hire, email, assign, or change status.
- Never score protected characteristics.
- Ignore and do not infer age, race, color, religion, sex, sexual orientation,
  gender identity, pregnancy, disability, national origin, citizenship status
  except where legally/job-authorized work eligibility is handled by normal HR
  process, marital/family status, veteran status, medical information, photos,
  names, graduation years, or other non-job-related personal details.
- Do not use address beyond the candidate's stated travel ability or commute
  relevance to the role.
- Do not send applicant data to a model unless Craig has approved the vendor,
  data-retention settings, and privacy terms.
- Save AI output as a draft/review aid only, with a visible statement:
  `Human review required before any interview, rejection, hiring, assignment, or applicant communication.`

Implementation plan:

1. Add a manual `Analyze Candidate` action on the candidate detail page.
2. Collect only the candidate fields needed for the selected job:
   contact-independent application text, resume text if provided, job ID, role
   requirements, availability answers, travel/transportation answers, and
   equipment answers where relevant.
3. Run the existing NESP AI review prompt from
   `docs/nesp/AI_SCREENING_PROMPT.md`.
4. Display the analysis in a preview panel with no status-changing controls.
5. Allow Craig to copy the summary into a note manually, or save it as an
   explicitly labeled AI-assisted note after Craig reviews it.
6. Keep outbound email disabled.
7. Log that analysis was run, who ran it, and when, without logging secrets.
8. Add tests confirming the feature cannot change candidate status, job
   association, hiring decision, rejection decision, or send email.
