<?php
/*
 * NESP recruiting ad planning helpers.
 *
 * This class prepares draft-only ad copy, tracking links, and owner controls.
 * It does not publish ads, create campaigns, spend money, or send messages.
 */

class NESPRecruitingAds
{
    const CAREERS_BASE_URL = 'https://careers.nesportsphoto.com/careers/?p=applyToJob&ID=';

    public static function getSourceOptions()
    {
        return array(
            'nesp_website' => 'NESP website',
            'indeed' => 'Indeed',
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
            'linkedin' => 'LinkedIn',
            'craigslist' => 'Craigslist',
            'masshire' => 'MassHire',
            'handshake' => 'Handshake',
            'college_board' => 'College board',
            'referral' => 'Referral',
            'other' => 'Other'
        );
    }

    public static function normalizeSourceKey($value)
    {
        $key = strtolower(trim((string) $value));
        $key = str_replace(array('-', ' ', '.', '/'), '_', $key);

        $aliases = array(
            'nesp' => 'nesp_website',
            'website' => 'nesp_website',
            'careers' => 'nesp_website',
            'careers_site' => 'nesp_website',
            'google_business_profile' => 'other',
            'google_business' => 'other',
            'photography_school' => 'college_board',
            'community_group' => 'other'
        );

        if (isset($aliases[$key]))
        {
            $key = $aliases[$key];
        }

        $sources = self::getSourceOptions();
        return isset($sources[$key]) ? $key : '';
    }

    public static function getSourceLabel($sourceKey)
    {
        $sources = self::getSourceOptions();
        $sourceKey = self::normalizeSourceKey($sourceKey);
        return isset($sources[$sourceKey]) ? $sources[$sourceKey] : $sources['other'];
    }

    public static function getCandidateSourceLabel($sourceKey)
    {
        return 'NESP Ad: ' . self::getSourceLabel($sourceKey);
    }

    public static function sourceFromRequest($request)
    {
        foreach (array('nesp_source', 'utm_source', 'source') as $field)
        {
            if (isset($request[$field]))
            {
                $sourceKey = self::normalizeSourceKey($request[$field]);
                if ($sourceKey !== '')
                {
                    return self::getCandidateSourceLabel($sourceKey);
                }
            }
        }

        return '';
    }

    public static function trackedApplicationURL($jobID, $sourceKey)
    {
        $sourceKey = self::normalizeSourceKey($sourceKey);
        if ($sourceKey === '')
        {
            $sourceKey = 'other';
        }

        return self::CAREERS_BASE_URL . (int) $jobID . '&nesp_source=' . rawurlencode($sourceKey);
    }

    /**
     * The single approved destination for boards that permit an external
     * application URL. Native board applications remain review-only intake
     * records until an administrator explicitly imports them.
     */
    public static function getCentralApplicationDestinations()
    {
        $jobs = array(
            41001 => 'Part-Time Customer Service Representative',
            41002 => 'Staff Photographer',
            41003 => 'Freelance/Contract Youth Sports Photographer',
            41005 => 'Weekend Table Greeter / Field Assistant'
        );
        $routes = array(
            'craigslist' => array('Craigslist', 'Use the tracked OpenCATS link in the post body.'),
            'masshire' => array('MassHire', 'Use the external application URL when the form permits it.'),
            'handshake' => array('Handshake', 'Use the external application URL when the form permits it.'),
            'facebook' => array('Facebook', 'Use the tracked link in the post or call-to-action.'),
            'instagram' => array('Instagram', 'Use the tracked link where Instagram permits a clickable link.'),
            'college_board' => array('College boards', 'Use the external application URL when the board permits it.'),
            'other' => array('Other community boards', 'Use the tracked OpenCATS link when the board permits it.'),
            'indeed' => array('Indeed', 'Use the OpenCATS link only if Indeed offers an external application setting; otherwise use Inbox Review Intake.'),
            'linkedin' => array('LinkedIn', 'Use the OpenCATS link only if LinkedIn offers an external application setting; otherwise use Inbox Review Intake.')
        );

        $destinations = array();
        foreach ($jobs as $jobID => $jobTitle)
        {
            foreach ($routes as $sourceKey => $route)
            {
                $destinations[] = array(
                    'joborder_id' => $jobID,
                    'job_title' => $jobTitle,
                    'platform_key' => $sourceKey,
                    'platform' => $route[0],
                    'instructions' => $route[1],
                    'tracked_link' => self::trackedApplicationURL($jobID, $sourceKey),
                    'native_review_only' => in_array($sourceKey, array('indeed', 'linkedin'), true)
                );
            }
        }

        return $destinations;
    }

