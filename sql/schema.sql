-- MySQL Database Schema for Pearlica storefront and admin panel

SET FOREIGN_KEY_CHECKS = 0;

-- 1. admin_users
DROP TABLE IF EXISTS `admin_users`;
CREATE TABLE `admin_users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `is_super_admin` TINYINT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin user (Username: admin, Password: admin123)
INSERT INTO `admin_users` (`id`, `username`, `password_hash`, `is_super_admin`) VALUES
(1, 'admin', '$2y$12$xuMydTZS9XkTmDrfNdfgruCHdHFdBuXZoBze0HNsCW/OD121nMmm2', 1);

-- 2. admin_user_permissions
DROP TABLE IF EXISTS `admin_user_permissions`;
CREATE TABLE `admin_user_permissions` (
  `user_id` INT PRIMARY KEY,
  `can_dashboard` TINYINT DEFAULT 1,
  `can_categories` TINYINT DEFAULT 1,
  `can_product_groups` TINYINT DEFAULT 1,
  `can_collections` TINYINT DEFAULT 1,
  `can_products` TINYINT DEFAULT 1,
  `can_orders` TINYINT DEFAULT 1,
  `can_banners` TINYINT DEFAULT 1,
  FOREIGN KEY (`user_id`) REFERENCES `admin_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. categories
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `parent_id` INT NULL DEFAULT NULL,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL UNIQUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. product_groups
DROP TABLE IF EXISTS `product_groups`;
CREATE TABLE `product_groups` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL UNIQUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. products
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT NOT NULL,
  `product_group_id` INT NULL DEFAULT NULL,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL UNIQUE,
  `description` TEXT NULL,
  `specification` TEXT NULL,
  `additional_information` TEXT NULL,
  `styling_tips` TEXT NULL,
  `status` VARCHAR(50) DEFAULT 'draft',
  `sku` VARCHAR(100) NULL DEFAULT NULL UNIQUE,
  `metal_type` VARCHAR(100) DEFAULT 'Silver',
  `purity` VARCHAR(50) DEFAULT '925',
  `weight_grams` DECIMAL(10, 4) NULL DEFAULT NULL,
  `size` VARCHAR(50) NULL DEFAULT NULL,
  `cost_price` DECIMAL(10, 2) NULL DEFAULT NULL,
  `mrp` DECIMAL(10, 2) NULL DEFAULT NULL,
  `discount_percentage` DECIMAL(5, 2) DEFAULT 0.00,
  `gst_percent` DECIMAL(5, 2) DEFAULT 3.00,
  `stock_quantity` INT DEFAULT 0,
  `is_gifting` TINYINT DEFAULT 0,
  `meta_title` VARCHAR(255) NULL DEFAULT NULL,
  `meta_description` TEXT NULL DEFAULT NULL,
  `meta_keywords` TEXT NULL DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_group_id`) REFERENCES `product_groups` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. product_media
DROP TABLE IF EXISTS `product_media`;
CREATE TABLE `product_media` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `media_type` VARCHAR(50) DEFAULT 'image',
  `url` VARCHAR(255) NOT NULL,
  `sort_order` INT DEFAULT 0,
  `is_primary` TINYINT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. product_specifications
DROP TABLE IF EXISTS `product_specifications`;
CREATE TABLE `product_specifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `spec_key` VARCHAR(255) NOT NULL,
  `spec_value` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. tags & product_tags
DROP TABLE IF EXISTS `tags`;
CREATE TABLE `tags` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `product_tags`;
CREATE TABLE `product_tags` (
  `product_id` INT NOT NULL,
  `tag_id` INT NOT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`product_id`, `tag_id`),
  FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. store_users
DROP TABLE IF EXISTS `store_users`;
CREATE TABLE `store_users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(255) NULL DEFAULT NULL UNIQUE,
  `full_name` VARCHAR(255) DEFAULT '',
  `phone` VARCHAR(100) NULL DEFAULT NULL,
  `address` VARCHAR(255) NULL DEFAULT NULL,
  `address_line_2` VARCHAR(255) NULL DEFAULT NULL,
  `city` VARCHAR(255) NULL DEFAULT NULL,
  `state` VARCHAR(255) NULL DEFAULT NULL,
  `pincode` VARCHAR(20) NULL DEFAULT NULL,
  `birthday` DATE NULL DEFAULT NULL,
  `gender` VARCHAR(50) NULL DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. login_otps
DROP TABLE IF EXISTS `login_otps`;
CREATE TABLE `login_otps` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(255) NOT NULL,
  `code` VARCHAR(10) NOT NULL,
  `expires_at` TIMESTAMP NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. promo_codes
DROP TABLE IF EXISTS `promo_codes`;
CREATE TABLE `promo_codes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `description` TEXT NULL,
  `discount_type` VARCHAR(20) DEFAULT 'percent',
  `discount_value` DECIMAL(10, 2) NOT NULL,
  `min_order_paise` INT DEFAULT 0,
  `valid_from` TIMESTAMP NULL DEFAULT NULL,
  `valid_until` TIMESTAMP NULL DEFAULT NULL,
  `usage_limit` INT NULL DEFAULT NULL,
  `used_count` INT DEFAULT 0,
  `is_active` TINYINT DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. orders
DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_number` VARCHAR(100) NOT NULL UNIQUE,
  `store_user_id` INT NULL DEFAULT NULL,
  `status` VARCHAR(50) DEFAULT 'pending_payment',
  `payment_method` VARCHAR(50) DEFAULT 'razorpay',
  `promo_code_id` INT NULL DEFAULT NULL,
  `subtotal_paise` INT DEFAULT 0,
  `shipping_paise` INT DEFAULT 0,
  `discount_paise` INT DEFAULT 0,
  `total_paise` INT DEFAULT 0,
  `shipping_name` VARCHAR(255) NOT NULL,
  `shipping_phone` VARCHAR(100) NOT NULL,
  `shipping_email` VARCHAR(255) NOT NULL,
  `shipping_address` TEXT NOT NULL,
  `shipping_city` VARCHAR(255) NOT NULL,
  `shipping_state` VARCHAR(255) NOT NULL,
  `shipping_pincode` VARCHAR(20) NOT NULL,
  `refrens_invoice_id` VARCHAR(64) NULL DEFAULT NULL,
  `paid_at` TIMESTAMP NULL DEFAULT NULL,
  `cancelled_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`store_user_id`) REFERENCES `store_users` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`promo_code_id`) REFERENCES `promo_codes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. order_items
DROP TABLE IF EXISTS `order_items`;
CREATE TABLE `order_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `product_id` INT NULL DEFAULT NULL,
  `sku` VARCHAR(100) NULL DEFAULT NULL,
  `product_name` VARCHAR(255) NOT NULL,
  `unit_price_paise` INT DEFAULT 0,
  `quantity` INT DEFAULT 1,
  `line_total_paise` INT DEFAULT 0,
  FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. payments
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `provider` VARCHAR(50) DEFAULT 'razorpay',
  `status` VARCHAR(50) DEFAULT 'created',
  `amount_paise` INT DEFAULT 0,
  `currency` VARCHAR(20) DEFAULT 'INR',
  `provider_order_id` VARCHAR(255) NOT NULL,
  `provider_payment_id` VARCHAR(255) NULL DEFAULT NULL,
  `provider_signature` VARCHAR(255) NULL DEFAULT NULL,
  `paid_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. shiprocket_orders
DROP TABLE IF EXISTS `shiprocket_orders`;
CREATE TABLE `shiprocket_orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL UNIQUE,
  `shiprocket_order_id` VARCHAR(100) NULL DEFAULT NULL,
  `shipment_id` VARCHAR(100) NULL DEFAULT NULL,
  `awb_code` VARCHAR(100) NULL DEFAULT NULL,
  `status` VARCHAR(100) DEFAULT 'pending',
  `request_payload` TEXT NULL,
  `response_payload` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. contact_messages
DROP TABLE IF EXISTS `contact_messages`;
CREATE TABLE `contact_messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(100) NOT NULL,
  `order_id` VARCHAR(100) NULL DEFAULT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `ip_address` VARCHAR(50) NULL DEFAULT NULL,
  `user_agent` VARCHAR(255) NULL DEFAULT NULL,
  `email_sent` TINYINT DEFAULT 0,
  `email_sent_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 17. product_reviews
DROP TABLE IF EXISTS `product_reviews`;
CREATE TABLE `product_reviews` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `store_user_id` INT NULL DEFAULT NULL,
  `rating` INT NOT NULL,
  `author_name` VARCHAR(120) NOT NULL,
  `body` TEXT NULL,
  `is_approved` TINYINT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`store_user_id`) REFERENCES `store_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 18. collections
DROP TABLE IF EXISTS `collections`;
CREATE TABLE `collections` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL UNIQUE,
  `thumbnail_url` VARCHAR(255) NULL DEFAULT NULL,
  `description` TEXT NULL,
  `meta_title` VARCHAR(255) NULL DEFAULT NULL,
  `meta_description` TEXT NULL DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 19. collection_products
DROP TABLE IF EXISTS `collection_products`;
CREATE TABLE `collection_products` (
  `collection_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `sort_order` INT DEFAULT 0,
  PRIMARY KEY (`collection_id`, `product_id`),
  FOREIGN KEY (`collection_id`) REFERENCES `collections` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 20. homepage_banners
DROP TABLE IF EXISTS `homepage_banners`;
CREATE TABLE `homepage_banners` (
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

SET FOREIGN_KEY_CHECKS = 1;
