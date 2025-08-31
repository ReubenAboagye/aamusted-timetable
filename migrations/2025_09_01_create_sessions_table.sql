-- Migration: create sessions table for saved timetables
-- Run with: mysql -u root -p timetable_system < migrations/2025_09_01_create_sessions_table.sql

CREATE TABLE IF NOT EXISTS `sessions` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `semester_name` VARCHAR(255) NOT NULL,
  `academic_year` VARCHAR(50) NOT NULL,
  `semester_number` TINYINT NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Example seed
INSERT INTO `sessions` (`semester_name`, `academic_year`, `semester_number`, `is_active`) VALUES
('Regular Semester', '2024/2025', 1, 1);



