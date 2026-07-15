/*
 * NESP recruiting ads additive migration.
 *
 * Adds draft-only campaign controls for job-ad setup and manual spend
 * reporting. This migration does not publish ads, send messages, enable
 * integrations, or change candidate stages.
 */

CREATE TABLE IF NOT EXISTS `nesp_recruiting_campaign_control` (
  `recruiting_campaign_control_id` INT(11) NOT NULL AUTO_INCREMENT,
  `platform_key` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `display_name` VARCHAR(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `campaign_status` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `renewal_date` DATE,
  `manual_spend` DECIMAL(10,2) NOT NULL DEFAULT '0.00',
  `owner_approval_required` TINYINT(1) NOT NULL DEFAULT '1',
  `notes` TEXT COLLATE utf8mb4_unicode_ci,
  `updated_by_user_id` INT(11),
  `date_created` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  `date_modified` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  PRIMARY KEY (`recruiting_campaign_control_id`),
  UNIQUE KEY `IDX_platform_key` (`platform_key`),
  KEY `IDX_campaign_status` (`campaign_status`),
  KEY `IDX_renewal_date` (`renewal_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
