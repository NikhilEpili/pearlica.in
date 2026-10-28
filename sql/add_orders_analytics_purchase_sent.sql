-- Run once: prevents duplicate Purchase analytics for the same order.
ALTER TABLE `orders`
  ADD COLUMN `analytics_purchase_sent_at` DATETIME NULL DEFAULT NULL AFTER `paid_at`;
