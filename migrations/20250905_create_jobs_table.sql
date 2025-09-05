-- Migration: Create jobs table for async timetable generation
CREATE TABLE IF NOT EXISTS `jobs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `job_type` varchar(50) NOT NULL DEFAULT 'generate_timetable',
  `stream_id` int DEFAULT NULL,
  `academic_year` varchar(9) DEFAULT NULL,
  `semester` int DEFAULT NULL,
  `options` json DEFAULT NULL,
  `status` enum('queued','running','failed','completed','cancelled') NOT NULL DEFAULT 'queued',
  `progress` int NOT NULL DEFAULT 0,
  `result` json DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_stream` (`stream_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


