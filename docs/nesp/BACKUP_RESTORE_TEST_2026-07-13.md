# NESP Hiring Backup Restore Test - 2026-07-13

This document records the encrypted off-platform backup and restore test for
the NESP hiring portal. It does not contain secrets, backup contents,
applicant records, database dumps, or encryption keys.

## Scope

- Render web service: `nesp-hiring-web`
- Render database service: `nesp-hiring-db`
- Deployed commit tested: `eddee7caaae01b6c2d710ae08e2703b540c9ae9e`
- Custom domain: `https://careers.nesportsphoto.com`
- Backup owner: Craig Edwards
- Recovery process owner: Craig Edwards, with Codex-assisted restore steps
- Test date: 2026-07-13

Basic Auth remained enabled. Outbound email remained disabled. No production
data was deleted or changed.

## Approved Off-Platform Destination

Destination type: Craig-controlled local Mac storage outside the repository.

Encrypted backup path:

`/Users/craig/Documents/NESP-Hiring-Backups/daily/20260713T172634Z/nesp-hiring-backup-20260713T172634Z.tar.gz.cms`

Checksum file:

`/Users/craig/Documents/NESP-Hiring-Backups/daily/20260713T172634Z/nesp-hiring-backup-20260713T172634Z.tar.gz.cms.sha256`

Local restore key location:

`/Users/craig/Documents/NESP-Hiring-Backups/keys/`

The private key must remain outside git and must not be shared publicly.

## Retention Policy

- Keep the latest 7 daily encrypted backups.
- Keep the latest 4 weekly encrypted backups.
- Do not automatically delete backups beyond this policy until the retention
  script/process has been separately tested.
- Weekly backups are identified by copying or creating the selected backup
  under `/Users/craig/Documents/NESP-Hiring-Backups/weekly/` with the UTC
  timestamp in the filename and directory name.

## Encryption

- Method: OpenSSL CMS public-key encryption.
- Content encryption: AES-256 via `openssl cms -encrypt -binary -aes256`.
- Only the public certificate was copied to Render.
- The private key stayed on Craig's Mac outside the repository.
- Generated `config.php` was excluded from the off-platform package because it
  can contain deployment credentials.
- Render deployment environment values found in SQL metadata were redacted
  before encryption.

## Backup Result

- Backup timestamp: `20260713T172634Z`
- Encrypted archive size: `22845` bytes
- Source SHA-256:
  `19132fa8476b05b8385e5c68b5df3ea727ce14630e41c6ac453b118a6f9b4766`
- Destination SHA-256:
  `19132fa8476b05b8385e5c68b5df3ea727ce14630e41c6ac453b118a6f9b4766`
- Source/destination checksum match: passed
- Off-platform plaintext backup files: none left in the destination
- Local encrypted archive permissions: owner read/write only

The Render export directory was cleaned so only the encrypted archive and its
checksum remain there, in addition to the normal Render backup created by the
backup script.

## Restore Test

Restore environment:

- Temporary local directory under `/private/tmp`
- Disposable local Docker container: `nesp-hiring-restore-db`
- MariaDB image: `mariadb:10.11`
- Temporary database: `cats_restore`

Restore steps performed:

1. Decrypted the `.cms` archive locally using Craig's private key.
2. Extracted the backup package into a temporary restore directory.
3. Verified the internal `manifest.sha256` checksums.
4. Restored `persistent-files.tar.gz` into a temporary file directory.
5. Imported `database.sql.gz` into the disposable MariaDB database.
6. Queried restored database tables and NESP job records.
7. Destroyed the disposable database container.
8. Removed temporary plaintext restore files and base64 transfer files.

Restore verification:

- Database tables readable: passed, 54 tables.
- File archive restored: passed.
- Internal package checksums: passed.
- Four active/public NESP jobs restored: passed.
- Job `41004` inactive/nonpublic: passed.

Restored job state:

| Job ID | Title | Status | Public |
| --- | --- | --- | --- |
| `41001` | Part-Time Customer Service Representative | Active | `1` |
| `41002` | Weekend Staff Portrait & Team Photographer - Youth Sports | Active | `1` |
| `41003` | Freelance/Contract Youth Sports Photographer | Active | `1` |
| `41004` | Photography Assistant/Poser | Inactive | `0` |
| `41005` | Weekend Table Greeter / Field Assistant | Active | `1` |

## Launch Gate Status

Backup creation, encryption, off-platform transfer, checksum verification,
database restore, file restore, and four-job verification succeeded.

The launch gate is not fully passed yet because the required fictional `Test
Applicant` verification could not be completed. Production currently reports
`0` candidate rows, so the backup accurately restored `0` candidates. No
candidate record was deleted during this backup test.

Before removing Basic Auth, Craig should either:

1. Confirm that the fictional Test Applicant record is no longer required for
   the launch gate; or
2. Authorize creation of a new fictional test applicant, then rerun an
   encrypted backup and restore test that verifies that record.

## Recovery Steps

1. Retrieve the latest approved encrypted backup from Craig-controlled storage.
2. Verify the `.sha256` checksum before decrypting.
3. Decrypt with the local private restore key stored outside the repository.
4. Restore the database dump into a fresh MariaDB database, never over
   production without approval.
5. Restore `persistent-files.tar.gz` into the approved persistent file path.
6. Recreate deployment configuration from approved environment variables.
7. Confirm installer lock, disabled mail settings, protected uploads, and job
   records before reopening applicant traffic.

Estimated recovery time for this small hiring portal backup is 30-60 minutes
after a working Render/database target and the private restore key are
available.

## Never Commit Or Share

- Database dumps
- Applicant uploads or resumes
- Decrypted backup archives
- Encrypted backup archives
- Private restore keys
- Render environment variables
- Basic Auth credentials
- OpenCATS admin credentials
