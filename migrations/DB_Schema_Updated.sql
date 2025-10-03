-- =====================================================
-- AAMUSTED TIMETABLE SYSTEM - UPDATED DATABASE SCHEMA
-- =====================================================
-- Version: 2.0
-- Date: 2025-09-04
-- Description: Complete, unified database schema for the timetable system
-- 
-- This schema addresses all inconsistencies and provides:
-- 1. Proper foreign key relationships
-- 2. Stream-based filtering support
-- 3. Timetable generation, editing, and saving functionality
-- 4. Performance optimization with proper indexes
-- 5. Data integrity constraints
-- =====================================================

-- Create database
CREATE DATABASE IF NOT EXISTS `timetable_system` 
CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;

USE `timetable_system`;

-- Set SQL mode and disable foreign key checks for initial setup
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- CORE ENTITY TABLES
-- =====================================================

-- Departments table
DROP TABLE IF EXISTS `departments`;
CREATE TABLE `departments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `description` text,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dept_code` (`code`),
  UNIQUE KEY `uq_dept_name` (`name`),
  KEY `idx_departments_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Streams table
DROP TABLE IF EXISTS `streams`;
CREATE TABLE `streams` (
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
  UNIQUE KEY `uq_stream_name` (`name`),
  UNIQUE KEY `uq_stream_code` (`code`),
  KEY `idx_streams_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Levels table
DROP TABLE IF EXISTS `levels`;
CREATE TABLE `levels` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `code` varchar(20) NOT NULL,
  `description` text,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_level_code` (`code`),
  UNIQUE KEY `uq_level_name` (`name`),
  KEY `idx_levels_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Programs table
DROP TABLE IF EXISTS `programs`;
CREATE TABLE `programs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `department_id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `description` text,
  `duration_years` int DEFAULT '4',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_program_code` (`code`),
  UNIQUE KEY `uq_program_name` (`name`),
  KEY `idx_programs_department` (`department_id`),
  KEY `idx_programs_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Buildings table
DROP TABLE IF EXISTS `buildings`;
CREATE TABLE `buildings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `description` text,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_building_code` (`code`),
  UNIQUE KEY `uq_building_name` (`name`),
  KEY `idx_buildings_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Room types table
DROP TABLE IF EXISTS `room_types`;
CREATE TABLE `room_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` text,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_room_type_name` (`name`),
  KEY `idx_room_types_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================================================
-- ACADEMIC ENTITY TABLES
-- =====================================================

-- Courses table
DROP TABLE IF EXISTS `courses`;
CREATE TABLE `courses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `name` varchar(200) NOT NULL,
  `department_id` int DEFAULT NULL,
  `credits` int DEFAULT NULL,
  `hours_per_week` int NOT NULL DEFAULT '3',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_course_code` (`code`),
  KEY `idx_courses_department` (`department_id`),
  KEY `idx_courses_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Lecturers table
DROP TABLE IF EXISTS `lecturers`;
CREATE TABLE `lecturers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `department_id` int NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_lecturers_department` (`department_id`),
  KEY `idx_lecturers_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Classes table
DROP TABLE IF EXISTS `classes`;
CREATE TABLE `classes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `program_id` int NOT NULL,
  `level_id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `stream_id` int NOT NULL,
  `total_capacity` int NOT NULL DEFAULT '0',
  `divisions_count` int NOT NULL DEFAULT '1',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_class_code` (`code`),
  KEY `idx_classes_program` (`program_id`),
  KEY `idx_classes_level` (`level_id`),
  KEY `idx_classes_stream` (`stream_id`),
  KEY `idx_classes_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================================================
-- RELATIONSHIP TABLES
-- =====================================================

-- Class courses relationship table
DROP TABLE IF EXISTS `class_courses`;
CREATE TABLE `class_courses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `class_id` int NOT NULL,
  `course_id` int NOT NULL,
  `lecturer_id` int DEFAULT NULL,
  `semester` enum('first','second','summer') NOT NULL DEFAULT 'first',
  `academic_year` varchar(9) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_class_course_semester_year` (`class_id`,`course_id`,`semester`,`academic_year`),
  KEY `idx_class_courses_class` (`class_id`),
  KEY `idx_class_courses_course` (`course_id`),
  KEY `idx_class_courses_lecturer` (`lecturer_id`),
  KEY `idx_class_courses_active` (`is_active`),
  KEY `idx_class_courses_semester` (`semester`),
  KEY `idx_class_courses_year` (`academic_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Lecturer courses relationship table
