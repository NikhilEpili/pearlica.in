-- Homepage banners (admin-managed hero + mid-page images).
-- Run once on your MySQL database for this project.

CREATE TABLE IF NOT EXISTS `homepage_banners` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `image_url` VARCHAR(512) NOT NULL,
  `alt_text` VARCHAR(255) NOT NULL DEFAULT '',
  `link_url` VARCHAR(512) NULL DEFAULT NULL,
  `placement` VARCHAR(16) NOT NULL DEFAULT 'hero',
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  INDEX `idx_homepage_banners_list` (`placement`, `is_active`, `sort_order`, `deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional permission for non-super admins (safe if column already exists: run manually / ignore duplicate).
ALTER TABLE `admin_user_permissions`
  ADD COLUMN `can_banners` TINYINT(1) NOT NULL DEFAULT 1 AFTER `can_orders`;
