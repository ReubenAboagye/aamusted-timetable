-- Migration: create stream_time_slots mapping table
-- Adds a mapping between streams and time_slots so generation can use stream-specific slots
-- Run this SQL in your database (e.g., via phpMyAdmin or mysql CLI)

CREATE TABLE IF NOT EXISTS `stream_time_slots` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stream_id` int(11) NOT NULL,
  `time_slot_id` int(11) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_stream_id` (`stream_id`),
  KEY `idx_time_slot_id` (`time_slot_id`),
  CONSTRAINT `fk_stream_time_slots_stream` FOREIGN KEY (`stream_id`) REFERENCES `streams` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_stream_time_slots_time_slot` FOREIGN KEY (`time_slot_id`) REFERENCES `time_slots` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional: insert default mappings for existing streams to use all time slots
-- INSERT INTO stream_time_slots (stream_id, time_slot_id, is_active)
-- SELECT s.id, ts.id, 1 FROM streams s JOIN time_slots ts ON ts.is_break = 0;


