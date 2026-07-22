from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER, TA_LEFT
from reportlab.lib.pagesizes import letter
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import inch
from reportlab.platypus import (
    BaseDocTemplate,
    Frame,
    PageTemplate,
    Paragraph,
    Spacer,
    Table,
    TableStyle,
    PageBreak,
    KeepTogether,
)


OUTPUT = "docs/nesp/NESP_Hiring_System_Operations_Guide.pdf"


def on_page(canvas, doc):
    canvas.saveState()
    canvas.setFont("Helvetica", 8)
    canvas.setFillColor(colors.HexColor("#64748b"))
    canvas.drawString(0.7 * inch, 0.42 * inch, "NESP Hiring System Operations Guide")
    canvas.drawRightString(7.8 * inch, 0.42 * inch, f"Page {doc.page}")
    canvas.restoreState()


def p(text, style):
    return Paragraph(text, style)


def bullet(items, style):
    out = []
    for item in items:
        out.append(Paragraph(f"- {item}", style))
    return out


def section(title, body, styles):
    story = [Paragraph(title, styles["H2"]), Spacer(1, 6)]
    story.extend(body)
    story.append(Spacer(1, 12))
    return story


def make_table(rows, widths, header=True):
    header_style = ParagraphStyle(
        name="TableHeader",
        fontName="Helvetica-Bold",
        fontSize=8.2,
        leading=10.5,
        textColor=colors.HexColor("#0f172a"),
        alignment=TA_LEFT,
    )
    cell_style = ParagraphStyle(
        name="TableCell",
        fontName="Helvetica",
        fontSize=8.0,
        leading=10.5,
        textColor=colors.HexColor("#111827"),
        alignment=TA_LEFT,
    )
    wrapped = []
    for row_index, row in enumerate(rows):
        wrapped_row = []
        for value in row:
            style = header_style if header and row_index == 0 else cell_style
            wrapped_row.append(Paragraph(str(value), style))
        wrapped.append(wrapped_row)
    tbl = Table(wrapped, colWidths=widths, hAlign="LEFT", repeatRows=1 if header else 0)
    tbl.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, 0), colors.HexColor("#dbeafe") if header else colors.white),
        ("TEXTCOLOR", (0, 0), (-1, 0), colors.HexColor("#0f172a")),
        ("FONTNAME", (0, 0), (-1, 0), "Helvetica-Bold" if header else "Helvetica"),
        ("FONTNAME", (0, 1), (-1, -1), "Helvetica"),
        ("FONTSIZE", (0, 0), (-1, -1), 8.5),
        ("LEADING", (0, 0), (-1, -1), 11),
        ("GRID", (0, 0), (-1, -1), 0.35, colors.HexColor("#cbd5e1")),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("LEFTPADDING", (0, 0), (-1, -1), 7),
        ("RIGHTPADDING", (0, 0), (-1, -1), 7),
        ("TOPPADDING", (0, 0), (-1, -1), 6),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 6),
    ]))
    return tbl


