-- Blog posts table + admin permission (safe to run more than once for CREATE TABLE).
CREATE TABLE IF NOT EXISTS `blog_posts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(128) NOT NULL,
  `excerpt` TEXT NULL DEFAULT NULL,
  `body_html` MEDIUMTEXT NOT NULL,
  `cover_image` VARCHAR(512) NULL DEFAULT NULL,
  `meta_title` VARCHAR(255) NOT NULL DEFAULT '',
  `meta_description` VARCHAR(512) NOT NULL DEFAULT '',
  `published_at` DATETIME NULL DEFAULT NULL,
  `is_published` TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  UNIQUE KEY `uq_blog_posts_slug` (`slug`),
  INDEX `idx_blog_posts_list` (`is_published`, `published_at`, `deleted_at`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add can_blogs if missing (skip if the column already exists):
-- ALTER TABLE `admin_user_permissions`
--   ADD COLUMN `can_blogs` TINYINT(1) NOT NULL DEFAULT 1 AFTER `can_policies`;
