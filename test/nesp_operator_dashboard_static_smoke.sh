#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DASHBOARD="$ROOT_DIR/modules/nesp/Dashboard.tpl"
CSS="$ROOT_DIR/modules/nesp/nespWorkflow.css"
WORKFLOW="$ROOT_DIR/lib/NESPWorkflow.php"
FIXTURE="$ROOT_DIR/test/fixtures/nesp_operator_dashboard_render.html"

fail() {
    printf 'NESP operator dashboard smoke failed: %s\n' "$1" >&2
    exit 1
}

require_grep() {
    local pattern="$1"
    local file="$2"
    local message="$3"
    grep -q "$pattern" "$file" || fail "$message"
}

require_file() {
    [ -f "$1" ] || fail "missing $1"
}

require_file "$DASHBOARD"
require_file "$CSS"
require_file "$WORKFLOW"
require_file "$FIXTURE"

require_grep 'What needs my attention now' "$DASHBOARD" 'first screen question is missing'
require_grep 'Needs Me Now' "$DASHBOARD" 'Needs Me Now label is missing'
require_grep 'Waiting on Applicant' "$DASHBOARD" 'Waiting on Applicant label is missing'
require_grep 'Upcoming Interviews' "$DASHBOARD" 'Upcoming Interviews label is missing'
require_grep 'Recently Completed' "$DASHBOARD" 'Recently Completed label is missing'
require_grep 'Other hiring tools' "$DASHBOARD" 'secondary tools label is missing'
require_grep "'due_at' => \\\$row\\['due_at'\\]" "$WORKFLOW" 'due_at is not exposed for display'
require_grep 'nesp-operator-focus' "$CSS" 'operator focus styles are missing'
require_grep 'nesp-attention-grid' "$CSS" 'attention grid styles are missing'
require_grep 'flex-wrap: wrap;' "$CSS" 'navigation wrapping guard is missing'
require_grep '\.nesp-workflow \.nesp-data-table tr:first-child' "$CSS" 'mobile header collapse must be data-table scoped'

if grep -q '\.nesp-workflow \.nesp-table tr:first-child' "$CSS"; then
    fail 'generic mobile table header hiding can break key/value tables'
fi

python3 - "$DASHBOARD" <<'PY'
import pathlib
import sys

text = pathlib.Path(sys.argv[1]).read_text()
labels = ["Needs Me Now", "Waiting on Applicant", "Upcoming Interviews", "Recently Completed"]
positions = [text.find(label) for label in labels]
if any(position < 0 for position in positions):
    raise SystemExit("missing attention label")
if positions != sorted(positions):
    raise SystemExit("attention labels are not in the required order")

default_marker = "else\n                {\n                    $sections = array("
start = text.find(default_marker)
if start < 0:
    raise SystemExit("default dashboard section list not found")
end = text.find(");\n                }", start)
section_block = text[start:end]
required = ["'needsCraig'", "'waitingApplicant'", "'upcomingInterviews'", "'recentlyCompleted'"]
for item in required:
    if item not in section_block:
        raise SystemExit(f"default dashboard missing {item}")
if "'waitingInterviewer'" in section_block:
    raise SystemExit("waitingInterviewer should not be a first-screen default section")

task_card = text[text.find('<div class="nesp-task-card">'):text.find('</details>', text.find('<div class="nesp-task-card">'))]
if task_card.count('nesp-primary-action') != 1:
    raise SystemExit("candidate task card should have exactly one dominant action")
PY

require_grep 'Generate Secure Questionnaire Link' "$ROOT_DIR/modules/nesp/QuestionnaireConfirm.tpl" 'questionnaire action changed or missing'
require_grep 'Create Interview Preview' "$ROOT_DIR/modules/nesp/ScheduleInterview.tpl" 'interview scheduling action changed or missing'
require_grep 'Run Dry-Run' "$ROOT_DIR/modules/nesp/StaffingForecast.tpl" 'staffing dry-run action changed or missing'
require_grep 'Import Approved Rows' "$ROOT_DIR/modules/nesp/StaffingForecast.tpl" 'staffing import action changed or missing'

printf 'NESP operator dashboard smoke passed.\n'
