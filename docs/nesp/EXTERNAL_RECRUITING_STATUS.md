# NESP External Recruiting Setup Status

Updated July 18, 2026.

This status note preserves the safe stopping points for external recruiting.
The NESP careers portal remains the single intended application destination.

## NESP Application Portal

- Role-specific prescreening questions are implemented for jobs `41001`,
  `41002`, `41003`, and `41005`.
- Answers are stored in OpenCATS candidate questionnaire history as
  `NESP Prescreen - [role title]`.
- Answers are for human review only and do not automatically reject, rank, hire,
  assign, email, or change status.
- External platforms should send candidates to the matching NESP application
  form and should not duplicate the NESP prescreen questions when an external
  application URL is supported.

## Indeed

Current review tabs exist for all four roles, but they are not ready to publish.
The employer dashboard currently shows four incomplete NESP drafts, including
two Customer Service drafts.

Blocking issue:

- Indeed currently shows `Application method: Email` on the review pages.
- The NESP application links are present in the job descriptions, but that is
  not the same as configuring Indeed to use the NESP application form as the
  application workflow.

Additional cleanup before publication:

- Remove any remaining Indeed-side customized prescreening questions from the
  photographer drafts when the NESP form is the application destination.
- Correct structured pay for photographer drafts if Indeed permits ranges:
  - Staff Photographer: `$22-$25/hour`
  - Freelance Photographer: `$22-$27/hour`
- Do not publish until Craig approves a compliant application-method path.

Compliant options to investigate:

- Indeed employer setting for company career-site / external application URL.
- Indeed ATS or XML job-feed path that can index the NESP careers URL.
- Manual publication only if Craig approves Indeed/email as an additional
  workflow, which is not currently approved.

OpenCATS feed hardening released July 18:

- The Indeed XML template now uses NESP publisher/company identity instead of
  the legacy CATS values.
- Feed URLs point to the public NESP job-detail page with `source=Indeed` and
  the existing feed reference, so applicants can use the NESP Apply Now flow.
- Feed records now include a requisition ID, ISO-8601 publish date, configured
  salary, and the Indeed account email required by the XML feed format.
- The feed is live at
  `https://careers.nesportsphoto.com/xml/index.php?t=indeed` and was verified
  over HTTPS on July 18. It reports the OpenCATS publisher identity, the four
  NESP requisition IDs, the corrected availability facts, and HTTPS job links.
  Indeed XML-feed onboarding is still required before Indeed can ingest it.

Indeed ATS account check completed July 18:

- OpenCATS was added to the Indeed employer account's ATS selection.
- Indeed confirmed that OpenCATS does not currently have an available Indeed
  integration. This records the relationship in the account but does not
  create automatic candidate or job synchronization.
- Keep the public NESP HTTPS feed and role-specific NESP Apply Now links as the
  source of truth until Indeed approves or supports a direct integration.

July 18 verification:

- The current Indeed dashboard shows four incomplete NESP drafts, including two
  Customer Service drafts.
- At least one draft still shows `Application method: Email`.
- Opening the application-method editor triggers Indeed's account
  re-verification prompt and requests a confirmation code before the setting
  can be changed.
- Do not publish the drafts until the application method is changed to a
  compliant external NESP application path and verified on the review page.

Current access re-check, July 18, 2026:

- The Indeed employer account is currently signed in as
  `craig@nesportsphoto.com`.
- The Jobs dashboard currently shows four incomplete NESP drafts: two Customer
  Service drafts, Freelance Photographer, and Table Greeter / Field Assistant.
  The unnamed exploratory draft was deleted during this verification; the
  older Staff Photographer and Freelance Photographer listings remain visible
  as paused listings.
- The Customer Service review page still shows `Application method: Email`.
  No Indeed post was published during this check.
- Craigslist currently redirects to the account login page, so no Craigslist
  draft was submitted or paid for during this check.
- A direct HTTPS check of the live Indeed XML feed returned exactly
  requisitions `41001`, `41002`, `41003`, and `41005`, with corrected
  year-round/seasonal copy and role-specific NESP application URLs.

Public copy release verification:

- Copy polish was merged and deployed to `careers.nesportsphoto.com` on July
  18 in release `a9507e0`.
- The live customer-service page now opens with a clear year-round role
  summary and shows approximately 20-30 hours per week.
- The live staff photographer, freelance photographer, and field assistant
  pages now use distinct applicant-focused openings and show the seasonal
  windows September-November and April-June.
- The live Indeed XML feed contains the same copy and corrected availability
  facts for all four requisitions.

Draft copy prepared July 18:

- Customer Service (`41001`): 1 opening; part-time; year-round; approximately
  20-30 hours per week; `$22-$25/hour`.
- Staff Photographer (`41002`): 20 openings; part-time, temporary, seasonal;
  September-November and April-June; approximately 20-30 hours per week;
  `$22-$25/hour`.
- Freelance Photographer (`41003`): existing separate 7-opening draft;
  part-time seasonal contract assignments generally available September-November
  and April-June; `$22-$27/hour`.
- Table Greeter / Field Assistant (`41005`): 20 openings; part-time,
  temporary, seasonal; September-November and April-June; `$18/hour`.

The current Indeed dashboard still reports incomplete drafts, so they remain
unpublished until each draft is rechecked and the application method is
verified as the matching NESP/OpenCATS form.

## Craigslist

Craig approved up to `$180` total for four separate Craigslist posts, expected
at `$45` each if the Boston jobs flow remains unchanged.

Current browser draft:

- Customer Service draft exists in the Boston Craigslist flow.
- The draft has been adjusted in the visible form to use the direct application
  link:
  `https://careers.nesportsphoto.com/careers/?p=applyToJob&ID=41001`
- The draft has not been continued to payment, submitted, or published.

Still needed:

- Prepare separate drafts for jobs `41002`, `41003`, and `41005`.
- Confirm region, category, title, rate, direct NESP application URL, `$45`
  cost, and expiration before payment.
- Stop before entering or confirming payment unless Craig is present.

## College, MassHire, And Photography Channels

Do not create accounts, accept terms, or publish without Craig's approval.

Priority targets remain:

- Northern Essex Community College / Handshake
- Merrimack College / employer recruiting channel
- UMass Lowell / Handshake
- Middlesex Community College / employer recruiting channel
- MassHire Merrimack Valley Career Center
- New Hampshire Employment Security / NHWorks if applicable
- Relevant photography associations, camera clubs, and college photography
  program newsletters

For each channel, confirm whether it supports an external NESP application link
before posting.

July 18 platform checks:

- MassHire JobQuest advertises free employer job posting, but requires an
  employer account before a job can be posted.
- SeasonalJobs.com requires an employer account and currently presents paid
  packages of $49 for one job or $99 for five jobs.
- ProductionHUB requires an account to post a basic job.
- LinkedIn requires a signed-in employer account before job posting.
- CoolWorks and JobsInSports require an employer account or employer purchase
  flow before posting.
