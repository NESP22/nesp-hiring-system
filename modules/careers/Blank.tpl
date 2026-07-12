<?php
global $careerPage;
$isNESPCareers = trim((string) $this->siteName) === 'New England Sports Photo';
$careerAssetPrefix = (isset($careerPage) && $careerPage == true ? '../' : '');
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=<?php echo(HTML_ENCODING); ?>" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title><?php $this->_($this->siteName); ?> - Careers</title>
            <script type="text/javascript" src="<?php echo '../' . TemplateUtility::getVersionedAssetURL('js/careerPortalApply.js'); ?>"></script>
        <?php global $careerPage; if (isset($careerPage) && $careerPage == true): ?>
            <script type="text/javascript" src="<?php echo '../' . TemplateUtility::getVersionedAssetURL('js/lib.js'); ?>"></script>
            <script type="text/javascript" src="<?php echo '../' . TemplateUtility::getVersionedAssetURL('js/sorttable.js'); ?>"></script>
            <script type="text/javascript" src="<?php echo '../' . TemplateUtility::getVersionedAssetURL('js/calendarDateInput.js'); ?>"></script>
        <?php else: ?>
            <script type="text/javascript" src="<?php echo TemplateUtility::getVersionedAssetURL('js/lib.js'); ?>"></script>
            <script type="text/javascript" src="<?php echo TemplateUtility::getVersionedAssetURL('js/sorttable.js'); ?>"></script>
            <script type="text/javascript" src="<?php echo TemplateUtility::getVersionedAssetURL('js/calendarDateInput.js'); ?>"></script>
			<script type="text/javascript" src="<?php echo TemplateUtility::getVersionedAssetURL('js/careersPage.js'); ?>"></script>
        <?php endif; ?>
        <?php if ($isNESPCareers): ?>
            <link rel="stylesheet" type="text/css" href="<?php echo $careerAssetPrefix . TemplateUtility::getVersionedAssetURL('modules/careers/nespCareers.css'); ?>" />
        <?php endif; ?>
        <style type="text/css" media="all">
            <?php echo($this->template['CSS']); ?>
			#poweredCATS { clear: both; margin: 30px auto; clear: both; width: 140px; height: 40px; border: none;}
			#poweredCATS img { border: none; }
        </style>
    </head>
    <body<?php if ($isNESPCareers): ?> class="nesp-careers-page"<?php endif; ?>>
    <?php if ($isNESPCareers): ?>
    <div id="nespCareers">
        <header class="nesp-header">
            <div class="nesp-logo-mark">
                <img src="<?php echo $careerAssetPrefix . TemplateUtility::getVersionedAssetURL('images/nesp-logo.png'); ?>" alt="New England Sports Photo logo" />
            </div>
            <div class="nesp-brand">
                <p class="nesp-kicker">Family-owned since 1975</p>
                <h1>New England Sports Photo</h1>
                <p class="nesp-careers-label">Careers</p>
                <p>Youth sports photography across New England</p>
                <p class="nesp-location">Methuen, Massachusetts</p>
            </div>
        </header>
    <?php endif; ?>
    <!-- TOP -->
    <?php echo($this->template['Header']); ?>

    <!-- CONTENT -->
    <?php echo($this->template['Content']); ?>

    <!-- FOOTER -->
    <?php echo($this->template['Footer']); ?>
    <?php if ($isNESPCareers): ?>
        <footer class="nesp-footer">
            <strong>Local review only.</strong> These draft job postings are prepared for NESP review and are not published externally.
        </footer>
    </div>
    <?php endif; ?>
    <div style="font-size:9px;">
        <br /><br /><br /><br />
    </div>
    <div style="text-align:center;">

        <?php /* WARNING: It is against the terms of the CPL to remove or alter the following line.  The 'Powered by OpenCATS' line must stay visible on every page. */ ?>
        <div id="poweredCATS">
		<a href="http://www.opencats.org" target="_blank"><img src="../images/CATS-powered.gif" alt="Powered by: OpenCATS - Applicant Tracking System" title="Powered by: OpenCATS - Applicant Tracking System" /></a>
		</div>
    </div>
    <script type="text/javascript">st_init();</script>
    </body>
</html>
