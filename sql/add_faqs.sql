-- FAQs table + admin permission (safe to run more than once for CREATE TABLE).
CREATE TABLE IF NOT EXISTS `faqs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `question` VARCHAR(512) NOT NULL,
  `answer` TEXT NOT NULL,
  `answer_html` TEXT NULL DEFAULT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  INDEX `idx_faqs_list` (`is_active`, `sort_order`, `deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add can_faqs if missing (skip if the column already exists):
-- ALTER TABLE `admin_user_permissions`
--   ADD COLUMN `can_faqs` TINYINT(1) NOT NULL DEFAULT 1 AFTER `can_banners`;
