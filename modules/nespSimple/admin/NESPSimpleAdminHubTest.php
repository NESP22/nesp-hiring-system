<?php

use PHPUnit\Framework\TestCase;

include_once(LEGACY_ROOT . '/modules/nespSimple/admin/NESPSimpleAdminHub.php');

class NESPSimpleAdminHubFakeDatabase
{
    public $rows;

    public function __construct($rows)
    {
        $this->rows = $rows;
    }

    public function getAllAssoc($sql)
    {
        return $this->rows;
    }
}

class NESPSimpleAdminHubFakeWorkflow
{
    public $grantCalls = array();

    public function getQuestionnaireDetail($questionnaireID)
    {
        return array(
            'screening_questionnaire_id' => $questionnaireID,
            'question_snapshot_json' => json_encode(array(
                array(
                    'key' => 'availability',
                    'label' => 'Exact issued availability question',
                    'type' => 'long_text',
                    'required' => true,
                    'choices' => array(),
                    'sort_order' => 1
                )
            )),
            'questions' => array(
                array('key' => 'wrong', 'label' => 'Current copied question must not render')
            ),
            'answers' => array(
                array(
                    'question_key' => 'availability',
                    'question_label' => 'Exact issued availability question',
                    'answer_text' => 'Yes',
                    'sort_order' => 1
                )
            )
        );
    }

    public function getEligibleInterviewersForAssignment($jobOrderID)
    {
        return array(
            array(
                'interviewer_profile_id' => 4,
                'display_name' => 'Suthir'
            )
        );
    }

    public function createCandidateGrant($interviewerProfileID, $candidateID, $jobOrderID, $actorUserID)
    {
        $this->grantCalls[] = array($interviewerProfileID, $candidateID, $jobOrderID, $actorUserID);
        return 44;
    }
}

class NESPSimpleAdminHubFakeCandidates
{
    public function getResumes($candidateID)
    {
        return array(array('attachmentID' => 9, 'title' => 'Resume'));
    }
}

class NESPSimpleAdminHubFakeAttachments
{
    public function get($attachmentID)
    {
        return array(
            'title' => 'Resume',
            'originalFilename' => 'resume.pdf',
            'retrievalURL' => 'index.php?m=attachments&amp;a=getAttachment&amp;id=' . $attachmentID
        );
    }
}

class NESPSimpleAdminHubTest extends TestCase
{
    private function sampleRows()
    {
        return array(
            array(
                'candidate_workflow_id' => 1,
                'candidate_id' => 50,
                'joborder_id' => 41002,
                'last_activity' => '2026-07-23 12:00:00',
                'first_name' => 'Alex',
                'last_name' => 'Applicant',
                'email1' => 'alex@example.com',
                'phone_cell' => '555-0100',
                'phone_home' => '',
                'phone_work' => '',
                'source' => 'NESP Ad: Indeed',
                'role_title' => 'Staff Photographer',
                'stage_key' => 'needs_review',
                'stage_name' => 'Needs Craig',
                'screening_questionnaire_id' => 22,
                'questionnaire_status_key' => 'completed',
                'questionnaire_submitted_at' => '2026-07-23 11:00:00',
                'interview_id' => null,
                'interview_status_key' => null,
                'assigned_interviewer_names' => 'Suthir',
                'resume_count' => 1
            ),
            array(
                'candidate_workflow_id' => 2,
                'candidate_id' => 51,
                'joborder_id' => 41005,
                'last_activity' => '2026-07-23 12:10:00',
                'first_name' => '=Formula',
                'last_name' => 'Example',
                'email1' => '+formula@example.com',
                'phone_cell' => '',
                'phone_home' => '',
                'phone_work' => '',
                'source' => '',
                'role_title' => 'Field Assistant',
                'stage_key' => 'new',
                'stage_name' => 'New',
                'screening_questionnaire_id' => null,
                'questionnaire_status_key' => null,
                'questionnaire_submitted_at' => null,
                'interview_id' => 14,
                'interview_status_key' => 'scheduled',
                'assigned_interviewer_names' => '',
                'resume_count' => 0
            )
        );
    }

    private function hub(&$workflow = null)
    {
        $workflow = new NESPSimpleAdminHubFakeWorkflow();
        return new NESPSimpleAdminHub(
            new NESPSimpleAdminHubFakeDatabase($this->sampleRows()),
            $workflow,
            new NESPSimpleAdminHubFakeAttachments(),
            new NESPSimpleAdminHubFakeCandidates()
        );
    }

    public function testApplicantListDecoratesOperationalIndicatorsAndContact()
    {
        $workflow = null;
        $rows = $this->hub($workflow)->getApplicantRows();

        $this->assertCount(2, $rows);
        $this->assertSame('Alex Applicant', $rows[0]['candidate_name']);
        $this->assertSame('alex@example.com', $rows[0]['email1']);
        $this->assertSame('555-0100', $rows[0]['contact_phone']);
        $this->assertTrue($rows[0]['questionnaire_submitted']);
        $this->assertTrue($rows[0]['has_resume']);
        $this->assertSame('Not scheduled', $rows[0]['interview_status_label']);
        $this->assertSame('NESP Portal', $rows[1]['source_label']);
        $this->assertSame('Scheduled', $rows[1]['interview_status_label']);
    }

