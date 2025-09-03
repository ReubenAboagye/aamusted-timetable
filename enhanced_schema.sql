-- ENHANCED TIMETABLE SYSTEM SCHEMA
-- Based on your existing DB Schema.sql with professional improvements
-- Only CLASSES are stream-specific, everything else is GLOBAL

CREATE DATABASE IF NOT EXISTS `timetable_system` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `timetable_system`;

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

-- ============================================================================
-- GLOBAL TABLES (Shared across all streams)
-- ============================================================================

--
-- Enhanced table structure for table `buildings`
--

DROP TABLE IF EXISTS `buildings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `buildings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `description` text,
  `address` text DEFAULT NULL,
  `floors_count` int DEFAULT 1,
  `accessibility_features` json DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_building_code` (`code`),
  UNIQUE KEY `uq_building_name` (`name`),
  INDEX `idx_building_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Enhanced table structure for table `departments` (GLOBAL)
--

DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `departments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `short_name` varchar(10) DEFAULT NULL,
  `description` text,
  `head_of_department` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dept_code` (`code`),
  UNIQUE KEY `uq_dept_name` (`name`),
  INDEX `idx_dept_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Enhanced table structure for table `levels` (GLOBAL)
--

DROP TABLE IF EXISTS `levels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `levels` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `code` varchar(20) NOT NULL,
  `numeric_value` int NOT NULL,
  `description` text,
  `min_credits` int DEFAULT 15,
  `max_credits` int DEFAULT 25,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_level_code` (`code`),
  UNIQUE KEY `uq_level_name` (`name`),
  UNIQUE KEY `uq_level_numeric` (`numeric_value`),
  INDEX `idx_level_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

-- Insert standard levels
INSERT INTO `levels` (`name`, `code`, `numeric_value`, `description`) VALUES
('Level 100', 'L100', 100, 'First Year'),
('Level 200', 'L200', 200, 'Second Year'),
('Level 300', 'L300', 300, 'Third Year'),
('Level 400', 'L400', 400, 'Fourth Year');

--
-- Enhanced table structure for table `programs` (GLOBAL - removed stream_id)
--

DROP TABLE IF EXISTS `programs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `programs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `department_id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `description` text,
  `duration_years` int DEFAULT '4',
  `degree_type` enum('certificate','diploma','bachelor','master','phd') DEFAULT 'bachelor',
  `entry_requirements` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_program_code` (`code`),
  UNIQUE KEY `uq_program_name` (`name`),
  KEY `department_id` (`department_id`),
  INDEX `idx_program_active` (`is_active`),
  CONSTRAINT `programs_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=72 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Enhanced table structure for table `courses` (GLOBAL - no stream_id)
--

DROP TABLE IF EXISTS `courses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `courses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `name` varchar(200) NOT NULL,
  `department_id` int NOT NULL,
  `level_id` int NOT NULL,
  `credits` int NOT NULL DEFAULT 3,
  `hours_per_week` int NOT NULL DEFAULT 3,
  `course_type` enum('core','elective','practical','project','seminar') DEFAULT 'core',
  `prerequisites` json DEFAULT NULL,
  `corequisites` json DEFAULT NULL,
  `preferred_room_type` varchar(50) DEFAULT 'classroom',
  `max_class_size` int DEFAULT 50,
  `description` text,
  `learning_outcomes` text DEFAULT NULL,
  `assessment_methods` json DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_course_code` (`code`),
  KEY `idx_courses_department_id` (`department_id`),
  KEY `idx_courses_level_id` (`level_id`),
  KEY `idx_courses_type` (`course_type`),
  INDEX `idx_course_active` (`is_active`),
  CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `courses_ibfk_2` FOREIGN KEY (`level_id`) REFERENCES `levels` (`id`) ON DELETE CASCADE,
  CHECK (`credits` > 0),
  CHECK (`hours_per_week` > 0),
  CHECK (`max_class_size` > 0)
) ENGINE=InnoDB AUTO_INCREMENT=121 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Enhanced table structure for table `lecturers` (GLOBAL - no stream_id)
--

DROP TABLE IF EXISTS `lecturers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lecturers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `staff_id` varchar(20) UNIQUE DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `title` varchar(20) DEFAULT NULL,
  `email` varchar(100) UNIQUE DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `department_id` int NOT NULL,
  `rank` enum('professor','associate_professor','assistant_professor','lecturer','assistant_lecturer','teaching_assistant') DEFAULT 'lecturer',
  `specialization` text DEFAULT NULL,
  `qualifications` json DEFAULT NULL,
  `max_hours_per_week` int DEFAULT 20,
  `max_classes_per_day` int DEFAULT 4,
  `preferred_time_slots` json DEFAULT NULL,
  `unavailable_days` json DEFAULT NULL,
  `office_location` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `department_id` (`department_id`),
  INDEX `idx_lecturer_active` (`is_active`),
  INDEX `idx_lecturer_rank` (`rank`),
  CONSTRAINT `lecturers_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  CHECK (`max_hours_per_week` > 0),
  CHECK (`max_classes_per_day` > 0)
) ENGINE=InnoDB AUTO_INCREMENT=88 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Enhanced table structure for table `room_types` (GLOBAL)
--

DROP TABLE IF EXISTS `room_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `room_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` text,
  `equipment_required` json DEFAULT NULL,
  `setup_time_minutes` int DEFAULT 0,
  `cleanup_time_minutes` int DEFAULT 0,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_room_type_name` (`name`),
  INDEX `idx_room_type_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

-- Insert standard room types
INSERT INTO `room_types` (`name`, `description`, `equipment_required`) VALUES
('classroom', 'Standard classroom', '["whiteboard", "projector", "chairs"]'),
('lecture_hall', 'Large lecture hall', '["microphone", "projector", "tiered_seating"]'),
('laboratory', 'Science laboratory', '["lab_equipment", "safety_features", "ventilation"]'),
('computer_lab', 'Computer laboratory', '["computers", "network", "software"]'),
('seminar_room', 'Small seminar room', '["round_table", "whiteboard", "projector"]'),
('auditorium', 'Large auditorium', '["stage", "sound_system", "lighting"]');

--
-- Enhanced table structure for table `rooms` (GLOBAL - no stream_id)
--

DROP TABLE IF EXISTS `rooms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rooms` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `code` varchar(20) UNIQUE DEFAULT NULL,
  `building_id` int NOT NULL,
  `floor_number` int DEFAULT NULL,
  `room_number` varchar(20) DEFAULT NULL,
  `room_type` varchar(50) NOT NULL DEFAULT 'classroom',
  `capacity` int NOT NULL,
  `equipment` json DEFAULT NULL,
  `accessibility_features` json DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_room_name_building` (`name`, `building_id`),
  KEY `fk_rooms_building_id` (`building_id`),
  INDEX `idx_room_type_capacity` (`room_type`, `capacity`),
  INDEX `idx_room_active` (`is_active`),
  CONSTRAINT `fk_rooms_building_id` FOREIGN KEY (`building_id`) REFERENCES `buildings` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CHECK (`capacity` > 0)
) ENGINE=InnoDB AUTO_INCREMENT=68 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Enhanced table structure for table `days` (GLOBAL)
--

