-- Site policies (admin-editable). Safe to run CREATE TABLE repeatedly.
CREATE TABLE IF NOT EXISTS `site_policies` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `slug` VARCHAR(64) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `lead_text` VARCHAR(255) NOT NULL DEFAULT '',
  `effective_note` VARCHAR(255) NOT NULL DEFAULT '',
  `meta_title` VARCHAR(255) NOT NULL DEFAULT '',
  `meta_description` VARCHAR(512) NOT NULL DEFAULT '',
  `body_html` MEDIUMTEXT NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_site_policies_slug` (`slug`),
  INDEX `idx_site_policies_sort` (`sort_order`, `slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional (skip if column already exists):
-- ALTER TABLE `admin_user_permissions`
--   ADD COLUMN `can_policies` TINYINT(1) NOT NULL DEFAULT 1 AFTER `can_faqs`;