    public static function getPlatformMatrix()
    {
        return array(
            array('platform_key' => 'nesp_website', 'platform' => 'NESP careers website', 'role_types' => 'All approved active roles', 'cost_type' => 'Free', 'account_access' => 'NESP admin access', 'posting_method' => 'Internal job posting and tracked application links', 'image_dimensions' => 'Existing careers page assets', 'character_limits' => 'OpenCATS job description limits; keep copy concise', 'tracking_support' => 'Yes, nesp_source links', 'application_destination' => 'NESP application form', 'campaign_status' => 'Ready for Craig review', 'renewal_date' => 'None', 'approval_required' => 'Craig approval before making a role public'),
            array('platform_key' => 'indeed', 'platform' => 'Indeed', 'role_types' => 'Customer Service, seasonal photographers, field assistants', 'cost_type' => 'Free standard posts or paid sponsorship', 'account_access' => 'Employer account access', 'posting_method' => 'Manual posting unless approved integration exists', 'image_dimensions' => 'Logo/profile image; verify current Indeed employer specs before publishing', 'character_limits' => 'Use platform field limits shown during posting', 'tracking_support' => 'Yes, external application link', 'application_destination' => 'Tracked NESP application link', 'campaign_status' => 'Draft only', 'renewal_date' => 'Set after posting', 'approval_required' => 'Yes before publish or sponsorship'),
            array('platform_key' => 'facebook', 'platform' => 'Facebook', 'role_types' => 'Weekend field roles and local hiring posts', 'cost_type' => 'Free organic posts or paid boosts', 'account_access' => 'NESP page admin access', 'posting_method' => 'Manual organic post; paid boost only after approval', 'image_dimensions' => 'Square 1080x1080 or feed-safe image; verify current Meta specs', 'character_limits' => 'Keep primary text short; verify current limits before publishing', 'tracking_support' => 'Yes, tracked link in post', 'application_destination' => 'Tracked NESP application link', 'campaign_status' => 'Draft only', 'renewal_date' => 'Manual repost date', 'approval_required' => 'Yes before publish or paid boost'),
            array('platform_key' => 'instagram', 'platform' => 'Instagram', 'role_types' => 'Visual field roles, photographer roles, local community recruiting', 'cost_type' => 'Free organic posts or paid promotion', 'account_access' => 'NESP Instagram or connected Meta account', 'posting_method' => 'Manual post/story/reel; paid promotion only after approval', 'image_dimensions' => 'Square 1080x1080, portrait 1080x1350, story 1080x1920; verify current specs', 'character_limits' => 'Caption limit varies by surface; keep short and link through profile/story where allowed', 'tracking_support' => 'Limited; use tracked link where clickable links are allowed', 'application_destination' => 'Tracked NESP application link', 'campaign_status' => 'Draft only', 'renewal_date' => 'Manual repost date', 'approval_required' => 'Yes before publish or promotion'),
            array('platform_key' => 'linkedin', 'platform' => 'LinkedIn', 'role_types' => 'Customer Service, Scheduler / Office Support, Sales Representative', 'cost_type' => 'Free company post or paid job/post promotion', 'account_access' => 'LinkedIn company page or recruiter access', 'posting_method' => 'Manual post/job setup unless approved integration exists', 'image_dimensions' => '1200x627 social image or company logo; verify current specs', 'character_limits' => 'Use platform field limits shown during posting', 'tracking_support' => 'Yes, tracked external link', 'application_destination' => 'Tracked NESP application link', 'campaign_status' => 'Draft only', 'renewal_date' => 'Set after posting', 'approval_required' => 'Yes before publish or paid promotion'),
            array('platform_key' => 'craigslist', 'platform' => 'Craigslist', 'role_types' => 'Local customer service, seasonal photographer, field assistant roles', 'cost_type' => 'Usually paid by region/category', 'account_access' => 'Craiglist account access', 'posting_method' => 'Manual posting and payment confirmation by Craig', 'image_dimensions' => 'Optional images; verify current Craigslist upload rules', 'character_limits' => 'Plain-text friendly copy; verify current form limits', 'tracking_support' => 'Yes, tracked link in body where allowed', 'application_destination' => 'Tracked NESP application link', 'campaign_status' => 'Draft only', 'renewal_date' => 'Set to expiration shown by Craigslist', 'approval_required' => 'Yes before payment or publishing'),
            array('platform_key' => 'google_business_profile', 'platform' => 'Google Business Profile posts', 'role_types' => 'Local awareness and office roles', 'cost_type' => 'Free', 'account_access' => 'Google Business Profile manager access', 'posting_method' => 'Manual update/post', 'image_dimensions' => 'Square or landscape image; verify current Google profile specs', 'character_limits' => 'Short post text; verify current limits in Google profile UI', 'tracking_support' => 'Yes, tracked link button if supported', 'application_destination' => 'Tracked NESP application link', 'campaign_status' => 'Draft only', 'renewal_date' => 'Manual refresh date', 'approval_required' => 'Yes before publish'),
            array('platform_key' => 'college_board', 'platform' => 'Local college career boards', 'role_types' => 'Photographer, assistant, seasonal field roles, office support', 'cost_type' => 'Usually free or low-cost', 'account_access' => 'Employer account per school', 'posting_method' => 'Mostly manual; varies by school', 'image_dimensions' => 'Usually logo only; verify each board', 'character_limits' => 'Varies by board', 'tracking_support' => 'Yes if external link is allowed', 'application_destination' => 'Tracked NESP application link', 'campaign_status' => 'Draft only', 'renewal_date' => 'Set per board expiration', 'approval_required' => 'Yes before account terms or posting'),
            array('platform_key' => 'photography_school', 'platform' => 'Photography schools and programs', 'role_types' => 'Photographer and assistant roles', 'cost_type' => 'Usually free outreach or board posting', 'account_access' => 'School contact or employer account', 'posting_method' => 'Manual email/form/posting', 'image_dimensions' => 'Logo or flyer; verify each school', 'character_limits' => 'Varies by school', 'tracking_support' => 'Yes if link is allowed', 'application_destination' => 'Tracked NESP application link', 'campaign_status' => 'Draft only', 'renewal_date' => 'Manual follow-up date', 'approval_required' => 'Yes before outreach'),
            array('platform_key' => 'community_group', 'platform' => 'Local community groups', 'role_types' => 'Weekend field roles and local office roles', 'cost_type' => 'Free unless boosted', 'account_access' => 'Group membership and posting permission', 'posting_method' => 'Manual post; respect group rules', 'image_dimensions' => 'Square 1080x1080 is a safe draft size; verify group/platform rules', 'character_limits' => 'Keep short; varies by group', 'tracking_support' => 'Yes if links are allowed', 'application_destination' => 'Tracked NESP application link', 'campaign_status' => 'Draft only', 'renewal_date' => 'Manual repost date', 'approval_required' => 'Yes before posting')
        );
    }

