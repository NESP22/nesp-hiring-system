# NESP Local Career Portal Review

This folder contains local-only review helpers for the NESP hiring system.

These files are not production deployment steps and must not be used to publish
job ads, enable outbound email, create real applicant records, or connect to
external infrastructure.

Use `seed-local-careers.sql` only against a disposable local OpenCATS database.
It enables the career portal in that database and adds fake local job-order
records for reviewing the draft NESP postings in a browser.

