/*
 * NESP recruiting ads rollback migration.
 *
 * Removes only the draft job-ad campaign-control table added by
 * db/nesp_recruiting_ads_additive.sql.
 */

DROP TABLE IF EXISTS `nesp_recruiting_campaign_control`;