DROP TABLE IF EXISTS `days`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `days` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(20) NOT NULL,
  `short_name` varchar(3) NOT NULL,
  `sort_order` int NOT NULL,
  `is_weekend` tinyint(1) DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_day_name` (`name`),
  UNIQUE KEY `uq_day_short` (`short_name`),
  UNIQUE KEY `uq_day_order` (`sort_order`),
  INDEX `idx_day_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

-- Insert standard days
INSERT INTO `days` (`name`, `short_name`, `sort_order`, `is_weekend`) VALUES
('Monday', 'Mon', 1, 0),
('Tuesday', 'Tue', 2, 0),
('Wednesday', 'Wed', 3, 0),
('Thursday', 'Thu', 4, 0),
('Friday', 'Fri', 5, 0),
('Saturday', 'Sat', 6, 1),
('Sunday', 'Sun', 7, 1);

--
-- Enhanced table structure for table `time_slots` (GLOBAL)
--

DROP TABLE IF EXISTS `time_slots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `time_slots` (
  `id` int NOT NULL AUTO_INCREMENT,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `duration` int NOT NULL,
  `slot_name` varchar(50) DEFAULT NULL,
  `is_break` tinyint(1) DEFAULT '0',
  `is_mandatory` tinyint(1) DEFAULT '0',
  `sort_order` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_times` (`start_time`,`end_time`),
  INDEX `idx_timeslot_duration` (`duration`),
  INDEX `idx_timeslot_break` (`is_break`),
  CHECK (`duration` > 0),
  CHECK (`start_time` < `end_time`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

-- Insert comprehensive time slots
INSERT INTO `time_slots` (`start_time`, `end_time`, `duration`, `slot_name`, `is_break`, `sort_order`) VALUES
('07:00:00', '08:00:00', 60, 'Period 1', 0, 1),
('08:00:00', '09:00:00', 60, 'Period 2', 0, 2),
('09:00:00', '10:00:00', 60, 'Period 3', 0, 3),
('10:00:00', '11:00:00', 60, 'Period 4', 0, 4),
('11:00:00', '12:00:00', 60, 'Period 5', 0, 5),
('12:00:00', '13:00:00', 60, 'Lunch Break', 1, 6),
('13:00:00', '14:00:00', 60, 'Period 6', 0, 7),
('14:00:00', '15:00:00', 60, 'Period 7', 0, 8),
('15:00:00', '16:00:00', 60, 'Period 8', 0, 9),
('16:00:00', '17:00:00', 60, 'Period 9', 0, 10),
('17:00:00', '18:00:00', 60, 'Period 10', 0, 11),
('18:00:00', '19:00:00', 60, 'Evening Period 1', 0, 12),
('19:00:00', '20:00:00', 60, 'Evening Period 2', 0, 13);

-- ============================================================================
-- STREAM-SPECIFIC TABLES
-- ============================================================================

--
-- Enhanced table structure for table `streams`
--

DROP TABLE IF EXISTS `streams`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `streams` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `code` varchar(20) NOT NULL,
  `description` text,
  `active_days` json DEFAULT NULL,
  `period_start` time NOT NULL DEFAULT '08:00:00',
  `period_end` time NOT NULL DEFAULT '17:00:00',
  `break_start` time DEFAULT '12:00:00',
  `break_end` time DEFAULT '13:00:00',
  `max_daily_hours` int DEFAULT 8,
  `max_weekly_hours` int DEFAULT 40,
  `color_code` varchar(7) DEFAULT '#007bff',
  `sort_order` int DEFAULT 1,
  `is_active` tinyint(1) DEFAULT '1',
  `is_current` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `code` (`code`),
  INDEX `idx_stream_active` (`is_active`),
  INDEX `idx_stream_current` (`is_current`),
  CHECK (`period_start` < `period_end`),
  CHECK (`max_daily_hours` > 0),
  CHECK (`max_weekly_hours` > 0)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

-- Insert enhanced stream data
INSERT INTO `streams` (`name`, `code`, `description`, `period_start`, `period_end`, `break_start`, `break_end`, `active_days`, `color_code`, `sort_order`, `is_current`) VALUES
('Regular', 'REG', 'Regular weekday classes for full-time students', '08:00:00', '17:00:00', '12:00:00', '13:00:00', '["monday", "tuesday", "wednesday", "thursday", "friday"]', '#007bff', 1, 1),
('Weekend', 'WKD', 'Weekend classes for working professionals', '09:00:00', '17:00:00', '12:00:00', '13:00:00', '["saturday", "sunday"]', '#28a745', 2, 0),
('Evening', 'EVE', 'Evening classes for working students', '18:00:00', '22:00:00', '20:00:00', '20:15:00', '["monday", "tuesday", "wednesday", "thursday", "friday"]', '#ffc107', 3, 0);

--
-- Enhanced table structure for table `stream_time_slots`
--

DROP TABLE IF EXISTS `stream_time_slots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stream_time_slots` (
  `id` int NOT NULL AUTO_INCREMENT,
  `stream_id` int NOT NULL,
  `time_slot_id` int NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `priority` int DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_stream_time_slot` (`stream_id`, `time_slot_id`),
  KEY `idx_stream_id` (`stream_id`),
  KEY `idx_time_slot_id` (`time_slot_id`),
  INDEX `idx_stream_slots_active` (`stream_id`, `is_active`),
  CONSTRAINT `fk_stream_time_slots_stream` FOREIGN KEY (`stream_id`) REFERENCES `streams` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_stream_time_slots_time_slot` FOREIGN KEY (`time_slot_id`) REFERENCES `time_slots` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=128 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

-- Create stream_days mapping table
CREATE TABLE IF NOT EXISTS `stream_days` (
  `id` int NOT NULL AUTO_INCREMENT,
  `stream_id` int NOT NULL,
  `day_id` int NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_stream_day` (`stream_id`, `day_id`),
  KEY `idx_stream_days_active` (`stream_id`, `is_active`),
  CONSTRAINT `fk_stream_days_stream` FOREIGN KEY (`stream_id`) REFERENCES `streams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_stream_days_day` FOREIGN KEY (`day_id`) REFERENCES `days` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Enhanced table structure for table `classes` (ONLY stream-specific table)
--

DROP TABLE IF EXISTS `classes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `classes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `program_id` int NOT NULL,
  `level_id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `stream_id` int NOT NULL,
  `academic_year` varchar(9) NOT NULL DEFAULT '2024/2025',
  `semester` enum('first','second','summer') NOT NULL DEFAULT 'first',
  `total_capacity` int NOT NULL DEFAULT '30',
  `current_enrollment` int DEFAULT '0',
  `divisions_count` int NOT NULL DEFAULT '1',
  `division_capacity` int DEFAULT NULL,
  `class_coordinator` varchar(100) DEFAULT NULL,
  `preferred_start_time` time DEFAULT '08:00:00',
  `preferred_end_time` time DEFAULT '17:00:00',
  `max_daily_courses` int DEFAULT 4,
  `max_weekly_hours` int DEFAULT 25,
  `special_requirements` json DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_class_code_stream_year_semester` (`code`, `stream_id`, `academic_year`, `semester`),
  KEY `program_id` (`program_id`),
  KEY `level_id` (`level_id`),
  KEY `stream_id` (`stream_id`),
  INDEX `idx_class_stream_active` (`stream_id`, `is_active`),
  INDEX `idx_class_academic` (`academic_year`, `semester`),
  INDEX `idx_class_enrollment` (`current_enrollment`, `total_capacity`),
  CONSTRAINT `classes_ibfk_1` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `classes_ibfk_2` FOREIGN KEY (`level_id`) REFERENCES `levels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `classes_ibfk_3` FOREIGN KEY (`stream_id`) REFERENCES `streams` (`id`) ON DELETE CASCADE,
  CHECK (`total_capacity` > 0),
  CHECK (`current_enrollment` >= 0),
  CHECK (`current_enrollment` <= `total_capacity`),
  CHECK (`divisions_count` > 0),
  CHECK (`max_daily_courses` > 0),
  CHECK (`max_weekly_hours` > 0)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

-- ============================================================================
-- RELATIONSHIP TABLES
-- ============================================================================

--
-- Enhanced table structure for table `lecturer_courses` (GLOBAL)
--

DROP TABLE IF EXISTS `lecturer_courses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lecturer_courses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `lecturer_id` int NOT NULL,
  `course_id` int NOT NULL,
  `is_primary` tinyint(1) DEFAULT '0',
  `competency_level` enum('basic','intermediate','advanced','expert') DEFAULT 'intermediate',
  `max_classes_per_week` int DEFAULT 5,
  `preferred_time_slots` json DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lecturer_course` (`lecturer_id`,`course_id`),
  KEY `course_id` (`course_id`),
  INDEX `idx_lc_active` (`is_active`),
  INDEX `idx_lc_primary` (`is_primary`, `is_active`),
  CONSTRAINT `lecturer_courses_ibfk_1` FOREIGN KEY (`lecturer_id`) REFERENCES `lecturers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lecturer_courses_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CHECK (`max_classes_per_week` > 0)
) ENGINE=InnoDB AUTO_INCREMENT=93 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Enhanced table structure for table `course_room_types`
--

DROP TABLE IF EXISTS `course_room_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `course_room_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `course_id` int NOT NULL,
  `room_type_id` int NOT NULL,
  `priority` int DEFAULT 1,
  `is_required` tinyint(1) DEFAULT '0',
  `setup_requirements` json DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_course_room_type` (`course_id`,`room_type_id`),
  KEY `room_type_id` (`room_type_id`),
  INDEX `idx_crt_priority` (`course_id`, `priority`),
  CONSTRAINT `course_room_types_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `course_room_types_ibfk_2` FOREIGN KEY (`room_type_id`) REFERENCES `room_types` (`id`) ON DELETE CASCADE,
  CHECK (`priority` > 0)
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Enhanced table structure for table `class_courses` with professional validation
--

DROP TABLE IF EXISTS `class_courses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `class_courses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `class_id` int NOT NULL,
  `course_id` int NOT NULL,
  `lecturer_id` int DEFAULT NULL,
  `semester` enum('first','second','summer') NOT NULL DEFAULT 'first',
  `academic_year` varchar(9) NOT NULL DEFAULT '2024/2025',
  `assignment_type` enum('automatic','manual','recommended') DEFAULT 'manual',
  `assigned_by` varchar(100) DEFAULT NULL,
  `assignment_reason` text DEFAULT NULL,
  `approval_status` enum('pending','approved','rejected') DEFAULT 'approved',
  `approved_by` varchar(100) DEFAULT NULL,
  `approval_date` timestamp NULL DEFAULT NULL,
  `quality_score` int DEFAULT NULL,
  `validation_notes` json DEFAULT NULL,
  `is_mandatory` tinyint(1) DEFAULT '1',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_class_course_semester_year` (`class_id`,`course_id`,`semester`,`academic_year`),
  KEY `course_id` (`course_id`),
  KEY `lecturer_id` (`lecturer_id`),
  INDEX `idx_cc_class_active` (`class_id`, `is_active`),
  INDEX `idx_cc_course_active` (`course_id`, `is_active`),
  INDEX `idx_cc_academic` (`academic_year`, `semester`),
  INDEX `idx_cc_approval` (`approval_status`),
  INDEX `idx_cc_quality` (`quality_score`),
  CONSTRAINT `class_courses_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `class_courses_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `class_courses_ibfk_3` FOREIGN KEY (`lecturer_id`) REFERENCES `lecturers` (`id`) ON DELETE SET NULL,
  CHECK (`quality_score` IS NULL OR (`quality_score` >= 0 AND `quality_score` <= 100))
) ENGINE=InnoDB AUTO_INCREMENT=74 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Enhanced table structure for table `timetable`
--

DROP TABLE IF EXISTS `timetable`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `timetable` (
  `id` int NOT NULL AUTO_INCREMENT,
  `class_id` int NOT NULL,
  `course_id` int NOT NULL,
  `lecturer_id` int NOT NULL,
  `room_id` int NOT NULL,
  `day_id` int NOT NULL,
  `time_slot_id` int NOT NULL,
  `semester` enum('first','second','summer') NOT NULL,
  `academic_year` varchar(9) NOT NULL,
  `division_label` varchar(10) DEFAULT NULL,
  `timetable_type` enum('lecture','exam','practical','seminar') NOT NULL DEFAULT 'lecture',
  `session_duration` int DEFAULT NULL,
  `attendance_required` tinyint(1) DEFAULT '1',
  `notes` text DEFAULT NULL,
  `created_by` varchar(100) DEFAULT NULL,
  `approved_by` varchar(100) DEFAULT NULL,
  `approval_date` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_timetable_slot` (`room_id`,`day_id`,`time_slot_id`,`semester`,`academic_year`,`timetable_type`),
  UNIQUE KEY `uq_lecturer_time` (`lecturer_id`,`day_id`,`time_slot_id`,`semester`,`academic_year`),
  UNIQUE KEY `uq_class_time_division` (`class_id`,`day_id`,`time_slot_id`,`division_label`,`semester`,`academic_year`),
  KEY `class_id` (`class_id`),
  KEY `course_id` (`course_id`),
  KEY `lecturer_id` (`lecturer_id`),
  KEY `day_id` (`day_id`),
  KEY `time_slot_id` (`time_slot_id`),
  INDEX `idx_timetable_academic` (`academic_year`, `semester`),
  INDEX `idx_timetable_type` (`timetable_type`),
  CONSTRAINT `timetable_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `timetable_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `timetable_ibfk_3` FOREIGN KEY (`lecturer_id`) REFERENCES `lecturers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `timetable_ibfk_4` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `timetable_ibfk_5` FOREIGN KEY (`day_id`) REFERENCES `days` (`id`) ON DELETE CASCADE,
  CONSTRAINT `timetable_ibfk_6` FOREIGN KEY (`time_slot_id`) REFERENCES `time_slots` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `timetable_lecturers` (for multiple lecturers per session)
--

DROP TABLE IF EXISTS `timetable_lecturers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `timetable_lecturers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `timetable_id` int NOT NULL,
  `lecturer_id` int NOT NULL,
  `role` enum('primary','secondary','assistant') DEFAULT 'primary',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_timetable_lecturer` (`timetable_id`,`lecturer_id`),
  KEY `lecturer_id` (`lecturer_id`),
  CONSTRAINT `timetable_lecturers_ibfk_1` FOREIGN KEY (`timetable_id`) REFERENCES `timetable` (`id`) ON DELETE CASCADE,
  CONSTRAINT `timetable_lecturers_ibfk_2` FOREIGN KEY (`lecturer_id`) REFERENCES `lecturers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

-- ============================================================================
-- PROFESSIONAL VALIDATION AND BUSINESS LOGIC
-- ============================================================================

-- Create validation functions
DELIMITER $$

-- Function to validate department-oriented class-course assignment
DROP FUNCTION IF EXISTS `validate_class_course_assignment`$$
CREATE FUNCTION `validate_class_course_assignment`(
    p_class_id INT,
    p_course_id INT
) RETURNS JSON
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE v_class_program_dept INT;
    DECLARE v_class_level_id INT;
    DECLARE v_course_dept INT;
    DECLARE v_course_level_id INT;
    DECLARE v_course_type VARCHAR(50);
    DECLARE v_issues JSON DEFAULT JSON_ARRAY();
    DECLARE v_warnings JSON DEFAULT JSON_ARRAY();
    DECLARE v_quality_score INT DEFAULT 0;
    
    -- Get class details through program
    SELECT p.department_id, c.level_id
    INTO v_class_program_dept, v_class_level_id
    FROM classes c
    JOIN programs p ON c.program_id = p.id
    WHERE c.id = p_class_id AND c.is_active = 1;
    
    -- Get course details
    SELECT co.department_id, co.level_id, co.course_type
    INTO v_course_dept, v_course_level_id, v_course_type
    FROM courses co WHERE co.id = p_course_id AND co.is_active = 1;
    
    -- Validation 1: Both must exist and be active
    IF v_class_program_dept IS NULL THEN
        SET v_issues = JSON_ARRAY_APPEND(v_issues, '$', 'Class not found or inactive');
    END IF;
    
    IF v_course_dept IS NULL THEN
        SET v_issues = JSON_ARRAY_APPEND(v_issues, '$', 'Course not found or inactive');
    END IF;
    
    IF v_class_program_dept IS NOT NULL AND v_course_dept IS NOT NULL THEN
        -- Validation 2: Level must match (CRITICAL for academic integrity)
        IF v_class_level_id != v_course_level_id THEN
            SET v_issues = JSON_ARRAY_APPEND(v_issues, '$', 
                CONCAT('Level mismatch: Class level ', v_class_level_id, ', Course level ', v_course_level_id));
        ELSE
            SET v_quality_score = v_quality_score + 20; -- Major points for level match
        END IF;
        
        -- Validation 3: Department compatibility (Professional rule)
        IF v_class_program_dept = v_course_dept THEN
            SET v_quality_score = v_quality_score + 15; -- Points for same department
        ELSE
            IF v_course_type = 'core' THEN
                -- Core courses should be from same department
                SET v_issues = JSON_ARRAY_APPEND(v_issues, '$', 
                    CONCAT('Core course from different department: Class dept ', v_class_program_dept, ', Course dept ', v_course_dept));
            ELSE
                -- Cross-departmental electives are acceptable
                SET v_quality_score = v_quality_score + 5; -- Some points for cross-dept electives
                SET v_warnings = JSON_ARRAY_APPEND(v_warnings, '$', 
                    CONCAT('Cross-departmental ', v_course_type, ' course assignment'));
            END IF;
        END IF;
        
        -- Quality scoring based on course type
        CASE v_course_type
            WHEN 'core' THEN SET v_quality_score = v_quality_score + 10;
            WHEN 'elective' THEN SET v_quality_score = v_quality_score + 5;
            WHEN 'practical' THEN SET v_quality_score = v_quality_score + 8;
            WHEN 'project' THEN SET v_quality_score = v_quality_score + 6;
        END CASE;
        
        -- Check if already assigned
        IF EXISTS (
            SELECT 1 FROM class_courses 
            WHERE class_id = p_class_id AND course_id = p_course_id AND is_active = 1
        ) THEN
            SET v_warnings = JSON_ARRAY_APPEND(v_warnings, '$', 'Course already assigned to this class');
        END IF;
    END IF;
    
    -- Return comprehensive validation result
    RETURN JSON_OBJECT(
        'valid', JSON_LENGTH(v_issues) = 0,
        'errors', v_issues,
        'warnings', v_warnings,
        'quality_score', v_quality_score,
        'class_department', v_class_program_dept,
        'course_department', v_course_dept,
        'class_level', v_class_level_id,
        'course_level', v_course_level_id,
        'course_type', v_course_type
    );
END$$

-- Professional assignment procedure
DROP PROCEDURE IF EXISTS `assign_course_to_class_professional`$$
CREATE PROCEDURE `assign_course_to_class_professional`(
    IN p_class_id INT,
    IN p_course_id INT,
    IN p_lecturer_id INT,
    IN p_semester VARCHAR(20),
    IN p_academic_year VARCHAR(9),
    IN p_assigned_by VARCHAR(100),
    OUT p_result VARCHAR(200),
    OUT p_quality_score INT
)
BEGIN
    DECLARE v_validation JSON;
    DECLARE v_is_valid BOOLEAN DEFAULT FALSE;
    DECLARE v_warnings JSON;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_result = 'ERROR: Database error occurred';
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Professional validation
    SET v_validation = validate_class_course_assignment(p_class_id, p_course_id);
    SET v_is_valid = JSON_EXTRACT(v_validation, '$.valid');
    SET v_warnings = JSON_EXTRACT(v_validation, '$.warnings');
    SET p_quality_score = JSON_EXTRACT(v_validation, '$.quality_score');
    
    IF NOT v_is_valid THEN
        SET p_result = CONCAT('VALIDATION_FAILED: ', CAST(JSON_EXTRACT(v_validation, '$.errors') AS CHAR));
        ROLLBACK;
    ELSE
        -- Insert/update assignment with quality metadata
        INSERT INTO class_courses (
            class_id, course_id, lecturer_id, semester, academic_year,
            assigned_by, assignment_reason, quality_score, validation_notes, is_active
        ) VALUES (
            p_class_id, p_course_id, p_lecturer_id, p_semester, p_academic_year,
            p_assigned_by, 'Professional assignment with validation', p_quality_score, v_warnings, 1
        ) ON DUPLICATE KEY UPDATE
            lecturer_id = VALUES(lecturer_id),
            assigned_by = VALUES(assigned_by),
            quality_score = VALUES(quality_score),
            validation_notes = VALUES(validation_notes),
            is_active = 1,
            updated_at = CURRENT_TIMESTAMP;
        
        IF JSON_LENGTH(v_warnings) > 0 THEN
            SET p_result = CONCAT('SUCCESS_WITH_WARNINGS: ', CAST(v_warnings AS CHAR));
        ELSE
            SET p_result = 'SUCCESS: Assignment completed successfully';
        END IF;
        
        COMMIT;
    END IF;
END$$

-- Function to check timetable conflicts with professional logic
DROP FUNCTION IF EXISTS `check_timetable_conflicts_professional`$$
CREATE FUNCTION `check_timetable_conflicts_professional`(
    p_class_id INT,
    p_lecturer_id INT,
    p_room_id INT,
    p_day_id INT,
    p_time_slot_id INT,
    p_semester VARCHAR(20),
    p_academic_year VARCHAR(9),
    p_division_label VARCHAR(10)
) RETURNS JSON
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE v_conflicts JSON DEFAULT JSON_ARRAY();
    DECLARE v_room_capacity INT;
    DECLARE v_class_enrollment INT;
    
    -- Check room conflicts
    IF EXISTS (
        SELECT 1 FROM timetable 
        WHERE room_id = p_room_id AND day_id = p_day_id AND time_slot_id = p_time_slot_id 
        AND semester = p_semester AND academic_year = p_academic_year
    ) THEN
        SET v_conflicts = JSON_ARRAY_APPEND(v_conflicts, '$', 'room_occupied');
    END IF;
    
    -- Check lecturer conflicts
    IF EXISTS (
        SELECT 1 FROM timetable 
        WHERE lecturer_id = p_lecturer_id AND day_id = p_day_id AND time_slot_id = p_time_slot_id 
        AND semester = p_semester AND academic_year = p_academic_year
    ) THEN
        SET v_conflicts = JSON_ARRAY_APPEND(v_conflicts, '$', 'lecturer_busy');
    END IF;
    
    -- Check class conflicts (considering divisions)
    IF p_division_label IS NOT NULL THEN
        IF EXISTS (
            SELECT 1 FROM timetable 
            WHERE class_id = p_class_id AND day_id = p_day_id AND time_slot_id = p_time_slot_id 
            AND division_label = p_division_label AND semester = p_semester AND academic_year = p_academic_year
        ) THEN
            SET v_conflicts = JSON_ARRAY_APPEND(v_conflicts, '$', 'class_division_busy');
        END IF;
    ELSE
        IF EXISTS (
            SELECT 1 FROM timetable 
            WHERE class_id = p_class_id AND day_id = p_day_id AND time_slot_id = p_time_slot_id 
            AND semester = p_semester AND academic_year = p_academic_year
        ) THEN
            SET v_conflicts = JSON_ARRAY_APPEND(v_conflicts, '$', 'class_busy');
        END IF;
    END IF;
    
    -- Check room capacity vs class enrollment
    SELECT r.capacity INTO v_room_capacity FROM rooms r WHERE r.id = p_room_id;
    SELECT c.current_enrollment INTO v_class_enrollment FROM classes c WHERE c.id = p_class_id;
    
    IF v_class_enrollment > v_room_capacity THEN
        SET v_conflicts = JSON_ARRAY_APPEND(v_conflicts, '$', 'room_capacity_exceeded');
    END IF;
    
    RETURN v_conflicts;
END$$

-- Procedure for professional timetable generation
DROP PROCEDURE IF EXISTS `generate_timetable_professional`$$
CREATE PROCEDURE `generate_timetable_professional`(
    IN p_stream_id INT,
    IN p_semester VARCHAR(20),
    IN p_academic_year VARCHAR(9),
    IN p_clear_existing BOOLEAN,
    OUT p_success_count INT,
    OUT p_error_count INT,
    OUT p_conflict_count INT,
    OUT p_generation_notes TEXT
)
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_class_id INT;
    DECLARE v_course_id INT;
    DECLARE v_lecturer_id INT;
    DECLARE v_notes TEXT DEFAULT '';
    DECLARE v_placement_attempts INT DEFAULT 0;
    DECLARE v_max_attempts INT DEFAULT 100;
    
    -- Cursor for class-course assignments in the specified stream
    DECLARE assignment_cursor CURSOR FOR
        SELECT cc.class_id, cc.course_id, cc.lecturer_id
        FROM class_courses cc
        JOIN classes c ON cc.class_id = c.id
        WHERE c.stream_id = p_stream_id 
        AND cc.semester = p_semester 
        AND cc.academic_year = p_academic_year
        AND cc.is_active = 1
        AND c.is_active = 1
        ORDER BY c.program_id, c.level_id, cc.course_id;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    SET p_success_count = 0;
    SET p_error_count = 0;
    SET p_conflict_count = 0;
    
    -- Clear existing timetable if requested
    IF p_clear_existing THEN
        DELETE t FROM timetable t
        JOIN classes c ON t.class_id = c.id
        WHERE c.stream_id = p_stream_id 
        AND t.semester = p_semester 
        AND t.academic_year = p_academic_year;
        
        SET v_notes = CONCAT(v_notes, 'Cleared existing timetable entries. ');
    END IF;
    
    OPEN assignment_cursor;
    
    assignment_loop: LOOP
        FETCH assignment_cursor INTO v_class_id, v_course_id, v_lecturer_id;
        IF done THEN
            LEAVE assignment_loop;
        END IF;
        
        SET v_placement_attempts = v_placement_attempts + 1;
        
        -- This is a simplified version - actual placement logic would be implemented in PHP
        -- for complex scheduling algorithms
        SET p_success_count = p_success_count + 1;
        
        IF v_placement_attempts >= v_max_attempts THEN
            SET v_notes = CONCAT(v_notes, 'Reached maximum placement attempts. ');
            LEAVE assignment_loop;
        END IF;
    END LOOP;
    
    CLOSE assignment_cursor;
    SET p_generation_notes = v_notes;
END$$

DELIMITER ;

-- ============================================================================
-- PROFESSIONAL VIEWS AND REPORTING
-- ============================================================================

-- Comprehensive class view with all context
CREATE OR REPLACE VIEW `classes_comprehensive` AS
SELECT 
    c.id,
    c.name as class_name,
    c.code as class_code,
    c.total_capacity,
    c.current_enrollment,
    c.divisions_count,
    c.academic_year,
    c.semester,
    c.is_active,
    
    -- Program context
    p.name as program_name,
    p.code as program_code,
    p.duration_years,
    p.degree_type,
    
    -- Department context
    d.name as department_name,
    d.code as department_code,
    
    -- Level context
    l.name as level_name,
    l.code as level_code,
    l.numeric_value as level_number,
    
    -- Stream context (ONLY for classes)
    s.name as stream_name,
    s.code as stream_code,
    s.period_start,
    s.period_end,
    s.active_days,
    
    -- Calculated metrics
    ROUND((c.current_enrollment * 100.0) / NULLIF(c.total_capacity, 0), 1) as enrollment_percentage,
    CASE 
        WHEN c.current_enrollment >= c.total_capacity THEN 'Full'
        WHEN c.current_enrollment >= (c.total_capacity * 0.9) THEN 'Nearly Full'
        WHEN c.current_enrollment >= (c.total_capacity * 0.5) THEN 'Half Full'
        ELSE 'Available'
    END as enrollment_status,
    
    -- Assignment statistics
    (SELECT COUNT(*) FROM class_courses cc WHERE cc.class_id = c.id AND cc.is_active = 1) as assigned_courses_count,
    (SELECT COUNT(*) FROM timetable t WHERE t.class_id = c.id AND t.semester = c.semester AND t.academic_year = c.academic_year) as scheduled_sessions_count

FROM classes c
LEFT JOIN programs p ON c.program_id = p.id
LEFT JOIN departments d ON p.department_id = d.id
LEFT JOIN levels l ON c.level_id = l.id
LEFT JOIN streams s ON c.stream_id = s.id;

-- Professional course assignment recommendations
CREATE OR REPLACE VIEW `course_assignment_recommendations` AS
SELECT 
    c.id as class_id,
    c.name as class_name,
    c.code as class_code,
    co.id as course_id,
    co.code as course_code,
    co.name as course_name,
    co.course_type,
    co.credits,
    
    -- Department context
    d1.name as class_department,
    d2.name as course_department,
    
    -- Level context
    l1.name as class_level,
    l2.name as course_level,
    
    -- Professional scoring (0-50 scale)
    (
        CASE WHEN p.department_id = co.department_id THEN 20 ELSE 0 END +  -- Same department
        CASE WHEN c.level_id = co.level_id THEN 20 ELSE -30 END +          -- Same level (critical)
        CASE WHEN co.course_type = 'core' THEN 10 ELSE 0 END +             -- Core course bonus
        CASE WHEN co.course_type = 'elective' THEN 5 ELSE 0 END +          -- Elective bonus
        CASE WHEN co.course_type = 'practical' THEN 8 ELSE 0 END           -- Practical bonus
    ) as recommendation_score,
    
    -- Professional recommendation categories
    CASE 
        WHEN cc.id IS NOT NULL AND cc.is_active = 1 THEN 'already_assigned'
        WHEN p.department_id = co.department_id AND c.level_id = co.level_id AND co.course_type = 'core' THEN 'highly_recommended'
        WHEN p.department_id = co.department_id AND c.level_id = co.level_id THEN 'recommended'
        WHEN c.level_id = co.level_id AND co.course_type IN ('elective', 'practical') THEN 'acceptable'
        WHEN c.level_id = co.level_id THEN 'possible_with_approval'
        ELSE 'not_suitable'
    END as recommendation_status,
    
    -- Compatibility flags
    p.department_id = co.department_id as department_match,
    c.level_id = co.level_id as level_match,
    
    -- Assignment metadata
    cc.assigned_by,
    cc.quality_score as current_quality_score,
    cc.approval_status

FROM classes c
JOIN programs p ON c.program_id = p.id
JOIN departments d1 ON p.department_id = d1.id
JOIN levels l1 ON c.level_id = l1.id
CROSS JOIN courses co
JOIN departments d2 ON co.department_id = d2.id
JOIN levels l2 ON co.level_id = l2.id
LEFT JOIN class_courses cc ON c.id = cc.class_id AND co.id = cc.course_id
WHERE c.is_active = 1 AND co.is_active = 1;

-- Timetable summary with stream context
CREATE OR REPLACE VIEW `timetable_summary` AS
SELECT 
    t.id,
    
    -- Class information (with stream context)
    c.name as class_name,
    c.code as class_code,
    c.total_capacity,
    c.current_enrollment,
    s.name as stream_name,
    s.period_start as stream_start,
    s.period_end as stream_end,
    
    -- Course information (global)
    co.code as course_code,
    co.name as course_name,
    co.credits,
    co.course_type,
    
    -- Lecturer information (global)
    l.name as lecturer_name,
    l.title as lecturer_title,
    l.rank as lecturer_rank,
    
    -- Room information (global)
    r.name as room_name,
    r.room_type,
    r.capacity as room_capacity,
    b.name as building_name,
    
    -- Time information
    d.name as day_name,
    d.short_name as day_short,
    ts.start_time,
    ts.end_time,
    ts.slot_name,
    
    -- Department information
    dept.name as department_name,
    dept.code as department_code,
    
    -- Academic context
    t.semester,
    t.academic_year,
    t.division_label,
    t.timetable_type,
    
    -- Quality indicators
    CASE 
        WHEN c.current_enrollment > r.capacity THEN 'overcapacity'
        WHEN c.current_enrollment = 0 THEN 'no_enrollment'
        ELSE 'ok'
    END as capacity_status,
    
    CASE 
        WHEN ts.start_time < s.period_start OR ts.end_time > s.period_end THEN 'outside_stream_period'
        WHEN ts.start_time >= s.break_start AND ts.end_time <= s.break_end THEN 'during_break'
        ELSE 'valid_time'
    END as time_validity

FROM timetable t
JOIN classes c ON t.class_id = c.id
JOIN courses co ON t.course_id = co.id
JOIN lecturers l ON t.lecturer_id = l.id
JOIN rooms r ON t.room_id = r.id
LEFT JOIN buildings b ON r.building_id = b.id
JOIN days d ON t.day_id = d.id
JOIN time_slots ts ON t.time_slot_id = ts.id
JOIN programs p ON c.program_id = p.id
JOIN departments dept ON p.department_id = dept.id
JOIN streams s ON c.stream_id = s.id;

-- Stream utilization statistics
CREATE OR REPLACE VIEW `stream_utilization` AS
SELECT 
    s.id as stream_id,
    s.name as stream_name,
    s.code as stream_code,
    s.period_start,
    s.period_end,
    s.is_active as stream_active,
    
    -- Class statistics (stream-specific)
    COUNT(DISTINCT c.id) as total_classes,
    SUM(c.total_capacity) as total_capacity,
    SUM(c.current_enrollment) as total_enrollment,
    
    -- Course assignments (derived from stream classes)
    COUNT(DISTINCT cc.course_id) as assigned_courses,
    COUNT(DISTINCT cc.id) as total_assignments,
    
    -- Timetable utilization
    COUNT(DISTINCT t.id) as scheduled_sessions,
    COUNT(DISTINCT t.room_id) as rooms_used,
    COUNT(DISTINCT t.lecturer_id) as lecturers_used,
    
    -- Time slot utilization
    COUNT(DISTINCT CONCAT(t.day_id, '-', t.time_slot_id)) as time_slots_used,
    (SELECT COUNT(*) FROM stream_time_slots WHERE stream_id = s.id AND is_active = 1) as available_time_slots,
    
    -- Calculated metrics
    ROUND(
        (COUNT(DISTINCT CONCAT(t.day_id, '-', t.time_slot_id)) * 100.0) / 
        NULLIF((SELECT COUNT(*) FROM stream_time_slots WHERE stream_id = s.id AND is_active = 1), 0), 
        2
    ) as time_utilization_percent,
    
    ROUND(
        (SUM(c.current_enrollment) * 100.0) / NULLIF(SUM(c.total_capacity), 0), 
        2
    ) as enrollment_percent

FROM streams s
LEFT JOIN classes c ON s.id = c.stream_id AND c.is_active = 1
LEFT JOIN class_courses cc ON c.id = cc.class_id AND cc.is_active = 1
LEFT JOIN timetable t ON c.id = t.class_id AND cc.course_id = t.course_id
GROUP BY s.id
ORDER BY s.sort_order;

-- Assignment quality monitoring
CREATE OR REPLACE VIEW `assignment_quality_monitor` AS
SELECT 
    cc.id as assignment_id,
    cc.quality_score,
    cc.approval_status,
    cc.assigned_by,
    cc.created_at as assignment_date,
    
    -- Class context
    c.name as class_name,
    c.code as class_code,
    s.name as stream_name,
    
    -- Course context  
    co.code as course_code,
    co.name as course_name,
    co.course_type,
    
    -- Department context
    d1.name as class_department,
    d2.name as course_department,
    
    -- Level context
    l1.name as class_level,
    l2.name as course_level,
    
    -- Quality indicators
    CASE 
        WHEN cc.quality_score >= 40 THEN 'excellent'
        WHEN cc.quality_score >= 30 THEN 'good'
        WHEN cc.quality_score >= 20 THEN 'acceptable'
        WHEN cc.quality_score >= 10 THEN 'needs_review'
        ELSE 'poor'
    END as quality_rating,
    
    -- Issues
    d1.name = d2.name as dept_match,
    l1.name = l2.name as level_match,
    
    CASE 
        WHEN l1.id != l2.id THEN 'level_mismatch'
        WHEN d1.id != d2.id AND co.course_type = 'core' THEN 'core_course_wrong_dept'
        WHEN cc.quality_score < 20 THEN 'low_quality'
        ELSE 'no_issues'
    END as primary_issue

FROM class_courses cc
JOIN classes c ON cc.class_id = c.id
JOIN programs p ON c.program_id = p.id
JOIN departments d1 ON p.department_id = d1.id
JOIN levels l1 ON c.level_id = l1.id
JOIN streams s ON c.stream_id = s.id
JOIN courses co ON cc.course_id = co.id
JOIN departments d2 ON co.department_id = d2.id
JOIN levels l2 ON co.level_id = l2.id
WHERE cc.is_active = 1;

-- ============================================================================
-- TRIGGERS FOR DATA INTEGRITY
-- ============================================================================

DELIMITER $$

-- Trigger to ensure only one current stream
DROP TRIGGER IF EXISTS `ensure_single_current_stream`$$
CREATE TRIGGER `ensure_single_current_stream`
BEFORE UPDATE ON `streams`
FOR EACH ROW
BEGIN
    IF NEW.is_current = 1 AND OLD.is_current = 0 THEN
        UPDATE streams SET is_current = 0 WHERE id != NEW.id;
    END IF;
END$$

-- Trigger to validate class-course assignments
DROP TRIGGER IF EXISTS `validate_class_course_assignment_trigger`$$
CREATE TRIGGER `validate_class_course_assignment_trigger`
BEFORE INSERT ON `class_courses`
FOR EACH ROW
BEGIN
    DECLARE v_validation JSON;
    DECLARE v_is_valid BOOLEAN;
    
    SET v_validation = validate_class_course_assignment(NEW.class_id, NEW.course_id);
    SET v_is_valid = JSON_EXTRACT(v_validation, '$.valid');
    
    IF NOT v_is_valid THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = CONCAT('Assignment validation failed: ', CAST(JSON_EXTRACT(v_validation, '$.errors') AS CHAR));
    END IF;
    
    -- Set quality score automatically
    SET NEW.quality_score = JSON_EXTRACT(v_validation, '$.quality_score');
    SET NEW.validation_notes = JSON_EXTRACT(v_validation, '$.warnings');
END$$

-- Trigger to prevent room capacity violations
DROP TRIGGER IF EXISTS `prevent_room_capacity_violation`$$
CREATE TRIGGER `prevent_room_capacity_violation`
BEFORE INSERT ON `timetable`
FOR EACH ROW
BEGIN
    DECLARE v_room_capacity INT;
    DECLARE v_class_enrollment INT;
    DECLARE v_room_name VARCHAR(50);
    DECLARE v_class_name VARCHAR(100);
    
    SELECT r.capacity, r.name INTO v_room_capacity, v_room_name 
    FROM rooms r WHERE r.id = NEW.room_id;
    
    SELECT c.current_enrollment, c.name INTO v_class_enrollment, v_class_name
    FROM classes c WHERE c.id = NEW.class_id;
    
    IF v_class_enrollment > v_room_capacity THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = CONCAT('Room capacity exceeded: ', v_room_name, 
                                 ' (capacity: ', v_room_capacity, 
                                 ') cannot accommodate ', v_class_name, 
                                 ' (enrollment: ', v_class_enrollment, ')');
    END IF;
END$$

-- Trigger to auto-populate division_capacity
DROP TRIGGER IF EXISTS `auto_calculate_division_capacity`$$
CREATE TRIGGER `auto_calculate_division_capacity`
BEFORE INSERT ON `classes`
FOR EACH ROW
BEGIN
    IF NEW.division_capacity IS NULL AND NEW.divisions_count > 0 THEN
        SET NEW.division_capacity = CEIL(NEW.total_capacity / NEW.divisions_count);
    END IF;
END$$

DROP TRIGGER IF EXISTS `auto_calculate_division_capacity_update`$$
CREATE TRIGGER `auto_calculate_division_capacity_update`
BEFORE UPDATE ON `classes`
FOR EACH ROW
BEGIN
    IF (NEW.total_capacity != OLD.total_capacity OR NEW.divisions_count != OLD.divisions_count) 
       AND NEW.divisions_count > 0 THEN
        SET NEW.division_capacity = CEIL(NEW.total_capacity / NEW.divisions_count);
    END IF;
END$$

DELIMITER ;

-- ============================================================================
-- AUDIT AND MONITORING TABLES
-- ============================================================================

-- Audit log for tracking changes
CREATE TABLE IF NOT EXISTS `audit_log` (
    `id` int NOT NULL AUTO_INCREMENT,
    `table_name` varchar(50) NOT NULL,
    `record_id` int NOT NULL,
    `action` enum('insert','update','delete') NOT NULL,
    `old_values` json DEFAULT NULL,
    `new_values` json DEFAULT NULL,
    `changed_by` varchar(100) DEFAULT NULL,
    `ip_address` varchar(45) DEFAULT NULL,
    `user_agent` text DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    INDEX `idx_audit_table_record` (`table_name`, `record_id`),
    INDEX `idx_audit_date` (`created_at`),
    INDEX `idx_audit_user` (`changed_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Timetable generation log
CREATE TABLE IF NOT EXISTS `timetable_generation_log` (
    `id` int NOT NULL AUTO_INCREMENT,
    `stream_id` int NOT NULL,
    `semester` varchar(20) NOT NULL,
    `academic_year` varchar(9) NOT NULL,
    `total_assignments` int DEFAULT 0,
    `successful_placements` int DEFAULT 0,
    `failed_placements` int DEFAULT 0,
    `conflicts_detected` int DEFAULT 0,
    `generation_time_seconds` decimal(10,3) DEFAULT 0,
    `algorithm_used` varchar(50) DEFAULT 'professional_placement',
    `generated_by` varchar(100) DEFAULT NULL,
    `generation_notes` text DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    FOREIGN KEY (`stream_id`) REFERENCES `streams` (`id`) ON DELETE CASCADE,
    INDEX `idx_log_stream_date` (`stream_id`, `created_at`),
    INDEX `idx_log_academic` (`academic_year`, `semester`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- System configuration
CREATE TABLE IF NOT EXISTS `system_config` (
    `id` int NOT NULL AUTO_INCREMENT,
    `config_key` varchar(100) NOT NULL UNIQUE,
    `config_value` text NOT NULL,
    `config_type` enum('string','integer','boolean','json','time','date') DEFAULT 'string',
    `description` text DEFAULT NULL,
    `category` varchar(50) DEFAULT 'general',
    `is_system` tinyint(1) DEFAULT '0',
    `validation_rules` json DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    INDEX `idx_config_category` (`category`),
    INDEX `idx_config_system` (`is_system`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Insert professional system configurations
INSERT INTO `system_config` (`config_key`, `config_value`, `config_type`, `description`, `category`, `is_system`) VALUES
('current_academic_year', '2024/2025', 'string', 'Current academic year', 'academic', 1),
('current_semester', 'first', 'string', 'Current semester', 'academic', 1),
('default_class_capacity', '30', 'integer', 'Default capacity for new classes', 'classes', 0),
('max_daily_hours_per_class', '8', 'integer', 'Maximum daily hours per class', 'scheduling', 0),
('max_weekly_hours_per_class', '25', 'integer', 'Maximum weekly hours per class', 'scheduling', 0),
('enable_cross_departmental_electives', 'true', 'boolean', 'Allow cross-departmental elective assignments', 'validation', 0),
('minimum_assignment_quality_score', '20', 'integer', 'Minimum quality score for assignments', 'validation', 0),
('require_approval_for_cross_dept_core', 'true', 'boolean', 'Require approval for cross-departmental core courses', 'validation', 0),
('timetable_generation_algorithm', 'professional_placement', 'string', 'Algorithm used for timetable generation', 'scheduling', 0),
('max_placement_attempts', '100', 'integer', 'Maximum attempts to place each assignment', 'scheduling', 0);

-- ============================================================================
-- POPULATE STREAM MAPPINGS
-- ============================================================================

-- Populate stream-time slot mappings based on stream periods
INSERT IGNORE INTO `stream_time_slots` (`stream_id`, `time_slot_id`, `priority`)
SELECT s.id, ts.id,
    CASE 
        WHEN ts.start_time BETWEEN '08:00:00' AND '12:00:00' THEN 1  -- Morning priority
        WHEN ts.start_time BETWEEN '14:00:00' AND '16:00:00' THEN 2  -- Afternoon priority
        ELSE 3  -- Evening/other
    END as priority
FROM streams s
CROSS JOIN time_slots ts
WHERE s.is_active = 1 
AND ts.is_active = 1 
AND NOT ts.is_break
AND (
    (s.id = 1 AND ts.start_time >= '08:00:00' AND ts.end_time <= '17:00:00') OR  -- Regular
    (s.id = 2 AND ts.start_time >= '09:00:00' AND ts.end_time <= '17:00:00') OR  -- Weekend
    (s.id = 3 AND ts.start_time >= '18:00:00' AND ts.end_time <= '22:00:00')     -- Evening
);

-- Populate stream-day mappings
INSERT IGNORE INTO `stream_days` (`stream_id`, `day_id`)
SELECT s.id, d.id
FROM streams s
CROSS JOIN days d
WHERE s.is_active = 1 
AND d.is_active = 1
AND (
    (s.id = 1 AND d.sort_order BETWEEN 1 AND 5) OR  -- Regular: Mon-Fri
    (s.id = 2 AND d.sort_order BETWEEN 6 AND 7) OR  -- Weekend: Sat-Sun
    (s.id = 3 AND d.sort_order BETWEEN 1 AND 5)     -- Evening: Mon-Fri
);

-- ============================================================================
-- RESTORE SQL SETTINGS
-- ============================================================================

/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Schema enhancement completed
