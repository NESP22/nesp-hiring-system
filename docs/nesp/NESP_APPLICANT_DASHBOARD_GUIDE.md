# NESP Applicant Dashboard Guide

## Purpose

This guide explains the applicant workflow information shown inside the internal NESP Hiring dashboard. It is not a public applicant portal and does not give applicants direct access to OpenCATS.

## Dashboard Views

- `Needs Craig`: applicants needing a Craig decision or review.
- `Waiting`: applicants or interviewers holding up the next step.
- `Interviews`: scheduled interview work and follow-up.
- `Completed`: finished hiring outcomes and inactive workflow items.
- `Settings`: admin controls, feature flags, interviewer setup, and audit review.

## Applicant Card Fields

Each card is designed to be skimmed quickly:

- Candidate name.
- Applied role.
- Current stage.
- Waiting on whom.
- One-sentence factual summary.
- Last activity.
- One large next-action button.
- Secondary detail links.

## Plain-Language Stages

- New Application.
- Ready for Review.
- Waiting for Phone Screen.
- Phone Screen Complete.
- Craig Review Needed.
- Ready for Interviewer Assignment.
- Waiting for Zoom Interview.
- Zoom Interview Scheduled.
- Waiting for Interview Notes.
- Interview Complete.
- Background Check.
- Offer.
- Hired.
- Hold.
- Not Selected.
- Withdrawn.

## Waiting-On Values

- Craig.
- Applicant.
- Interviewer.
- System.
- None.

## Safety Rules

- Suggestions never change stages automatically.
- Recommendations never hire or reject.
- Scorecards return work to Craig for review.
- Applicant-facing messages are not sent automatically.
- Email, SMS, Vapi, Zoom, AI review, and Drive import remain disabled unless approved in a separate rollout.

## Current Status

- Implemented: internal dashboard queues and card fields.
- Implemented: upcoming interview list and completed-work list.
- Implemented: scoped interviewer scorecard workflow.
- Fixture-only: preview candidates and staffing data.
- Deferred: public applicant scheduling pages and applicant self-service status pages.