    public static function getRequestedRoleAdTemplates()
    {
        $company = 'New England Sports Photo is a family-owned youth sports photography company with more than 50 years of experience serving leagues and families throughout New England.';
        $eeo = 'New England Sports Photo is an equal opportunity employer. Every application is reviewed by a person.';

        return array(
            self::approvedRole('weekend_sports_photographer', 41002, 'Weekend Sports Photographer', 'Weekend youth sports photographer - equipment provided', '$22-$25 per hour, based on experience', 'Primarily Massachusetts, with assignments in CT, RI, VT, and NH', 'Most assignments are Saturdays, with some Sundays and early-morning weekend availability', 'Photograph individual athletes and teams using NESP equipment; set up camera and lighting gear; work professionally with athletes, parents, coaches, and league staff; follow the NESP Picture Day workflow.', 'Reliable transportation and valid driver\'s license; early-morning weekend availability; comfort working with children and families; photography, sports, school, event, or customer-facing experience helpful; required background checks.', 'Standing, carrying photo equipment, and working at youth-sports fields and facilities.', 'Travel to assigned youth-sports locations is required.', $company, $eeo),
            self::missingRole('school_photographer', 'School Photographer', 'Craig needs to provide approved pay, schedule, territory, responsibilities, qualifications, physical requirements, travel expectations, and application destination before this ad can be used.'),
            self::missingRole('photography_assistant_poser', 'Photography Assistant / Poser', 'Repository marks Photography Assistant/Poser as inactive. Craig must approve whether this is the same as Weekend Table Greeter / Field Assistant or a separate role, then provide pay, schedule, and requirements.'),
            self::approvedRole('customer_service', 41001, 'Customer Service', 'Part-time customer service representative - Methuen, MA', '$22-$25 per hour, based on experience', 'In person at the NESP office in Methuen, Massachusetts', 'Monday-Friday on an agreed set daytime schedule, generally 9:00 a.m.-3:00 p.m. or 10:00 a.m.-4:00 p.m., year-round', 'Respond to customer emails, support tickets, and calls; help parents with online ordering and account questions; research shipping, delivery, order-status, reprint, and replacement requests; support league and school coordinators; escalate complex issues to Craig.', 'Customer-service or administrative experience preferred; strong written and verbal communication; comfortable with email, spreadsheets, support systems, and web applications; organized, detail-oriented, and dependable.', 'Office-based work using NESP systems.', 'In-person Methuen office work is required; no field travel listed.', $company, $eeo),
            self::missingRole('packing_production_staff', 'Packing / Production Staff', 'Craig needs to provide approved pay, location, schedule, responsibilities, qualifications, physical requirements, travel expectations, and application destination before this ad can be used.'),
            self::missingRole('scheduler_office_support', 'Scheduler / Office Support', 'Craig needs to provide approved pay, location, schedule, responsibilities, qualifications, physical requirements, travel expectations, and application destination before this ad can be used.'),
            self::missingRole('sales_representative', 'Sales Representative', 'Craig needs to provide approved pay or commission structure, location/territory, schedule, responsibilities, qualifications, travel expectations, and application destination before this ad can be used.')
        );
    }

