-- Migration to ensure streams table has proper structure for stream selection
-- This migration ensures the streams table exists with the necessary columns

-- Create streams table if it doesn't exist
CREATE TABLE IF NOT EXISTS `streams` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `code` varchar(20) NOT NULL,
  `description` text,
  `active_days` json DEFAULT NULL,
  `period_start` time DEFAULT NULL,
  `period_end` time DEFAULT NULL,
  `break_start` time DEFAULT NULL,
  `break_end` time DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Insert default streams if they don't exist
INSERT IGNORE INTO `streams` (`id`, `name`, `code`, `description`, `is_active`) VALUES
(1, 'Regular', 'REG', 'Regular weekday classes', 1),
(2, 'Weekend', 'WKD', 'Weekend classes', 1),
(3, 'Evening', 'EVE', 'Evening classes', 1);

-- Ensure classes table has stream_id column
ALTER TABLE `classes` 
ADD COLUMN IF NOT EXISTS `stream_id` int NOT NULL DEFAULT 1,
ADD KEY IF NOT EXISTS `stream_id` (`stream_id`),
ADD CONSTRAINT IF NOT EXISTS `classes_ibfk_3` FOREIGN KEY (`stream_id`) REFERENCES `streams` (`id`) ON DELETE CASCADE;

-- Update existing classes to have stream_id = 1 if they don't have one
UPDATE `classes` SET `stream_id` = 1 WHERE `stream_id` IS NULL OR `stream_id` = 0;

-- Add index for better performance
CREATE INDEX IF NOT EXISTS `idx_classes_stream_id` ON `classes` (`stream_id`);
