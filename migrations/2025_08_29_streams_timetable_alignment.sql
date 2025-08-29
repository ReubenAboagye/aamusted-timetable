-- Migration: Stream settings, seed streams, align timetable schema to code expectations
-- Date: 2025-08-29
-- Notes:
-- - Creates stream_settings table (per-stream working hours/days)
-- - Seeds four streams (Regular, Evening, Sandwich, Masters)
-- - Replaces timetable table with code-aligned schema using class_course_id and lecturer_course_id

START TRANSACTION;

-- Ensure streams table exists (expected by base dump); otherwise create minimal
CREATE TABLE IF NOT EXISTS `streams` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `code` varchar(20) NOT NULL,
  `description` text,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_stream_name` (`name`),
  UNIQUE KEY `uq_stream_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Stream settings table (one row per stream)
CREATE TABLE IF NOT EXISTS `stream_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `stream_id` int NOT NULL,
  `period_start` time DEFAULT NULL,
  `period_end` time DEFAULT NULL,
  `break_start` time DEFAULT NULL,
  `break_end` time DEFAULT NULL,
  `active_days` varchar(100) DEFAULT NULL, -- Comma-separated day names, e.g., Monday,Tuesday,Wednesday
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_stream_settings_stream` (`stream_id`),
  CONSTRAINT `fk_stream_settings_stream` FOREIGN KEY (`stream_id`) REFERENCES `streams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Seed streams (idempotent by code)
INSERT INTO `streams` (`name`, `code`, `description`, `is_active`)
VALUES
  ('Regular', 'REG', 'Regular day stream', 1),
  ('Evening', 'EVE', 'Evening stream', 1),
  ('Sandwich', 'SAN', 'Sandwich stream', 1),
  ('Masters', 'MST', 'Masters stream', 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`), `is_active` = VALUES(`is_active`);

-- Create default settings rows for any streams missing settings
INSERT INTO `stream_settings` (`stream_id`, `period_start`, `period_end`, `break_start`, `break_end`, `active_days`)
SELECT s.id, '08:00', '18:00', '12:00', '13:00', 'Monday,Tuesday,Wednesday,Thursday,Friday'
FROM `streams` s
LEFT JOIN `stream_settings` ss ON ss.stream_id = s.id
WHERE ss.id IS NULL AND s.is_active = 1;

-- Align timetable schema to application code (drop and recreate)
DROP TABLE IF EXISTS `timetable`;
CREATE TABLE `timetable` (
  `id` int NOT NULL AUTO_INCREMENT,
  `day_id` int NOT NULL,
  `time_slot_id` int NOT NULL,
  `room_id` int NOT NULL,
  `class_course_id` int NOT NULL,
  `lecturer_course_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_room_slot` (`room_id`,`day_id`,`time_slot_id`),
  UNIQUE KEY `uq_class_slot` (`class_course_id`,`day_id`,`time_slot_id`),
  KEY `idx_day` (`day_id`),
  KEY `idx_time_slot` (`time_slot_id`),
  KEY `idx_room` (`room_id`),
  KEY `idx_class_course` (`class_course_id`),
  KEY `idx_lecturer_course` (`lecturer_course_id`),
  CONSTRAINT `fk_timetable_day` FOREIGN KEY (`day_id`) REFERENCES `days` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_timetable_time_slot` FOREIGN KEY (`time_slot_id`) REFERENCES `time_slots` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_timetable_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_timetable_class_course` FOREIGN KEY (`class_course_id`) REFERENCES `class_courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_timetable_lecturer_course` FOREIGN KEY (`lecturer_course_id`) REFERENCES `lecturer_courses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

COMMIT;

