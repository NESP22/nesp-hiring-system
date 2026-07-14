<?php
namespace OpenCATS\Tests\IntegrationTests;

class NESPWorkflowSchemaTest extends DatabaseTestCase
{
    public function testNESPWorkflowTablesAreCreated()
    {
        $expectedTables = array(
            'nesp_feature_flag',
            'nesp_workflow_stage',
            'nesp_candidate_workflow',
            'nesp_interviewer_profile',
            'nesp_interviewer_candidate_grant',
            'nesp_interviewer_role_rule',
            'nesp_interviewer_availability',
            'nesp_interview_slot',
            'nesp_interview',
            'nesp_scorecard_template',
            'nesp_scorecard_response',
            'nesp_integration_status',
            'nesp_vapi_phone_screen',
            'nesp_zoom_interview',
            'nesp_ai_candidate_review',
            'nesp_audit_event',
            'nesp_session_security_event',
            'nesp_staffing_schedule_history',
            'nesp_staffing_import_batch',
            'nesp_staffing_import_row',
            'nesp_staffing_import_issue',
            'nesp_staffing_forecast',
            'nesp_staffing_recommendation'
        );

        foreach ($expectedTables as $table)
        {
            $this->assertSame(1, $this->countMatchingTables($table), $table . ' table is missing.');
        }
    }

    public function testNESPWorkflowSeedDataIsSafeByDefault()
    {
        $this->assertSame(8, $this->countRows('nesp_feature_flag'));
        $this->assertSame(0, $this->countRowsWhere('nesp_feature_flag', 'is_enabled = 1'));
        $this->assertSame(17, $this->countRows('nesp_workflow_stage'));
        $this->assertSame(4, $this->countRowsWhere('nesp_integration_status', "status_key = 'disabled'"));
        $this->assertSame(0, $this->countRows('nesp_interviewer_profile'));
        $this->assertSame(0, $this->countRows('nesp_interviewer_role_rule'));
        $this->assertSame(0, $this->countRows('nesp_interviewer_availability'));
        $this->assertSame(0, $this->countRows('nesp_interview_slot'));
        $this->assertSame(0, $this->countRows('nesp_candidate_workflow'));
        $this->assertSame(1, $this->countRowsWhere('nesp_scorecard_template', "template_key = 'nesp_standard_interview' AND is_enabled = 0"));
        $this->assertSame(8, $this->countRowsWhere('nesp_feature_flag', "flag_key LIKE 'NESP_%'"));
    }

    public function testNESPPhase2ColumnsArePresent()
    {
        $this->assertSame(1, $this->countMatchingColumns('nesp_candidate_workflow', 'waiting_on_key'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_candidate_workflow', 'summary'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_candidate_workflow', 'next_action_label'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_candidate_workflow', 'due_at'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_interviewer_profile', 'can_add_notes'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_interviewer_profile', 'can_submit_scorecard'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_scorecard_response', 'locked_at'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_scorecard_response', 'unlocked_at'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_scorecard_response', 'unlocked_by_user_id'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_interviewer_role_rule', 'assignment_mode'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_interviewer_availability', 'slot_minutes'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_interview_slot', 'zoom_status_key'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_staffing_import_batch', 'undone_at'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_staffing_import_row', 'source_row_hash'));
        $this->assertSame(1, $this->countMatchingColumns('nesp_staffing_import_issue', 'status_key'));
        $this->assertSame(0, $this->countUniqueIndexes('nesp_staffing_import_batch', 'IDX_nesp_import_source'));
    }

    private function countMatchingTables($table)
    {
        global $mySQLConnection;

        $result = mysqli_query(
            $mySQLConnection,
            sprintf("SHOW TABLES LIKE '%s'", mysqli_real_escape_string($mySQLConnection, $table))
        );

        return mysqli_num_rows($result);
    }

    private function countRows($table)
    {
        return $this->countRowsWhere($table, '1 = 1');
    }

    private function countMatchingColumns($table, $column)
    {
        global $mySQLConnection;

        $result = mysqli_query(
            $mySQLConnection,
            sprintf(
                "SHOW COLUMNS FROM `%s` LIKE '%s'",
                mysqli_real_escape_string($mySQLConnection, $table),
                mysqli_real_escape_string($mySQLConnection, $column)
            )
        );

        return mysqli_num_rows($result);
    }

    private function countRowsWhere($table, $whereClause)
    {
        global $mySQLConnection;

        $result = mysqli_query(
            $mySQLConnection,
            sprintf(
                'SELECT COUNT(*) AS total FROM `%s` WHERE %s',
                mysqli_real_escape_string($mySQLConnection, $table),
                $whereClause
            )
        );
        $row = mysqli_fetch_assoc($result);

        return (int) $row['total'];
    }

    private function countUniqueIndexes($table, $index)
    {
        global $mySQLConnection;

        $result = mysqli_query(
            $mySQLConnection,
            sprintf(
                "SHOW INDEX FROM `%s` WHERE Key_name = '%s' AND Non_unique = 0",
                mysqli_real_escape_string($mySQLConnection, $table),
                mysqli_real_escape_string($mySQLConnection, $index)
            )
        );

        return mysqli_num_rows($result);
    }
}