DROP TABLE IF EXISTS `lecturer_courses`;
CREATE TABLE `lecturer_courses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `lecturer_id` int NOT NULL,
  `course_id` int NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lecturer_course` (`lecturer_id`,`course_id`),
  KEY `idx_lecturer_courses_lecturer` (`lecturer_id`),
  KEY `idx_lecturer_courses_course` (`course_id`),
  KEY `idx_lecturer_courses_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Course room types relationship table
DROP TABLE IF EXISTS `course_room_types`;
CREATE TABLE `course_room_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `course_id` int NOT NULL,
  `room_type_id` int NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_course_room_type` (`course_id`,`room_type_id`),
  KEY `idx_course_room_types_course` (`course_id`),
  KEY `idx_course_room_types_type` (`room_type_id`),
  KEY `idx_course_room_types_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================================================
-- INFRASTRUCTURE TABLES
-- =====================================================

-- Rooms table
DROP TABLE IF EXISTS `rooms`;
CREATE TABLE `rooms` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `room_type` varchar(50) NOT NULL COMMENT 'Expected values: classroom, lecture_hall, laboratory, computer_lab, seminar_room, auditorium',
  `capacity` int NOT NULL,
  `building_id` int DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_room_name_building` (`name`),
  KEY `idx_rooms_building` (`building_id`),
  KEY `idx_rooms_type` (`room_type`),
  KEY `idx_rooms_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Days table
DROP TABLE IF EXISTS `days`;
CREATE TABLE `days` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(20) NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_day_name` (`name`),
  KEY `idx_days_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Time slots table
