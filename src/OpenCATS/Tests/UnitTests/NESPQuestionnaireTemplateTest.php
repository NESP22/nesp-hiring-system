<?php
use PHPUnit\Framework\TestCase;

class NESPQuestionnaireTemplateTest extends TestCase
{
    public function testAdminQuestionnaireTemplatesUseExistingNespBrandAsset()
    {
        $templates = array(
            'modules/nesp/Questionnaires.tpl',
            'modules/nesp/QuestionSets.tpl',
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
        $sets = file_get_contents('modules/nesp/QuestionSets.tpl');
        $public = file_get_contents('modules/nesp/screeningQuestionnaire.php');

        $this->assertStringContainsString('a=requestQuestionnaire', $confirm);
        $this->assertStringContainsString('name="csrfToken"', $confirm);
        $this->assertStringContainsString('a=saveQuestionnaireReview', $review);
        $this->assertStringContainsString('name="csrfToken"', $review);
        $this->assertStringContainsString('a=publishQuestionSetDraft', $sets);
        $this->assertStringContainsString('name="csrfToken"', $sets);
        $this->assertStringContainsString('Published versions are immutable', $sets);
        $this->assertStringContainsString('submitQuestionnaireFromToken($token, $answers)', $public);
        $this->assertStringContainsString('requestQuestionnaireHumanFollowUpFromToken($token)', $public);
        $this->assertStringNotContainsString('link_url', $confirm . $review . $public);
        $this->assertStringNotContainsString('invitation_copy_text', $confirm . $review . $public);
    }

    public function testInterviewerSettingsUsesStateGatedLifecycleActions()
    {
        $settings = file_get_contents('modules/nesp/Settings.tpl');
        $controller = file_get_contents('modules/nesp/NESPUI.php');

        foreach (array('prepareInterviewerLogin', 'activateInterviewerLogin', 'suspendInterviewerLogin', 'reactivateInterviewerLogin', 'resetInterviewerTempPassword', 'disableInterviewerLogin', 'revokeCandidateGrant', 'deactivateInterviewerRoleRule') as $action)
        {
            $this->assertStringContainsString('a=' . $action, $settings);
            $this->assertStringContainsString("case '" . $action . "'", $controller);
        }
        $this->assertStringContainsString('Copy-only login details.', $settings);
        $this->assertStringContainsString('The app has not sent this to anyone.', $settings);
        $this->assertStringContainsString('Copy Login Details', $settings);
        $this->assertStringContainsString('data-label="Name"', $settings);
        $this->assertStringContainsString('data-label="Candidate"', $settings);
        $this->assertStringContainsString('name="roleRuleID"', $settings);
        $this->assertStringContainsString('Remove Rule', $settings);
        $this->assertStringNotContainsString('name="linkedUserID"', $settings);
        $this->assertStringNotContainsString('name="isActive"', $settings);
    }

    public function testLegacyForgotPasswordBlocksNespInterviewerMailerPath()
    {
        $source = file_get_contents('modules/login/LoginUI.php');

        $this->assertStringContainsString('forgotPasswordBlockedForScopedInterviewer', $source);
        $this->assertStringContainsString("trim((string) \$row['categories']) === 'nesp_interviewer'", $source);
        $this->assertMatchesRegularExpression('/forgotPasswordBlockedForScopedInterviewer\(\$username\).*?return;.*?\$user = new Users\(\)/s', $source);
    }

    public function testNespWorkflowCssKeepsBrandSelectorsScoped()
    {
        $source = file_get_contents('modules/nesp/nespWorkflow.css');
        $lines = preg_split('/\R/', $source);

        foreach ($lines as $lineNumber => $line)
        {
            if (preg_match('/^\s*(?:\.nesp-(?!workflow\b)|button\.nesp-)/', $line))
            {
                $this->fail(sprintf(
                    'NESP branding selector must be scoped under .nesp-workflow on line %d: %s',
                    $lineNumber + 1,
                    trim($line)
                ));
            }
        }

        $this->assertStringContainsString('.nesp-workflow .nesp-page-title', $source);
        $this->assertStringContainsString('.nesp-workflow .nesp-brand-lockup', $source);
        $this->assertStringContainsString('.nesp-workflow .nesp-table', $source);
    }

    public function testNespWorkflowCssKeepsDashboardHeadersOnDarkTheme()
    {
        $source = file_get_contents('modules/nesp/nespWorkflow.css');
        $darkThemeSelectors = array(
            '.nesp-workflow #contents',
            '.nesp-workflow .nesp-page-title',
            '.nesp-workflow .nesp-dashboard-nav a',
            '.nesp-workflow .nesp-card',
            '.nesp-workflow .nesp-panel',
            '.nesp-workflow .nesp-task-card',
            '.nesp-workflow .nesp-empty',
            '.nesp-workflow .nesp-table th'
        );

        foreach ($darkThemeSelectors as $selector)
        {
            $this->assertMatchesRegularExpression(
                '/' . preg_quote($selector, '/') . '[^{]*\{[^}]*background:\s*(?!#fff(?:fff)?\b|#f[0-9a-f]{5}\b|white\b)[^;}]+;/is',
                $source,
                sprintf('Expected %s to define a non-white dashboard background.', $selector)
            );
        }

        $this->assertStringContainsString('--nesp-dashboard-bg: #061f46;', $source);
        $this->assertStringContainsString('--nesp-dashboard-panel: #0b2b57;', $source);
        $this->assertStringContainsString('--nesp-dashboard-text: #eef5ff;', $source);
        $this->assertMatchesRegularExpression('/\.nesp-workflow \.nesp-secondary-action:focus\s*\{?|\b\.nesp-workflow \.nesp-secondary-action:hover,/s', $source);
    }

    public function testLegacyNavigationCssCentersOpenCatsTabs()
    {
        $source = file_get_contents('main.css');

        $this->assertMatchesRegularExpression('/#header\s*\{[^}]*clear:\s*both;/s', $source);
        $this->assertMatchesRegularExpression('/#header\s*\{[^}]*height:\s*auto;/s', $source);
        $this->assertMatchesRegularExpression('/#header ul#primary\s*\{[^}]*position:\s*static;/s', $source);
        $this->assertMatchesRegularExpression('/#header ul#primary\s*\{[^}]*display:\s*flex;/s', $source);
        $this->assertMatchesRegularExpression('/#header ul#primary\s*\{[^}]*flex-wrap:\s*wrap;/s', $source);
        $this->assertMatchesRegularExpression('/#header ul#primary\s*\{[^}]*width:\s*auto;/s', $source);
        $this->assertMatchesRegularExpression('/#header ul#primary > li\s*\{[^}]*display:\s*contents;/s', $source);
        $this->assertMatchesRegularExpression('/#header ul#primary a\.inactive\s*\{[^}]*display:\s*flex;/s', $source);
        $this->assertMatchesRegularExpression('/#header ul#primary a\.inactive\s*\{[^}]*align-items:\s*center;/s', $source);
        $this->assertMatchesRegularExpression('/#header ul#primary a\.inactive\s*\{[^}]*float:\s*none;/s', $source);
        $this->assertMatchesRegularExpression('/#header ul#primary a\.inactive\s*\{[^}]*width:\s*auto;/s', $source);
        $this->assertMatchesRegularExpression('/#header ul#primary a\.inactive\s*\{[^}]*min-width:\s*70px;/s', $source);
        $this->assertMatchesRegularExpression('/#header ul#primary a\.inactive\s*\{[^}]*background:\s*#E2E2E2;/s', $source);
        $this->assertMatchesRegularExpression('/#header ul#primary a\.active\s*\{[^}]*display:\s*flex;/s', $source);
        $this->assertMatchesRegularExpression('/#header ul#primary a\.active\s*\{[^}]*line-height:\s*normal;/s', $source);
        $this->assertMatchesRegularExpression('/#header ul#primary a\.active\s*\{[^}]*float:\s*none;/s', $source);
        $this->assertMatchesRegularExpression('/#header ul#primary a\.active\s*\{[^}]*width:\s*auto;/s', $source);
        $this->assertMatchesRegularExpression('/#header ul#primary a\.active\s*\{[^}]*background:\s*#6c94eb;/s', $source);
        $this->assertMatchesRegularExpression('/#header ul#primary a\.inactive:focus,\s*#header ul#primary a\.active:focus\s*\{[^}]*outline:\s*2px solid #315f93;/s', $source);
        $this->assertDoesNotMatchRegularExpression('/#header ul#primary a\.(?:inactive|active)\s*\{[^}]*url\(images\/tabs\//s', $source);
        $this->assertDoesNotMatchRegularExpression('/#header ul#primary a\.(?:inactive|active)\s*\{[^}]*width:\s*81px;/s', $source);
        $this->assertDoesNotMatchRegularExpression('/#header ul#primary a\.(?:inactive|active)\s*\{[^}]*float:\s*left;/s', $source);
        $this->assertMatchesRegularExpression('/#header ul#secondary\s*\{[^}]*position:\s*static;/s', $source);
        $this->assertMatchesRegularExpression('/#header ul#secondary\s*\{[^}]*display:\s*flex;/s', $source);
        $this->assertMatchesRegularExpression('/#header ul#secondary\s*\{[^}]*flex-wrap:\s*wrap;/s', $source);
        $this->assertMatchesRegularExpression('/#header ul#secondary\s*\{[^}]*width:\s*100%;/s', $source);
        $this->assertMatchesRegularExpression('/#header ul#secondary\s*\{[^}]*min-height:\s*24px;/s', $source);
        $this->assertMatchesRegularExpression('/#header ul#secondary li a\s*\{[^}]*float:\s*none;/s', $source);
        $this->assertMatchesRegularExpression('/#header ul#secondary li a\s*\{[^}]*white-space:\s*nowrap;/s', $source);
        $this->assertMatchesRegularExpression('/#header ul#secondary li span\s*\{[^}]*float:\s*none;/s', $source);
        $this->assertMatchesRegularExpression('/#header ul#secondary li span\s*\{[^}]*white-space:\s*nowrap;/s', $source);
        $this->assertMatchesRegularExpression('/#main\s*\{[^}]*padding-top:\s*0px;/s', $source);
    }
}