def build():
    doc = BaseDocTemplate(
        OUTPUT,
        pagesize=letter,
        leftMargin=0.7 * inch,
        rightMargin=0.7 * inch,
        topMargin=0.7 * inch,
        bottomMargin=0.65 * inch,
        title="NESP Hiring System Operations Guide",
    )
    frame = Frame(doc.leftMargin, doc.bottomMargin, doc.width, doc.height, id="normal")
    doc.addPageTemplates([PageTemplate(id="main", frames=frame, onPage=on_page)])

    styles = getSampleStyleSheet()
    styles.add(ParagraphStyle(
        name="TitleMain",
        parent=styles["Title"],
        fontName="Helvetica-Bold",
        fontSize=25,
        leading=30,
        textColor=colors.HexColor("#0f172a"),
        alignment=TA_CENTER,
        spaceAfter=10,
    ))
    styles.add(ParagraphStyle(
        name="Subtitle",
        parent=styles["BodyText"],
        fontName="Helvetica",
        fontSize=11,
        leading=15,
        textColor=colors.HexColor("#334155"),
        alignment=TA_CENTER,
        spaceAfter=18,
    ))
    styles.add(ParagraphStyle(
        name="H1",
        parent=styles["Heading1"],
        fontName="Helvetica-Bold",
        fontSize=18,
        leading=22,
        textColor=colors.HexColor("#1e3a8a"),
        spaceBefore=8,
        spaceAfter=8,
    ))
    styles.add(ParagraphStyle(
        name="H2",
        parent=styles["Heading2"],
        fontName="Helvetica-Bold",
        fontSize=13,
        leading=16,
        textColor=colors.HexColor("#0f172a"),
        spaceBefore=6,
        spaceAfter=4,
    ))
    styles.add(ParagraphStyle(
        name="Body",
        parent=styles["BodyText"],
        fontName="Helvetica",
        fontSize=9.5,
        leading=13,
        textColor=colors.HexColor("#111827"),
        spaceAfter=5,
    ))
    styles.add(ParagraphStyle(
        name="Small",
        parent=styles["BodyText"],
        fontName="Helvetica",
        fontSize=8.5,
        leading=11.5,
        textColor=colors.HexColor("#334155"),
        spaceAfter=4,
    ))
    styles.add(ParagraphStyle(
        name="Callout",
        parent=styles["BodyText"],
        fontName="Helvetica-Bold",
        fontSize=10,
        leading=14,
        textColor=colors.HexColor("#7c2d12"),
        backColor=colors.HexColor("#ffedd5"),
        borderColor=colors.HexColor("#fdba74"),
        borderWidth=0.5,
        borderPadding=8,
        spaceBefore=6,
        spaceAfter=10,
    ))

    story = []
    story.append(Spacer(1, 0.55 * inch))
    story.append(Paragraph("NESP Hiring System", styles["TitleMain"]))
    story.append(Paragraph("Operations Guide for Craig", styles["Subtitle"]))
    story.append(Paragraph(
        "Use this guide to run the hiring dashboard safely: review applicants, send manual questionnaire links, manage interviewers, schedule manual Zoom interviews, and use the Staffing Forecast.",
        styles["Body"],
    ))
    story.append(Paragraph(
        "Release boundary: this guide is pinned to deployed commit 9bf3760e6d0955d1fb0d399adda9eaa4217319bc (merged PR #26, July 17, 2026). Dashboard: https://careers.nesportsphoto.com/index.php?m=nesp. Sign in first at https://careers.nesportsphoto.com/index.php?m=login as Craig/admin. Protected tests are Craig/admin-only and synthetic-data-only.",
        styles["Small"],
    ))
    story.append(Spacer(1, 0.14 * inch))
    story.append(Paragraph("Start Here: 6-Step Quickstart", styles["H2"]))
    story.append(Paragraph(
        "In the pinned build, the queue is labeled Needs Craig; this is the Needs Me Now worklist.",
        styles["Small"],
    ))
    story.append(make_table([
        ["Step", "What to click", "Success looks like", "Stop gate"],
        ["1. Login", "Open the login URL and sign in as Craig/admin.", "NESP Hiring opens with admin controls available.", "Stop if the URL, account, or role is unexpected."],
        ["2. Needs Me Now", "Click NESP Hiring, then Needs Craig (live label).", "Candidate card shows exact role, waiting state, and one next action.", "Stop on duplicate, wrong role, or unclear action."],
        ["3. Generate link", "On the correct card click Questionnaire; verify the set; click Generate Secure Questionnaire Link.", "Copy-only invitation is shown for the correct candidate/job/set. Field Staff 41005 uses Field Staff Pre-Interview; photographers 41002/41003 use Photographer Pre-Interview.", "Stop before Generate if role or set is unclear."],
        ["4. Manually share", "Click Copy Invitation, then Mark Invitation Copied; send only after separate approval.", "Copied and manually sent are separate states; dashboard sends nothing.", "Stop after copying unless sending is approved."],
        ["5. Review response", "Click Questionnaires; under Completed Questionnaires click Review; read answers; click Save Review only for human notes.", "Answers match candidate/job; no automatic ranking, rejection, hiring, or stage move.", "Stop on duplicate, wrong candidate, or unexpected automation."],
        ["6. Schedule/track", "Click Schedule Interview; check no active interview; complete fields; click Create Interview Preview. Later click Track in Interviews, then Save Human Outcome.", "Interview appears once in Upcoming Interviews; Track shows same candidate/date/interviewer and masked participant link.", "Stop before sending or creating Zoom/calendar events or a duplicate."],
    ], [0.82 * inch, 2.15 * inch, 2.38 * inch, 1.55 * inch]))
    story.append(Spacer(1, 0.14 * inch))
    story.append(Spacer(1, 0.18 * inch))
    story.append(KeepTogether([
        make_table([
            ["Current Safety Posture", "Meaning"],
            ["Manual only", "The system tracks links and invitations, but does not automatically email, text, call, create calendar events, or create Zoom meetings."],
            ["Human decisions only", "No AI ranking, automatic rejection, automatic hiring decision, or automatic candidate movement should be enabled."],
            ["NESP contact guarded", "NESP applicant/interviewer email, SMS, calls, Zoom meetings, calendar events, AI review, job ads, and automatic decisions stay off unless separately approved. This does not disable unrelated legacy OpenCATS password recovery."],
        ], [2.0 * inch, 4.9 * inch]),
        Spacer(1, 0.22 * inch),
        Paragraph(
            "Tonight's operating rule: one applicant record, one visible current state, one obvious next action.",
            styles["Callout"],
        ),
    ]))
    story.append(PageBreak())

    story.append(Paragraph("1. Daily Dashboard Flow", styles["H1"]))
    story.extend(section("Start Here", [
        p("Open the NESP dashboard and work from the main queues. Do not start from raw OpenCATS candidate lists unless you are diagnosing a record.", styles["Body"]),
        make_table([
            ["Queue", "What it means", "Your normal action"],
            ["Needs Me Now (live label: Needs Craig)", "A person or workflow item needs your review.", "Open the card and use the main button."],
            ["Waiting on Applicant", "You are waiting for the applicant to complete a questionnaire or respond.", "Do not duplicate the candidate. Check status or resend manually if needed."],
            ["Waiting on Interviewer", "An interviewer needs to review, meet, or record an outcome.", "Check interviewer assignment and upcoming interview status."],
            ["Upcoming Interviews", "Scheduled interviews that have not happened yet.", "Confirm Zoom link, date, time, and interviewer."],
            ["Recently Completed", "Finished items that still need history or outcome review.", "Confirm final notes and next action."],
        ], [1.5 * inch, 2.35 * inch, 3.05 * inch]),
    ], styles))
    story.extend(section("ADHD-Clean Rule", [
        p("Each candidate card should be read in this order: current status, next action, due date or waiting time, candidate name, job, then details.", styles["Body"]),
        *bullet([
            "If the next action is not obvious, do not guess. Open the candidate history first.",
            "If a candidate appears twice, stop and treat it as a duplicate-routing issue.",
            "If any screen shows a send button, confirm whether sending is disabled before using it.",
        ], styles["Small"]),
    ], styles))

    story.append(PageBreak())
    story.append(Paragraph("2. Applicant Questionnaire Workflow", styles["H1"]))
    story.extend(section("Generate And Send A Questionnaire Manually", [
        make_table([
            ["Step", "Action", "Pass Check"],
            ["1", "Open the applicant in the dashboard.", "Correct candidate and job are shown."],
            ["2", "Choose Invite to Screening Questionnaire.", "Question set matches the job type; stop if unclear."],
            ["3", "Generate Secure Link.", "Status becomes Link Ready or Waiting for Questionnaire."],
            ["4", "Copy Invitation or secure link.", "Copy includes the full public URL."],
            ["5", "Paste into your own email or message app.", "The dashboard itself does not send."],
            ["6", "Applicant opens link and submits once.", "Reopening same link shows already submitted/unavailable."],
            ["7", "Refresh dashboard.", "Status becomes Questionnaire Completed or Ready for Review."],
        ], [0.55 * inch, 3.0 * inch, 3.35 * inch]),
        p("Use this copy as the standard invite text. Copying is not sending; track generated, copied, manually sent, and response received as separate states:", styles["Body"]),
        p("<b>Subject:</b> NESP Screening Questionnaire<br/><br/><b>Body:</b> Thank you for applying to New England Sports Photo. Please complete the secure screening questionnaire using the link below.<br/><br/>&lt;secure link&gt;<br/><br/>Please complete it by &lt;expiration date&gt;. If you have trouble opening the link, reply to the person who sent you this message.", styles["Small"]),
    ], styles))
    story.extend(section("Questionnaire Sets", [
        p("Use Questionnaires -> Manage Question Sets when the wording needs to change. Edit a draft, preview it, then publish. Publishing affects future links only. Already-sent links keep their original question snapshot.", styles["Body"]),
        *bullet([
            "Field Staff Pre-Interview should appear first for table/field assistant applicants; Field Staff/Table Greeter job 41005 uses it.",
            "Photographer Pre-Interview is for Staff Photographer job 41002 and Freelance Photographer job 41003.",
            "Do not edit production wording directly in code once the admin screen is live.",
        ], styles["Small"]),
    ], styles))

    story.append(PageBreak())
    story.append(Paragraph("3. Interview Scheduling And Manual Zoom", styles["H1"]))
    story.extend(section("Schedule An Interview", [
        make_table([
            ["Step", "Action", "Pass Check"],
            ["1", "Create a Zoom meeting yourself outside the app.", "You have the applicant participant join link."],
            ["2", "Open Schedule Interview.", "Candidate and job are correct."],
            ["3", "Choose interviewer, date, time, duration, and timezone.", "Eastern time is default unless changed."],
            ["4", "Paste only the participant Zoom join URL.", "Host/start URLs are rejected."],
            ["5", "Save.", "Interview appears once in Upcoming Interviews, candidate history, interviewer queue, and counters."],
            ["6", "Copy/send invitation manually if approved.", "App records manual tracking but sends nothing automatically."],
        ], [0.55 * inch, 3.05 * inch, 3.3 * inch]),
    ], styles))
    story.extend(section("Reschedule, Cancel, Outcome", [
        make_table([
            ["Action", "What to do", "Must remain true"],
            ["Reschedule", "Edit the existing interview date/time/link if needed.", "No duplicate interview is created; old schedule stays in audit history."],
            ["Cancel", "Mark cancelled in the dashboard and manually cancel the Zoom meeting in Zoom.", "Cancelled item leaves Upcoming but remains in candidate history."],
            ["Outcome", "Record Completed, No Show, Follow-up Needed, Advance, Declined, or Not Moving Forward.", "Outcome is human-entered; no automatic ranking or rejection."],
        ], [1.2 * inch, 3.15 * inch, 2.55 * inch]),
    ], styles))

    story.append(PageBreak())
    story.append(Paragraph("4. Interviewer Setup", styles["H1"]))
    story.extend(section("Create And Activate An Interviewer", [
        make_table([
            ["Step", "Action", "Important Safety Detail"],
            ["1", "Open Interviewer Settings.", "Only Craig/admin should access this screen."],
            ["2", "Click Prepare Login.", "The login remains disabled; one-time details are shown only and are not emailed."],
            ["3", "Share the one-time login details manually only after approval.", "Use an approved channel; do not put credentials in this manual or a shared log."],
            ["4", "Activate Login.", "Interviewer can log in only after activation."],
            ["5", "Assign approved job roles or candidate grants.", "Interviewer sees only allowed candidates."],
            ["6", "Suspend, reactivate, reset, or disable as needed.", "History remains; passwords are never emailed by the app."],
        ], [0.55 * inch, 2.55 * inch, 3.8 * inch]),
    ], styles))
    story.extend(section("Interviewer Can And Cannot Do", [
        make_table([
            ["Can", "Cannot"],
            ["View assigned candidates and assigned jobs.", "View all candidates or unassigned candidates."],
            ["Review questionnaire answers for assigned candidates.", "Change candidate stages, approve, reject, delete, or hire."],
            ["Add interview notes and advisory recommendations.", "Access Vapi settings, Job Ads, feature flags, API secrets, or user admin."],
            ["Manage their own availability after that feature is enabled.", "See other interviewers' credentials or private Craig/admin notes."],
        ], [3.45 * inch, 3.45 * inch]),
    ], styles))

    story.append(PageBreak())
    story.append(Paragraph("5. Interviewer Availability, Calendar, And Zoom Links", styles["H1"]))
    story.extend(section("Per-Interviewer Rule", [
        p("Every interviewer must manage their own availability, their own Google Calendar connection, and their own Zoom participant link. Do not use a shared company calendar or shared Zoom link as a fallback.", styles["Body"]),
        make_table([
            ["Feature", "How it should work", "Default state"],
            ["Availability", "Interviewer sets recurring hours, blocked time, vacations, buffers, and limits.", "Disabled until deployed/tested."],
            ["Google Calendar", "Interviewer connects their own calendar for free/busy only. Event names/details stay hidden.", "Disabled until separately approved."],
            ["Zoom link", "Interviewer stores a safe participant join link. Host/start links are blocked.", "Disabled until protected testing."],
        ], [1.35 * inch, 4.1 * inch, 1.45 * inch]),
    ], styles))
    story.extend(section("Scheduling Conflict Checks", [
        *bullet([
            "Regular availability must allow the time.",
            "Blocked time, vacations, all-day unavailable periods, buffers, and daily/weekly limits must be respected.",
            "Existing NESP interviews must block overlapping interviews.",
            "Google busy windows must block scheduling when calendar free/busy is enabled.",
            "Admin override requires a written reason and audit event.",
        ], styles["Small"]),
    ], styles))

    story.append(PageBreak())
    story.append(Paragraph("6. Staffing Forecast", styles["H1"]))
    story.extend(section("How To Use The Forecast", [
        make_table([
            ["Step", "Action", "What You Get"],
            ["1", "Upload an exported .xlsx or .csv file and run Dry-Run.", "There is no direct Google Sheets integration; the temporary batch parses without saving source rows."],
            ["2", "Review rows.", "Check date, job/location, staffing string, and warnings."],
            ["3", "Approve valid rows only.", "Ambiguous/invalid rows stay out."],
            ["4", "Create verified backup.", "Production import is allowed only after backup."],
            ["5", "Stop before Import Approved Rows unless separately approved.", "Only explicitly approved rows persist after backup verification; the temporary dry-run batch then expires or is cleared."],
            ["6", "View Hiring Recommendation.", "Preliminary Fall 2026 needs by role and peak day/time."],
        ], [0.55 * inch, 2.45 * inch, 3.9 * inch]),
        p("Staffing shorthand: 1P means one photographer, 1T means one table person, and 1A means one photographer assistant.", styles["Callout"]),
    ], styles))
    story.extend(section("Forecast Reading Rules", [
        *bullet([
            "Treat current confirmed staff as zero unless current active staff records prove otherwise.",
            "Do not count historical names as available current staff.",
            "Hiring gap must be based on overlapping jobs and peak simultaneous demand, not total jobs added together.",
            "Label results as preliminary if based only on Fall 2026 reviewed rows.",
            "Treat incomplete start/end intervals as uncertain peak concurrency; do not present the result as exact until timing is verified.",
        ], styles["Small"]),
    ], styles))

    story.append(PageBreak())
    story.append(Paragraph("7. Safety Switches And Approval Gates", styles["H1"]))
    story.extend(section("Keep Disabled Unless Separately Approved", [
        make_table([
            ["Feature", "Keep Disabled Because"],
            ["NESP Applicant / Interviewer Email / SMS", "Manual contact is safer until separately approved. This does not disable unrelated legacy OpenCATS password recovery."],
            ["Vapi Calls", "No applicant or staff calls should happen automatically."],
            ["Zoom API / Sync", "Manual Zoom links are enough; no automatic meeting creation."],
            ["Google Calendar Event Creation", "Free/busy checking is separate from creating invitations."],
            ["AI Review", "No automatic ranking, rejection, or hiring decisions."],
            ["Job Ads and Staffing Drive Import", "Do not publish or connect external sources until a separate controlled rollout is approved."],
        ], [2.25 * inch, 4.65 * inch]),
    ], styles))
    story.extend(section("Exact Approval Lines", [
        p("<b>Deploy approved code only:</b> Approved: merge and deploy PR #__ at head __ after backup verification. Do not enable automatic sending or activate real applicants unless separately approved.", styles["Small"]),
        p("<b>Import staffing rows only:</b> Approved: after backup verification, import only reviewed approved Staffing Forecast rows. Do not modify the Google Sheet or contact applicants/staff.", styles["Small"]),
        p("<b>Protected test send only:</b> Approved: send/copy the protected test questionnaire/interview invitation only. Do not contact real applicants.", styles["Small"]),
        p("<b>Real-applicant activation:</b> Approved: activate the NESP hiring workflow for real applicants with applicant email, SMS, Vapi, Zoom API/sync, Google Calendar, AI review, job ads, automatic sending, and automatic decisions disabled unless separately approved.", styles["Small"]),
    ], styles))

    story.append(PageBreak())
    story.append(Paragraph("8. Quick Troubleshooting", styles["H1"]))
    story.append(make_table([
        ["Problem", "Likely Cause", "Next Safe Action"],
        ["Questionnaire says already submitted", "The secure link was already used once.", "Check dashboard status; generate a new link only if needed."],
        ["You marked sent but applicant got nothing", "The app tracks manual sending; it does not send email.", "Copy the secure link and send through your email/text app."],
        ["Staffing Forecast is blank", "No approved rows have been imported.", "Run dry-run, review, approve rows, backup, then import."],
        ["Applicant not in dashboard", "Workflow routing row may be missing or router not deployed.", "Check Needs Me Now (live label: Needs Craig) and route audit; do not create duplicates."],
        ["Zoom link rejected", "Host/start URL or unsafe token was pasted.", "Use the normal participant join URL only."],
        ["Interviewer cannot see candidate", "No approved job role or candidate grant.", "Add the proper grant; do not make them admin."],
        ["CAPTCHA blocks test", "Protected public portal test needs human CAPTCHA handling or approved bypass.", "Use one protected test only; do not remove public anti-spam without a safer replacement."],
    ], [1.75 * inch, 2.25 * inch, 2.9 * inch]))

    story.append(PageBreak())
    story.append(Paragraph("9. Protected Test Protocol", styles["H1"]))
    story.extend(section("Run Only In The Protected Craig Scope", [
        p("Exact dashboard URL: <b>https://careers.nesportsphoto.com/index.php?m=nesp</b><br/>Login prerequisite: sign in first at <b>https://careers.nesportsphoto.com/index.php?m=login</b> as Craig/admin. Use synthetic candidates, jobs, interviewer profiles, and exported files only. Never place credentials, applicant PII, real Zoom links, or calendar links in this guide or a shared test log.", styles["Body"]),
        make_table([
            ["Test", "Action", "Success assertion"],
            ["Login and scope", "Create a synthetic interviewer, click Prepare Login, verify disabled, then activate only for this test.", "Assigned candidate/job is visible; unassigned candidates/jobs, Settings, flags, credentials, and status controls are denied or absent; role is read-only nesp_interviewer."],
            ["Questionnaire", "Verify the role and question set before Generate; use Field Staff Pre-Interview for 41005 and Photographer Pre-Interview for 41002/41003.", "Correct set/version is shown; generated, copied, manually sent, and response received remain separate states."],
            ["Duplicate safety", "Repeat the same synthetic candidate/job/link check before generating another link or scheduling.", "No duplicate candidate, questionnaire, or interview is created; stop on any duplicate warning."],
            ["Response", "Submit one synthetic questionnaire response and refresh the dashboard.", "Response is visible for human review; no automatic ranking, rejection, hiring, or stage movement occurs."],
            ["Forecast", "Upload an exported .xlsx or .csv file, run Dry-Run, and inspect every row and warning.", "Temporary batch shows no persisted source rows; only explicitly reviewed rows may be selected, with duplicate checks visible."],
        ], [1.25 * inch, 2.65 * inch, 3.0 * inch]),
        p("<b>Stop before send:</b> copying a link is not sending it. Stop before any manual send unless separately approved. <b>Stop before import:</b> do not click Import Approved Rows; do not connect Google Sheets, calendars, Zoom, or contact channels. Do not enable flags or activate real users.", styles["Callout"]),
        p("Cleanup: sign out, suspend the synthetic interviewer, verify it cannot sign in, discard the temporary workbook, clear temporary row selections, and verify no synthetic rows were imported. Escalate immediately if a send, import, provider connection, or real-record action starts.", styles["Body"]),
    ], styles))

    story.append(PageBreak())
    story.append(Paragraph("10. Phone Checklist", styles["H1"]))
    story.extend(section("Before Work", [
        *bullet([
            "Open the dashboard URL; sign in at the login URL as Craig/admin first.",
            "Confirm the release pin and record feature/integration states. Keep NESP automatic contact and external integrations disabled.",
            "Use synthetic records/files only. Keep credentials, applicant PII, and real Zoom/calendar links out of notes.",
        ], styles["Body"]),
    ], styles))
    story.extend(section("Questionnaires", [
        *bullet([
            "Field Staff/Table Greeter 41005: Field Staff Pre-Interview first.",
            "Staff Photographer 41002 and Freelance Photographer 41003: Photographer Pre-Interview.",
            "Record generated, copied, manually sent, and response received separately; stop if the role or set is unclear.",
        ], styles["Body"]),
    ], styles))
    story.extend(section("Interviewer And Forecast", [
        *bullet([
            "Interviewer Settings: click Prepare Login; verify disabled, then activate only for the protected test.",
            "Check assigned access and denied direct URLs; sign out and suspend the synthetic login afterward.",
            "Forecast: upload exported .xlsx/.csv, run Dry-Run, inspect every row and duplicate warning.",
            "Only reviewed rows may be selected. Stop before Import Approved Rows; no direct Google Sheets integration.",
            "Clear selections, discard the temporary file, and verify no rows persisted. Never send, import, connect, or enable from this checklist.",
        ], styles["Body"]),
    ], styles))

    doc.build(story)


if __name__ == "__main__":
    build()
