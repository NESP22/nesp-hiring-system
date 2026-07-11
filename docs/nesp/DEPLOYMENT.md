# NESP Hiring System — Secure Deployment Guide

## Current status

This repository is a fork of OpenCATS. The NESP production files are being prepared on a separate branch before anything is placed online.

Nothing in this guide changes the live NESP website, DNS, email, Cloudflare Workers, or any existing business system.

## Intended architecture

```text
Applicant or NESP staff
        |
        v
Cloudflare HTTPS / Tunnel
        |
        v
OpenCATS web container (localhost-only origin)
        |
        +--> PHP container
        |
        +--> private MariaDB network
```

The MariaDB service has no public port. phpMyAdmin is not included. The OpenCATS origin binds only to `127.0.0.1` unless the deployment is intentionally changed.

## Before deployment

Use a small Linux server that remains online. The server must have:

- Docker Engine
- Docker Compose v2
- Git
- Enough encrypted disk space for resumes and backups
- Operating-system security updates enabled

Do not use Craig's everyday computer as the permanent production host.

## Initial server setup

Clone the NESP fork and switch to the approved production branch or merged `master` branch:

```bash
git clone https://github.com/NESP22/nesp-hiring-system.git
cd nesp-hiring-system
```

Create the untracked production environment file:

```bash
cp docker/.env.production.example docker/.env.production
```

Generate two different long passwords and place them in `docker/.env.production`:

```bash
openssl rand -base64 36
openssl rand -base64 36
```

Never paste those passwords into GitHub, ChatGPT, screenshots, email, or a support ticket.

## Start OpenCATS privately

From the repository root:

```bash
docker compose \
  --env-file docker/.env.production \
  -f docker/docker-compose.production.yml \
  up -d --build
```

Check status:

```bash
docker compose \
  --env-file docker/.env.production \
  -f docker/docker-compose.production.yml \
  ps
```

The origin is intentionally available only from the server at:

```text
http://127.0.0.1:8080
```

Do not open port 8080 to the public internet.

## OpenCATS installation wizard

Connect privately through SSH port forwarding or wait until the Cloudflare Tunnel is configured.

Use these database values in the OpenCATS installer:

- Database host: `opencatsdb`
- Database name: value of `DB_NAME`
- Database user: value of `DB_USER`
- Database password: value of `DB_PASSWORD`

After installation:

1. Sign in with the new administrator account.
2. Replace any installer-generated temporary password.
3. Create an empty `INSTALL_BLOCK` file in the repository root.
4. Confirm the installation wizard can no longer be opened.
5. Keep outbound email disabled until templates and recipient safety are tested.
6. Do not upload real applicant resumes until backups and access controls pass testing.

## Cloudflare plan

Cloudflare will be configured only after a server is running successfully.

Recommended hostnames:

- `jobs.<approved-domain>` — public job listings and applicant form
- `hiring-admin.<approved-domain>` — staff dashboard protected by Cloudflare Access

Both hostnames can point through Cloudflare Tunnel to the same local OpenCATS origin. Cloudflare Access should protect the staff hostname only; the public application hostname must remain usable by applicants.

When the tunnel token is ready, store it only in `docker/.env.production`, then start the optional tunnel profile:

```bash
docker compose \
  --env-file docker/.env.production \
  -f docker/docker-compose.production.yml \
  --profile cloudflare \
  up -d
```

## Backups

Back up both:

1. The MariaDB database.
2. The OpenCATS application directory, especially generated configuration, resumes, uploads, attachments, and temporary processing data that must be retained.

A backup is not considered complete until a restore has been tested on a separate machine or isolated Docker project.

Recommended minimum policy:

- Encrypted daily backup
- At least 30 daily restore points
- One monthly restore test
- Backups stored away from the production server
- Access limited to authorized NESP staff

## Applicant-data rules

Applicant information is sensitive business and personal data.

- Do not commit resumes or applicant data to GitHub.
- Do not paste resumes into public AI tools.
- Do not place protected characteristics into AI scoring prompts.
- AI may summarize and organize job-related information, but a person must make interview, rejection, and hiring decisions.
- Keep automated email sending disabled until Craig explicitly approves the final workflow.

## Next gates

1. Validate Docker configuration in GitHub Actions.
2. Select a small always-on server.
3. Deploy privately without Cloudflare or public applicants.
4. Complete a fake-applicant test.
5. Configure Cloudflare Tunnel and Access.
6. Add the NESP customer-service position.
7. Add AI summaries in draft/recommendation mode only.
8. Add photographer and table-staff seasonal workflows.