    private static function approvedRole($roleKey, $jobID, $title, $headline, $compensation, $location, $schedule, $responsibilities, $qualifications, $physicalRequirements, $travel, $company, $eeo)
    {
        $short = $headline . '. Apply through NESP: ' . self::trackedApplicationURL($jobID, 'nesp_website');
        $full = $headline . "\n\n"
            . 'Compensation: ' . $compensation . "\n"
            . 'Location: ' . $location . "\n"
            . 'Schedule: ' . $schedule . "\n\n"
            . 'Responsibilities: ' . $responsibilities . "\n\n"
            . 'Qualifications: ' . $qualifications . "\n\n"
            . 'Physical requirements: ' . $physicalRequirements . "\n"
            . 'Travel expectations: ' . $travel . "\n\n"
            . $company . "\n\n"
            . 'Apply: ' . self::trackedApplicationURL($jobID, 'nesp_website') . "\n"
            . $eeo;

        return array(
            'role_key' => $roleKey,
            'title' => $title,
            'status' => 'Prepared draft',
            'headline' => $headline,
            'short_version' => $short,
            'full_version' => $full,
            'compensation' => $compensation,
            'location' => $location,
            'schedule' => $schedule,
            'responsibilities' => $responsibilities,
            'qualifications' => $qualifications,
            'physical_requirements' => $physicalRequirements,
            'travel_expectations' => $travel,
            'company_summary' => $company,
            'equal_opportunity' => $eeo,
            'application_link' => self::trackedApplicationURL($jobID, 'nesp_website'),
            'tracking_source_code' => 'nesp_source=SOURCE_KEY',
            'platform_versions' => self::platformVersions($jobID, $headline)
        );
    }

    private static function missingRole($roleKey, $title, $missing)
    {
        return array(
            'role_key' => $roleKey,
            'title' => $title,
            'status' => 'Missing Craig-approved fields',
            'headline' => '[Needs Craig approval] ' . $title,
            'short_version' => $missing,
            'full_version' => $missing,
            'compensation' => 'Missing',
            'location' => 'Missing',
            'schedule' => 'Missing',
            'responsibilities' => 'Missing',
            'qualifications' => 'Missing',
            'physical_requirements' => 'Missing',
            'travel_expectations' => 'Missing',
            'company_summary' => 'Approved NESP company summary may be used after role details are approved.',
            'equal_opportunity' => 'Use approved equal-opportunity language after role details are approved.',
            'application_link' => 'Missing',
            'tracking_source_code' => 'Not ready',
            'platform_versions' => array()
        );
    }

    private static function platformVersions($jobID, $headline)
    {
        $versions = array();
        foreach (self::getSourceOptions() as $sourceKey => $label)
        {
            $versions[] = array(
                'source_key' => $sourceKey,
                'platform' => $label,
                'tracking_link' => self::trackedApplicationURL($jobID, $sourceKey),
                'copy' => $headline . '. Apply through this NESP link: ' . self::trackedApplicationURL($jobID, $sourceKey)
            );
        }

        return $versions;
    }
}
