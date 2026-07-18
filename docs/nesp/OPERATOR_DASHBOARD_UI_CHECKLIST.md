# NESP Operator Dashboard UI Checklist

Use this for the focused operator-friendly dashboard pass. Use only fake, seeded, or protected test records when validating the live app.

## Acceptance Checks

- The first screen answers "What needs my attention now?" without scrolling on tablet/desktop and near the top on mobile.
- The four prominent areas appear in this order: Needs Me Now, Waiting on Applicant, Upcoming Interviews, Recently Completed.
- Candidate cards have exactly one visually dominant action.
- Secondary candidate actions sit under Details.
- Due date appears when available; otherwise the card shows when it started waiting.
- Empty states include one next action.
- Questionnaire, interview, forecast, and interviewer settings links remain available in Other hiring tools.
- Navigation wraps without overlap at 390px, 1024px, and 1440px.
- Dark theme, contrast, focus outlines, and safety copy remain visible.
- No automatic sending, integrations, ranking, imports, or decisions were added.

## Local Smoke

```bash
test/nesp_operator_dashboard_static_smoke.sh
git diff --check
```

## Sanitized Render Screenshots

- `docs/nesp/screenshots/operator-dashboard-mobile-390.png`
- `docs/nesp/screenshots/operator-dashboard-tablet-1024.png`
- `docs/nesp/screenshots/operator-dashboard-desktop-1440.png`

These screenshots use the static fake-data fixture at `test/fixtures/nesp_operator_dashboard_render.html`; they do not contain applicant data.
