# NESP Hiring Portal Deployment Readiness

This document is a planning checklist only. It does not authorize deployment,
public posting, DNS changes, Cloudflare changes, Render resource creation,
billing approval, outbound email, or real applicant collection.

## Target Service Shape

- Web service: Render Web Service running the OpenCATS web/PHP stack.
- Database: persistent private MariaDB service.
- Worker/background jobs: none required for the first private review launch.
- Public job board: OpenCATS careers module, eventually mapped to a public
  hostname such as `careers.nesportsphoto.com`.
- Staff dashboard: OpenCATS admin/staff area, protected separately before any
  real applicant data is accepted.

## Required Components

- OpenCATS application container.
- PHP runtime with required extensions from the project Dockerfile.
- Nginx or equivalent web front end.
- Private MariaDB database.
- Persistent application storage for generated config, attachments, uploads,
  resumes, exports, backups, and temporary processing files that must survive
  deploys.
- HTTPS termination through the approved hosting/proxy layer.

## Persistent Database Requirements

- MariaDB must be persistent, private, and backed up.
- The database must not expose a public port.
- Production data must not use the local test seed records.
- Before launch, run the OpenCATS installer or approved production migration
  process against the persistent database.
- Confirm the career portal settings and job records are production-approved
  before any public hostname is connected.

## Persistent File And Upload Storage

- Store resumes, uploads, attachments, generated config, and temporary parsing
  files on persistent disk or approved object storage.
- Do not store applicant files only inside an ephemeral container filesystem.
- Restrict direct public access to applicant files.
- Include the persistent storage path in backups and restore tests.

## Environment Variables

Required before private deployment:

- `DB_NAME`
- `DB_USER`
- `DB_PASSWORD`
- `DB_ROOT_PASSWORD` or managed database equivalent
- `OPENCATS_HTTP_PORT` or Render-provided port mapping
- `PHP_VERSION`
- `MARIADB_IMAGE` if using the Docker Compose path

Must remain unset or disabled until explicitly approved:

- Cloudflare tunnel token or DNS integration values
- Outbound SMTP credentials
- Any value that enables `OPENCATS_MAIL_ENABLED`
- External job-board integration credentials
- Analytics or tracking IDs

## Initial Admin Setup

1. Deploy privately, not publicly.
2. Complete OpenCATS installation against the persistent database.
3. Create a named admin account for Craig or approved NESP staff.
4. Replace installer or temporary passwords immediately.
5. Confirm `INSTALL_BLOCK` prevents rerunning the installer.
6. Confirm staff access is protected before real applicant data is accepted.
7. Confirm hiring decisions remain human-reviewed.

## Backups

- Run encrypted database backups on a defined schedule.
- Back up persistent application files and uploads.
- Keep backups away from the production service account where practical.
- Test a restore before accepting real applicants.
- Document who can access backups and how restore approval works.

## Outbound Email

- Keep outbound email disabled for launch readiness review.
- Applicant communications remain draft-only until Craig approves templates,
  recipients, sender domain, unsubscribe/compliance needs, and test results.
- Do not enable automatic rejection, selection, hiring, assignment, status
  changes, or email sending without human-reviewed workflow approval.

## Suggested Future URL

- Public applicant board: `careers.nesportsphoto.com`
- Staff dashboard: separate protected hostname or private access path.

DNS and Cloudflare changes are blocked until Craig explicitly approves the
final hosting plan, access controls, and launch timing.

## Rollback Steps

1. Remove public DNS or proxy routing to the service.
2. Stop or scale down the web service.
3. Keep the database and persistent uploads intact for audit and recovery.
4. Restore the last known-good database and file-storage backup if data changes
   caused the rollback.
5. Confirm outbound email remains disabled during rollback.
6. Re-run smoke tests privately before reconnecting any public hostname.

## Launch Blockers

- Final Craig approval of all public job wording.
- Payroll/legal confirmation for W-2, travel-pay, overtime, and background
  check language.
- Contractor-classification review before external publication of the
  Freelance/Contract Youth Sports Photographer role.
- Approved NESP logo/brand asset for the public portal.
- Production admin setup and access controls.
- Persistent database and file storage configured and restore-tested.
- Backups configured and restore-tested.
- Outbound email workflow approved, or confirmed disabled.
- Privacy and applicant-data handling review.
- No external job-board posting until explicitly approved.
