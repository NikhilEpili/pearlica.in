-- Instagram reels / posts for homepage "Your Favourite Influencers" carousel.
-- Safe to run on existing databases.

CREATE TABLE IF NOT EXISTS `instagram_reels` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `instagram_url` VARCHAR(512) NULL DEFAULT NULL,
  `media_type` VARCHAR(8) NOT NULL DEFAULT 'reel',
  `shortcode` VARCHAR(64) NOT NULL DEFAULT '',
  `product_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `published_sort_order` INT NULL DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 0,
  `video_path` VARCHAR(255) NULL DEFAULT NULL,
  `poster_path` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  INDEX `idx_instagram_reels_list` (`is_active`, `sort_order`, `deleted_at`),
  FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ALTER TABLE `admin_user_permissions`
--   ADD COLUMN `can_reels` TINYINT(1) NOT NULL DEFAULT 1 AFTER `can_faqs`;
-- (Also applied automatically by instagram_reel_ensure_schema() on admin/homepage load.)

-- Self-hosted reel media. Uploaded videos autoplay muted on the homepage carousel;
-- Instagram embeds cannot autoplay, so uploads are the preferred source.
-- (Also applied automatically by instagram_reel_ensure_schema().)
-- ALTER TABLE `instagram_reels`
--   ADD COLUMN `video_path` VARCHAR(255) NULL DEFAULT NULL,
--   ADD COLUMN `poster_path` VARCHAR(255) NULL DEFAULT NULL,
--   ADD COLUMN `published_sort_order` INT NULL DEFAULT NULL,
--   MODIFY `instagram_url` VARCHAR(512) NULL DEFAULT NULL;