    public function testRoleSourceStatusAndSearchFiltersCompose()
    {
        $workflow = null;
        $rows = $this->hub($workflow)->getApplicantRows(array(
            'role' => '41002',
            'source' => 'NESP Ad: Indeed',
            'status' => 'needs_review',
            'search' => 'alex@example.com'
        ));

        $this->assertCount(1, $rows);
        $this->assertSame(50, $rows[0]['candidate_id']);
    }

    public function testDetailUsesOnlyImmutableSnapshotAndStoredAnswers()
    {
        $workflow = null;
        $detail = $this->hub($workflow)->getApplicantDetail(50, 41002);

        $this->assertTrue($detail['questionnaire']['exact_snapshot_available']);
        $this->assertSame(
            'Exact issued availability question',
            $detail['questionnaire']['snapshot_rows'][0]['question_label']
        );
        $this->assertSame('Yes', $detail['questionnaire']['snapshot_rows'][0]['answer_text']);
        $this->assertStringNotContainsString(
            'Current copied question',
            json_encode($detail['questionnaire']['snapshot_rows'])
        );
        $this->assertSame('resume.pdf', $detail['resumes'][0]['original_filename']);
        $this->assertSame('Suthir', $detail['eligible_interviewers'][0]['display_name']);
    }

    public function testMissingSnapshotNeverFallsBackToCopiedQuestions()
    {
        $detail = NESPSimpleAdminHub::decorateExactQuestionnaireSnapshot(array(
            'question_snapshot_json' => '',
            'questions' => array(array('key' => 'current', 'label' => 'Current question')),
            'answers' => array()
        ));

        $this->assertFalse($detail['exact_snapshot_available']);
        $this->assertSame(array(), $detail['snapshot_rows']);
    }

    public function testAssignmentUsesExistingWorkflowGrantAPI()
    {
        $workflow = null;
        $result = $this->hub($workflow)->assignInterviewer(4, 50, 41002, 1);

        $this->assertTrue($result['ok']);
        $this->assertSame(44, $result['grant_id']);
        $this->assertSame(array(4, 50, 41002, 1), $workflow->grantCalls[0]);
    }

    public function testAssignmentRejectsInterviewerOutsideEligibleRole()
    {
        $workflow = null;
        $result = $this->hub($workflow)->assignInterviewer(99, 50, 41002, 1);

        $this->assertFalse($result['ok']);
        $this->assertSame(array(), $workflow->grantCalls);
    }

    public function testCSVIncludesSafeContactAndOperationalColumnsWithoutSecrets()
    {
        $workflow = null;
        $csv = $this->hub($workflow)->buildExportCSV();

        $this->assertStringContainsString('"Candidate ID",Candidate,Email,Phone,Job,Source', $csv);
        $this->assertStringContainsString('alex@example.com', $csv);
        $this->assertStringContainsString('555-0100', $csv);
        $this->assertStringContainsString("'=Formula Example", $csv);
        $this->assertStringContainsString("'+formula@example.com", $csv);
        $this->assertStringNotContainsString('token', strtolower($csv));
        $this->assertStringNotContainsString('password', strtolower($csv));
        $controller = file_get_contents(LEGACY_ROOT . '/modules/nespSimple/admin/NESPSimpleAdminUI.php');
        $this->assertStringContainsString('$this->_hub->buildExportCSV();', $controller);
    }

    public function testControllerAndTemplateEnforceAdminCSRFAndNoMessagingActions()
    {
        $controller = file_get_contents(LEGACY_ROOT . '/modules/nespSimple/admin/NESPSimpleAdminUI.php');
        $detail = file_get_contents(LEGACY_ROOT . '/modules/nespSimple/admin/Detail.tpl');
        $combined = $controller . $detail;

        $this->assertStringContainsString('$this->adminOnly();', $controller);
        $this->assertStringContainsString('$this->requirePostCSRF();', $controller);
        $this->assertStringContainsString('isCSRFTokenValid', $controller);
        $this->assertStringContainsString('name="csrfToken"', $detail);
        $this->assertStringContainsString('createCandidateGrant', file_get_contents(LEGACY_ROOT . '/modules/nespSimple/admin/NESPSimpleAdminHub.php'));
        $this->assertStringNotContainsString('sendQuestionnaire', $combined);
        $this->assertStringNotContainsString('sendKoalendar', $combined);
        $this->assertStringNotContainsString('sendEmail', $combined);
    }

    public function testEveryNewFileStaysInsideApprovedModuleDirectory()
    {
        $files = array(
            'NESPSimpleAdminHub.php',
            'NESPSimpleAdminUI.php',
            'Hub.tpl',
            'Detail.tpl',
            'nespSimpleAdmin.css',
            'NESPSimpleAdminHubTest.php'
        );
        foreach ($files as $file)
        {
            $this->assertFileExists(LEGACY_ROOT . '/modules/nespSimple/admin/' . $file);
        }
    }
}
