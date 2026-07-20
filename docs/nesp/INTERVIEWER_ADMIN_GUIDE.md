# Interviewer Admin Guide

## Craig Daily Flow

1. Open `NESP Hiring`.
2. Start with `Needs Craig`.
3. Check `Unassigned Candidates`.
4. Assign an interviewer only after the interviewer account has been approved and created in a separate controlled task.
5. Review completed scorecards.
6. Check `Staffing Forecast`.
7. Make final decisions manually.

## Interviewer Daily Flow

1. Open `My Next Actions`.
2. Review only assigned candidates.
3. Conduct the interview.
4. Open `My Availability` to keep weekly availability, date overrides, and blackout dates current.
5. Save a draft scorecard if more time is needed.
6. Submit the scorecard to Craig.

## Approved Interviewer Profile Map

These real interviewer profiles are approved to be staged inactive. Staging does not create an OpenCATS login, send an email, grant candidate access, create a Zoom meeting, or activate the person.

| Person | Email | Approved Jobs | Account State | Notes |
| --- | --- | --- | --- | --- |
| Craig | Existing admin account | All jobs | Active admin | Craig remains the only default Customer Service interviewer for job `41001`. |
| Suthir | `suthir@nesportsphoto.com` | `41002`, `41003` | Ready for Account Creation | Photographer interviews only. No Customer Service or Field Assistant access. |
| Brandon | `brandon@nesportsphoto.com` | `41005` | Email Needs Confirmation | Show this warning before activation: `Please confirm that brandon@nesportsphoto.com is the correct email address.` |
| Nate | `nate@nesportsphoto.com` | None by default | Profile Created | Craig must explicitly assign a job role before Nate can receive candidates. Customer Service job `41001` is server-side Craig/manual-only. |

Run `db/nesp_real_interviewer_profile_seed.sql` only after `db/nesp_interviewer_settings_additive.sql`, and only after Craig approves staging these inactive profiles in the target environment.

## Editable Interviewer Settings

Craig/admin can edit:

- Name, email, active/inactive state, account state, and linked OpenCATS user ID.
- Approved job-role checkboxes.
- Timezone, default duration, buffer, earliest/latest time, daily and weekly limits.
- Whether Craig must attend and whether the interviewer may recommend.
- Availability open/closed status, reopen time, close reason, private notes, and email warning.
- Date-specific availability overrides and blackout dates.

Interviewers with active linked profiles can edit only their own availability screen. They cannot edit other interviewers, feature flags, candidate status, integration settings, or admin controls.

## Current Production State

- Interviewer-pool foundation: enabled.
- Staffing-forecast shell: enabled.
- Real interviewer accounts: none created.
- Interviewer profiles: `0`.
- Candidate grants: `0`.
- Production candidates: `0`.
- Historical staffing data imported: no.
- Google Drive staffing import: disabled.

## Information Needed Before Real Accounts

- Full name.
- Email.
- Roles they may interview.
- Timezone.
- Normal availability.
- Maximum interviews per day.
- Default interview duration.
- Whether Craig must attend.
- Whether they may provide advisory recommendations.

## Secure Account Creation Plan

1. Create a unique OpenCATS login for each approved interviewer.
2. Attach a scoped interviewer profile with no admin rights.
3. Use a temporary password or secure reset flow.
4. Force password change on first login when supported.
5. Grant access only to approved candidate/job pairs.
6. Keep Zoom, AI, prescreening, email, and SMS disabled unless separately approved.
7. Deactivate the interviewer instantly from Craig's dashboard if access needs to stop.
8. Never commit, print, or share passwords in documentation or chat.

## Assignment Safety Rules

An interviewer can receive a candidate assignment only when all of these are true:

- The profile is active.
- Availability status is open.
- The candidate is attached to the exact job order.
- The interviewer is approved for that exact job order.
- The interviewer grant is created by Craig/admin.

The app rejects assignments to inactive, closed, profile-only, or unapproved profiles. Customer Service job `41001` is Craig/manual-only and cannot be granted to an interviewer through the service layer. Suthir cannot receive Field Assistant job `41005`, and Brandon cannot receive photographer jobs unless Craig changes the approved-role checkboxes.