DROP TABLE IF EXISTS `time_slots`;
CREATE TABLE `time_slots` (
  `id` int NOT NULL AUTO_INCREMENT,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `duration` int NOT NULL,
  `is_break` tinyint(1) DEFAULT '0',
  `is_mandatory` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_times` (`start_time`,`end_time`),
  KEY `idx_time_slots_break` (`is_break`),
  KEY `idx_time_slots_mandatory` (`is_mandatory`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Stream time slots relationship table
DROP TABLE IF EXISTS `stream_time_slots`;
CREATE TABLE `stream_time_slots` (
  `id` int NOT NULL AUTO_INCREMENT,
  `stream_id` int NOT NULL,
  `time_slot_id` int NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_stream_time_slot` (`stream_id`,`time_slot_id`),
  KEY `idx_stream_time_slots_stream` (`stream_id`),
  KEY `idx_stream_time_slots_time_slot` (`time_slot_id`),
  KEY `idx_stream_time_slots_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================================================
-- TIMETABLE TABLES
-- =====================================================

-- Main timetable table (updated schema)
DROP TABLE IF EXISTS `timetable`;
CREATE TABLE `timetable` (
  `id` int NOT NULL AUTO_INCREMENT,
  `class_course_id` int NOT NULL,
  `lecturer_course_id` int NOT NULL,
  `day_id` int NOT NULL,
  `time_slot_id` int NOT NULL,
  `room_id` int NOT NULL,
  `division_label` varchar(10) DEFAULT NULL,
  `semester` enum('first','second','summer') NOT NULL DEFAULT 'first',
  `academic_year` varchar(9) DEFAULT NULL,
  `timetable_type` enum('lecture','exam') NOT NULL DEFAULT 'lecture',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_timetable_slot` (`room_id`,`day_id`,`time_slot_id`,`semester`,`academic_year`,`timetable_type`),
  UNIQUE KEY `uq_timetable_class_course_time` (`class_course_id`,`day_id`,`time_slot_id`,`division_label`),
  KEY `idx_timetable_class_course` (`class_course_id`),
  KEY `idx_timetable_lecturer_course` (`lecturer_course_id`),
  KEY `idx_timetable_day` (`day_id`),
  KEY `idx_timetable_time_slot` (`time_slot_id`),
  KEY `idx_timetable_room` (`room_id`),
  KEY `idx_timetable_semester` (`semester`),
  KEY `idx_timetable_academic_year` (`academic_year`),
  KEY `idx_timetable_type` (`timetable_type`),
  KEY `idx_timetable_active` (`is_active`),
  KEY `idx_timetable_stream_lookup` (`class_course_id`, `day_id`, `time_slot_id`),
  KEY `idx_timetable_lecturer_lookup` (`lecturer_course_id`, `day_id`, `time_slot_id`),
  KEY `idx_timetable_room_schedule` (`room_id`, `day_id`, `time_slot_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Saved timetables table
DROP TABLE IF EXISTS `saved_timetables`;
CREATE TABLE `saved_timetables` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `academic_year` varchar(50) NOT NULL,
  `semester` varchar(20) NOT NULL,
  `type` varchar(20) NOT NULL DEFAULT 'lecture',
  `stream_id` int DEFAULT NULL,
  `timetable_data` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_saved_timetables_stream` (`stream_id`),
  KEY `idx_saved_timetables_academic_year` (`academic_year`),
  KEY `idx_saved_timetables_semester` (`semester`),
  KEY `idx_saved_timetables_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Timetable lecturers relationship table (for multiple lecturers per slot)
DROP TABLE IF EXISTS `timetable_lecturers`;
CREATE TABLE `timetable_lecturers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `timetable_id` int NOT NULL,
  `lecturer_id` int NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_timetable_lecturer` (`timetable_id`,`lecturer_id`),
  KEY `idx_timetable_lecturers_timetable` (`timetable_id`),
  KEY `idx_timetable_lecturers_lecturer` (`lecturer_id`),
  KEY `idx_timetable_lecturers_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================================================
-- FOREIGN KEY CONSTRAINTS
-- =====================================================

-- Enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Programs foreign keys
ALTER TABLE `programs` 
ADD CONSTRAINT `fk_programs_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE;

-- Courses foreign keys
ALTER TABLE `courses` 
ADD CONSTRAINT `fk_courses_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL;

-- Lecturers foreign keys
ALTER TABLE `lecturers` 
ADD CONSTRAINT `fk_lecturers_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE;

-- Classes foreign keys
ALTER TABLE `classes` 
ADD CONSTRAINT `fk_classes_program` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE CASCADE,
ADD CONSTRAINT `fk_classes_level` FOREIGN KEY (`level_id`) REFERENCES `levels` (`id`) ON DELETE CASCADE,
ADD CONSTRAINT `fk_classes_stream` FOREIGN KEY (`stream_id`) REFERENCES `streams` (`id`) ON DELETE CASCADE;

-- Rooms foreign keys
ALTER TABLE `rooms` 
ADD CONSTRAINT `fk_rooms_building` FOREIGN KEY (`building_id`) REFERENCES `buildings` (`id`) ON DELETE SET NULL;

-- Class courses foreign keys
ALTER TABLE `class_courses` 
ADD CONSTRAINT `fk_class_courses_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
ADD CONSTRAINT `fk_class_courses_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
ADD CONSTRAINT `fk_class_courses_lecturer` FOREIGN KEY (`lecturer_id`) REFERENCES `lecturers` (`id`) ON DELETE SET NULL;

-- Lecturer courses foreign keys
ALTER TABLE `lecturer_courses` 
ADD CONSTRAINT `fk_lecturer_courses_lecturer` FOREIGN KEY (`lecturer_id`) REFERENCES `lecturers` (`id`) ON DELETE CASCADE,
ADD CONSTRAINT `fk_lecturer_courses_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

-- Course room types foreign keys
ALTER TABLE `course_room_types` 
ADD CONSTRAINT `fk_course_room_types_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
ADD CONSTRAINT `fk_course_room_types_type` FOREIGN KEY (`room_type_id`) REFERENCES `room_types` (`id`) ON DELETE CASCADE;

-- Stream time slots foreign keys
ALTER TABLE `stream_time_slots` 
ADD CONSTRAINT `fk_stream_time_slots_stream` FOREIGN KEY (`stream_id`) REFERENCES `streams` (`id`) ON DELETE CASCADE,
ADD CONSTRAINT `fk_stream_time_slots_time_slot` FOREIGN KEY (`time_slot_id`) REFERENCES `time_slots` (`id`) ON DELETE CASCADE;

-- Timetable foreign keys
ALTER TABLE `timetable` 
ADD CONSTRAINT `fk_timetable_class_course` FOREIGN KEY (`class_course_id`) REFERENCES `class_courses` (`id`) ON DELETE CASCADE,
ADD CONSTRAINT `fk_timetable_lecturer_course` FOREIGN KEY (`lecturer_course_id`) REFERENCES `lecturer_courses` (`id`) ON DELETE CASCADE,
ADD CONSTRAINT `fk_timetable_day` FOREIGN KEY (`day_id`) REFERENCES `days` (`id`) ON DELETE CASCADE,
ADD CONSTRAINT `fk_timetable_time_slot` FOREIGN KEY (`time_slot_id`) REFERENCES `time_slots` (`id`) ON DELETE CASCADE,
ADD CONSTRAINT `fk_timetable_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE;

-- Saved timetables foreign keys
ALTER TABLE `saved_timetables` 
ADD CONSTRAINT `fk_saved_timetables_stream` FOREIGN KEY (`stream_id`) REFERENCES `streams` (`id`) ON DELETE SET NULL;

-- Timetable lecturers foreign keys
ALTER TABLE `timetable_lecturers` 
ADD CONSTRAINT `fk_timetable_lecturers_timetable` FOREIGN KEY (`timetable_id`) REFERENCES `timetable` (`id`) ON DELETE CASCADE,
ADD CONSTRAINT `fk_timetable_lecturers_lecturer` FOREIGN KEY (`lecturer_id`) REFERENCES `lecturers` (`id`) ON DELETE CASCADE;

-- =====================================================
-- VIEWS FOR EASIER QUERYING
-- =====================================================

-- Comprehensive timetable view
CREATE OR REPLACE VIEW `timetable_view` AS
SELECT 
    t.id,
    t.class_course_id,
    t.lecturer_course_id,
    t.day_id,
    t.time_slot_id,
    t.room_id,
    t.division_label,
    t.semester,
    t.academic_year,
    t.timetable_type,
    t.is_active,
    t.created_at,
    t.updated_at,
    -- Class information
    c.name as class_name,
    c.code as class_code,
    c.stream_id,
    s.name as stream_name,
    -- Course information
    co.code as course_code,
    co.name as course_name,
    co.credits,
    co.hours_per_week,
    -- Lecturer information
    l.name as lecturer_name,
    l.department_id as lecturer_department_id,
    -- Room information
    r.name as room_name,
    r.capacity as room_capacity,
    r.room_type,
    -- Day and time information
    d.name as day_name,
    ts.start_time,
    ts.end_time,
    ts.duration
FROM timetable t
JOIN class_courses cc ON t.class_course_id = cc.id
JOIN classes c ON cc.class_id = c.id
JOIN courses co ON cc.course_id = co.id
JOIN lecturer_courses lc ON t.lecturer_course_id = lc.id
JOIN lecturers l ON lc.lecturer_id = l.id
JOIN rooms r ON t.room_id = r.id
JOIN days d ON t.day_id = d.id
JOIN time_slots ts ON t.time_slot_id = ts.id
JOIN streams s ON c.stream_id = s.id
WHERE t.is_active = 1;

-- Stream-based timetable view
CREATE OR REPLACE VIEW `stream_timetable_view` AS
SELECT 
    t.*
FROM timetable_view t
JOIN streams s ON t.stream_id = s.id
WHERE s.is_active = 1;

-- =====================================================
-- TRIGGERS FOR DATA VALIDATION
-- =====================================================

DELIMITER //

-- Validate academic year format
CREATE TRIGGER `validate_academic_year_insert` 
BEFORE INSERT ON `timetable`
FOR EACH ROW
BEGIN
    IF NEW.academic_year IS NOT NULL AND NEW.academic_year NOT REGEXP '^[0-9]{4}/[0-9]{4}$' THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Academic year must be in format YYYY/YYYY';
    END IF;
END//

CREATE TRIGGER `validate_academic_year_update` 
BEFORE UPDATE ON `timetable`
FOR EACH ROW
BEGIN
    IF NEW.academic_year IS NOT NULL AND NEW.academic_year NOT REGEXP '^[0-9]{4}/[0-9]{4}$' THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Academic year must be in format YYYY/YYYY';
    END IF;
END//

-- Validate time slot duration
CREATE TRIGGER `validate_time_slot_duration` 
BEFORE INSERT ON `time_slots`
FOR EACH ROW
BEGIN
    IF NEW.duration != TIMESTAMPDIFF(MINUTE, NEW.start_time, NEW.end_time) THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Duration must match the difference between start_time and end_time';
    END IF;
END//

CREATE TRIGGER `validate_time_slot_duration_update` 
BEFORE UPDATE ON `time_slots`
FOR EACH ROW
BEGIN
    IF NEW.duration != TIMESTAMPDIFF(MINUTE, NEW.start_time, NEW.end_time) THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Duration must match the difference between start_time and end_time';
    END IF;
END//

DELIMITER ;

-- =====================================================
-- SAMPLE DATA INSERTION
-- =====================================================

-- Insert default stream
INSERT INTO `streams` (`name`, `code`, `description`, `is_active`) VALUES 
('Default Stream', 'DEFAULT', 'Default stream for the system', 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Insert default days
INSERT INTO `days` (`name`, `is_active`) VALUES 
('Monday', 1),
('Tuesday', 1),
('Wednesday', 1),
('Thursday', 1),
('Friday', 1),
('Saturday', 1),
('Sunday', 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Insert default levels
INSERT INTO `levels` (`name`, `code`, `description`, `is_active`) VALUES 
('Level 100', 'L100', 'First year level', 1),
('Level 200', 'L200', 'Second year level', 1),
('Level 300', 'L300', 'Third year level', 1),
('Level 400', 'L400', 'Fourth year level', 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Insert default room types
INSERT INTO `room_types` (`name`, `description`, `is_active`) VALUES 
('classroom', 'Standard classroom', 1),
('lecture_hall', 'Large lecture hall', 1),
('laboratory', 'Science laboratory', 1),
('computer_lab', 'Computer laboratory', 1),
('seminar_room', 'Small seminar room', 1),
('auditorium', 'Large auditorium', 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- =====================================================
-- SCHEMA COMPLETION
-- =====================================================

-- Reset SQL mode
SET SQL_MODE = "";

-- Final message
SELECT 'AAMUSTED Timetable System Database Schema Created Successfully!' as status;
