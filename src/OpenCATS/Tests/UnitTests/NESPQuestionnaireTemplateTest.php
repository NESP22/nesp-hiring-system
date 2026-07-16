<?php
use PHPUnit\Framework\TestCase;

class NESPQuestionnaireTemplateTest extends TestCase
{
    public function testAdminQuestionnaireTemplatesUseExistingNespBrandAsset()
    {
        $templates = array(
            'modules/nesp/Questionnaires.tpl',
            'modules/nesp/QuestionnaireConfirm.tpl',
            'modules/nesp/QuestionnaireReview.tpl'
        );

        $this->assertFileExists('images/nesp-logo.png');
        foreach ($templates as $template)
        {
            $source = file_get_contents($template);
            $this->assertStringContainsString('images/nesp-logo.png', $source);
            $this->assertStringContainsString('New England Sports Photo', $source);
            $this->assertStringContainsString('nesp-brand-lockup', $source);
        }
    }

    public function testPublicQuestionnaireKeepsEscapedDynamicFieldsAndNespBranding()
    {
        $source = file_get_contents('modules/nesp/screeningQuestionnaire.php');

        $this->assertStringContainsString('../../images/nesp-logo.png', $source);
        $this->assertStringContainsString('New England Sports Photo', $source);
        $this->assertStringContainsString('Questionnaire progress', $source);
        $this->assertStringContainsString('aria-required="true"', $source);
        $this->assertStringContainsString("nesp_questionnaire_escape(\$questionnaire['role_title'])", $source);
        $this->assertStringContainsString("nesp_questionnaire_escape(\$token)", $source);
        $this->assertStringContainsString("nesp_questionnaire_escape(\$_SESSION['nesp_questionnaire_csrf'])", $source);
    }

    public function testQuestionnaireTemplatesKeepExistingActionsAndSecurityFields()
    {
        $confirm = file_get_contents('modules/nesp/QuestionnaireConfirm.tpl');
        $review = file_get_contents('modules/nesp/QuestionnaireReview.tpl');
        $public = file_get_contents('modules/nesp/screeningQuestionnaire.php');

        $this->assertStringContainsString('a=requestQuestionnaire', $confirm);
        $this->assertStringContainsString('name="csrfToken"', $confirm);
        $this->assertStringContainsString('a=saveQuestionnaireReview', $review);
        $this->assertStringContainsString('name="csrfToken"', $review);
        $this->assertStringContainsString('submitQuestionnaireFromToken($token, $answers)', $public);
        $this->assertStringContainsString('requestQuestionnaireHumanFollowUpFromToken($token)', $public);
        $this->assertStringNotContainsString('link_url', $confirm . $review . $public);
        $this->assertStringNotContainsString('invitation_copy_text', $confirm . $review . $public);
    }
}
