-- RESTRUCTURED DATABASE SCHEMA FOR TIMETABLE SYSTEM
-- Based on original DB Schema.sql with professional enhancements
-- Maintains compatibility while adding validation, quality scoring, and professional features

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
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES, SQL_NOTES=0 */;

-- ============================================================================
-- GLOBAL TABLES (Shared across all streams)
-- ============================================================================

--
-- Table structure for table `buildings` (GLOBAL)
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
  `floors_count` int DEFAULT '1',
  `accessibility_features` json DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_building_code` (`code`),
  UNIQUE KEY `uq_building_name` (`name`),
  KEY `idx_building_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Table structure for table `departments` (GLOBAL)
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
  KEY `idx_dept_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Table structure for table `levels` (GLOBAL)
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
  `min_credits` int DEFAULT '15',
  `max_credits` int DEFAULT '25',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_level_code` (`code`),
  UNIQUE KEY `uq_level_name` (`name`),
  UNIQUE KEY `uq_level_numeric` (`numeric_value`),
  KEY `idx_level_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Table structure for table `programs` (GLOBAL - removed stream_id)
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
  KEY `idx_program_active` (`is_active`),
  CONSTRAINT `programs_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=72 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Table structure for table `courses` (GLOBAL - enhanced)
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
  `credits` int NOT NULL DEFAULT '3',
  `hours_per_week` int NOT NULL DEFAULT '3',
  `course_type` enum('core','elective','practical','project','seminar') DEFAULT 'core',
  `prerequisites` json DEFAULT NULL,
  `corequisites` json DEFAULT NULL,
  `preferred_room_type` varchar(50) DEFAULT 'classroom',
  `max_class_size` int DEFAULT '50',
  `description` text DEFAULT NULL,
  `learning_outcomes` text DEFAULT NULL,
  `assessment_methods` json DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_course_code` (`code`),
  KEY `idx_courses_department_id` (`department_id`),
  KEY `idx_courses_level_id` (`level_id`),
  KEY `idx_courses_type_active` (`course_type`,`is_active`),
  CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `courses_ibfk_2` FOREIGN KEY (`level_id`) REFERENCES `levels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_courses_credits` CHECK ((`credits` > 0)),
  CONSTRAINT `chk_courses_hours` CHECK ((`hours_per_week` > 0)),
  CONSTRAINT `chk_courses_max_size` CHECK ((`max_class_size` > 0))
) ENGINE=InnoDB AUTO_INCREMENT=121 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Table structure for table `lecturers` (GLOBAL - enhanced)
--
DROP TABLE IF EXISTS `lecturers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lecturers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `staff_id` varchar(20) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `title` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `department_id` int NOT NULL,
  `rank` enum('professor','associate_professor','assistant_professor','lecturer','assistant_lecturer','teaching_assistant') DEFAULT 'lecturer',
  `specialization` text,
  `qualifications` json DEFAULT NULL,
  `max_hours_per_week` int DEFAULT '20',
  `max_classes_per_day` int DEFAULT '4',
  `preferred_time_slots` json DEFAULT NULL,
  `unavailable_days` json DEFAULT NULL,
  `office_location` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lecturer_staff_id` (`staff_id`),
  UNIQUE KEY `uq_lecturer_email` (`email`),
  KEY `department_id` (`department_id`),
  KEY `idx_lecturer_active` (`is_active`),
  KEY `idx_lecturer_rank` (`rank`),
  CONSTRAINT `lecturers_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_lecturers_max_hours` CHECK ((`max_hours_per_week` > 0)),
  CONSTRAINT `chk_lecturers_max_classes` CHECK ((`max_classes_per_day` > 0))
) ENGINE=InnoDB AUTO_INCREMENT=88 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Table structure for table `room_types` (GLOBAL)
--
DROP TABLE IF EXISTS `room_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `room_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` text,
  `equipment_required` json DEFAULT NULL,
  `setup_time_minutes` int DEFAULT '0',
  `cleanup_time_minutes` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_room_type_name` (`name`),
  KEY `idx_room_type_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Table structure for table `rooms` (GLOBAL - enhanced)
--
DROP TABLE IF EXISTS `rooms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rooms` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `code` varchar(20) DEFAULT NULL,
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
  UNIQUE KEY `uq_room_name_building` (`name`,`building_id`),
  UNIQUE KEY `uq_room_code` (`code`),
  KEY `fk_rooms_building_id` (`building_id`),
  KEY `idx_room_type_capacity` (`room_type`,`capacity`),
  KEY `idx_room_active` (`is_active`),
  CONSTRAINT `fk_rooms_building_id` FOREIGN KEY (`building_id`) REFERENCES `buildings` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_rooms_capacity` CHECK ((`capacity` > 0))
) ENGINE=InnoDB AUTO_INCREMENT=68 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Table structure for table `days` (GLOBAL)
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
  UNIQUE KEY `uq_day_sort` (`sort_order`),
  KEY `idx_day_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Table structure for table `time_slots` (GLOBAL)
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
  KEY `idx_timeslot_active` (`is_break`),
  KEY `idx_timeslot_sort` (`sort_order`),
  CONSTRAINT `chk_timeslot_duration` CHECK ((`duration` > 0)),
  CONSTRAINT `chk_timeslot_times` CHECK ((`start_time` < `end_time`))
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ============================================================================
-- STREAM-SPECIFIC TABLES
-- ============================================================================

--
-- Table structure for table `streams` (Enhanced)
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
  `max_daily_hours` int DEFAULT '8',
  `max_weekly_hours` int DEFAULT '40',
  `color_code` varchar(7) DEFAULT '#007bff',
  `sort_order` int DEFAULT '1',
  `is_active` tinyint(1) DEFAULT '1',
  `is_current` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_stream_active` (`is_active`),
  KEY `idx_stream_current` (`is_current`),
  KEY `idx_stream_sort` (`sort_order`),
  CONSTRAINT `chk_streams_period` CHECK ((`period_start` < `period_end`)),
  CONSTRAINT `chk_streams_max_daily` CHECK ((`max_daily_hours` > 0)),
  CONSTRAINT `chk_streams_max_weekly` CHECK ((`max_weekly_hours` > 0))
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Table structure for table `stream_time_slots`
--
DROP TABLE IF EXISTS `stream_time_slots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stream_time_slots` (
  `id` int NOT NULL AUTO_INCREMENT,
  `stream_id` int NOT NULL,
  `time_slot_id` int NOT NULL,
  `priority` int DEFAULT '1',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_stream_time_slot` (`stream_id`,`time_slot_id`),
  KEY `idx_stream_id` (`stream_id`),
  KEY `idx_time_slot_id` (`time_slot_id`),
  KEY `idx_stream_slots_active` (`stream_id`,`is_active`),
  CONSTRAINT `fk_stream_time_slots_stream` FOREIGN KEY (`stream_id`) REFERENCES `streams` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_stream_time_slots_time_slot` FOREIGN KEY (`time_slot_id`) REFERENCES `time_slots` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=128 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `stream_days`
--
DROP TABLE IF EXISTS `stream_days`;
CREATE TABLE `stream_days` (
  `id` int NOT NULL AUTO_INCREMENT,
  `stream_id` int NOT NULL,
  `day_id` int NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_stream_day` (`stream_id`,`day_id`),
  KEY `idx_stream_days_active` (`stream_id`,`is_active`),
  CONSTRAINT `fk_stream_days_stream` FOREIGN KEY (`stream_id`) REFERENCES `streams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_stream_days_day` FOREIGN KEY (`day_id`) REFERENCES `days` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Table structure for table `classes` (ONLY stream-specific table)
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
  `max_daily_courses` int DEFAULT '4',
  `max_weekly_hours` int DEFAULT '25',
  `special_requirements` json DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_class_code_stream_academic` (`code`,`stream_id`,`academic_year`,`semester`),
  KEY `program_id` (`program_id`),
  KEY `level_id` (`level_id`),
  KEY `stream_id` (`stream_id`),
  KEY `idx_class_stream_active` (`stream_id`,`is_active`),
  KEY `idx_class_academic` (`academic_year`,`semester`),
  KEY `idx_class_enrollment` (`current_enrollment`,`total_capacity`),
  CONSTRAINT `classes_ibfk_1` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `classes_ibfk_2` FOREIGN KEY (`level_id`) REFERENCES `levels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `classes_ibfk_3` FOREIGN KEY (`stream_id`) REFERENCES `streams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_classes_capacity` CHECK ((`total_capacity` > 0)),
  CONSTRAINT `chk_classes_enrollment` CHECK ((`current_enrollment` >= 0)),
  CONSTRAINT `chk_classes_enrollment_capacity` CHECK ((`current_enrollment` <= `total_capacity`)),
  CONSTRAINT `chk_classes_divisions` CHECK ((`divisions_count` > 0)),
  CONSTRAINT `chk_classes_max_daily` CHECK ((`max_daily_courses` > 0)),
  CONSTRAINT `chk_classes_max_weekly` CHECK ((`max_weekly_hours` > 0))
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ============================================================================
-- RELATIONSHIP TABLES (Connect global entities)
-- ============================================================================

--
-- Table structure for table `lecturer_courses` (GLOBAL)
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
  `max_classes_per_week` int DEFAULT '5',
  `preferred_time_slots` json DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lecturer_course` (`lecturer_id`,`course_id`),
  KEY `course_id` (`course_id`),
  KEY `idx_lc_active` (`is_active`),
  KEY `idx_lc_primary` (`is_primary`,`is_active`),
  CONSTRAINT `lecturer_courses_ibfk_1` FOREIGN KEY (`lecturer_id`) REFERENCES `lecturers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lecturer_courses_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_lc_max_classes` CHECK ((`max_classes_per_week` > 0))
) ENGINE=InnoDB AUTO_INCREMENT=93 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Table structure for table `course_room_types` (Enhanced)
--
DROP TABLE IF EXISTS `course_room_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `course_room_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `course_id` int NOT NULL,
  `room_type_id` int NOT NULL,
  `priority` int DEFAULT '1',
  `is_required` tinyint(1) DEFAULT '0',
  `setup_requirements` json DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_course_room_type` (`course_id`,`room_type_id`),
  KEY `room_type_id` (`room_type_id`),
  KEY `idx_crt_priority` (`course_id`,`priority`),
  CONSTRAINT `course_room_types_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `course_room_types_ibfk_2` FOREIGN KEY (`room_type_id`) REFERENCES `room_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_crt_priority` CHECK ((`priority` > 0))
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Table structure for table `class_courses` (Enhanced with professional features)
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
  `assignment_type` enum('automatic','manual','recommended','smart') DEFAULT 'manual',
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
  KEY `idx_cc_class_active` (`class_id`,`is_active`),
  KEY `idx_cc_course_active` (`course_id`,`is_active`),
  KEY `idx_cc_academic` (`academic_year`,`semester`),
  KEY `idx_cc_approval` (`approval_status`),
  KEY `idx_cc_quality` (`quality_score`),
  KEY `idx_cc_assignment_type` (`assignment_type`),
  CONSTRAINT `class_courses_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `class_courses_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `class_courses_ibfk_3` FOREIGN KEY (`lecturer_id`) REFERENCES `lecturers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_cc_quality_score` CHECK ((`quality_score` IS NULL OR ((`quality_score` >= 0) AND (`quality_score` <= 50))))
) ENGINE=InnoDB AUTO_INCREMENT=74 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Table structure for table `timetable` (Enhanced)
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
  KEY `idx_timetable_academic` (`academic_year`,`semester`),
  KEY `idx_timetable_type` (`timetable_type`),
  CONSTRAINT `timetable_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `timetable_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `timetable_ibfk_3` FOREIGN KEY (`lecturer_id`) REFERENCES `lecturers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `timetable_ibfk_4` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `timetable_ibfk_5` FOREIGN KEY (`day_id`) REFERENCES `days` (`id`) ON DELETE CASCADE,
  CONSTRAINT `timetable_ibfk_6` FOREIGN KEY (`time_slot_id`) REFERENCES `time_slots` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Table structure for table `timetable_lecturers` (Enhanced for multiple lecturers)
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

-- ============================================================================
-- PROFESSIONAL MONITORING AND AUDIT TABLES
-- ============================================================================

--
-- Table structure for table `timetable_generation_log`
--
CREATE TABLE IF NOT EXISTS `timetable_generation_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `stream_id` int NOT NULL,
  `semester` varchar(20) NOT NULL,
  `academic_year` varchar(9) NOT NULL,
  `total_assignments` int DEFAULT '0',
  `successful_placements` int DEFAULT '0',
  `failed_placements` int DEFAULT '0',
  `conflicts_detected` int DEFAULT '0',
  `generation_time_seconds` decimal(10,3) DEFAULT '0.000',
  `algorithm_used` varchar(50) DEFAULT 'professional_placement',
  `generated_by` varchar(100) DEFAULT NULL,
  `generation_notes` text DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_log_stream` (`stream_id`),
  KEY `idx_log_stream_date` (`stream_id`,`created_at`),
  KEY `idx_log_academic` (`academic_year`,`semester`),
  CONSTRAINT `fk_log_stream` FOREIGN KEY (`stream_id`) REFERENCES `streams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Table structure for table `professional_config`
--
CREATE TABLE IF NOT EXISTS `professional_config` (
  `id` int NOT NULL AUTO_INCREMENT,
  `config_key` varchar(100) NOT NULL,
  `config_value` text NOT NULL,
  `config_type` enum('string','integer','boolean','json','time','date') DEFAULT 'string',
  `category` varchar(50) DEFAULT 'general',
  `description` text DEFAULT NULL,
  `is_system` tinyint(1) DEFAULT '0',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_config_key` (`config_key`),
  KEY `idx_config_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ============================================================================
-- PROFESSIONAL VALIDATION FUNCTIONS
-- ============================================================================

DELIMITER $$

DROP FUNCTION IF EXISTS `validate_class_course_professional`$$
CREATE FUNCTION `validate_class_course_professional`(
    p_class_id INT,
    p_course_id INT
) RETURNS JSON
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE v_class_dept INT;
    DECLARE v_class_level INT;
    DECLARE v_course_dept INT;
    DECLARE v_course_level INT;
    DECLARE v_course_type VARCHAR(50);
    DECLARE v_quality_score INT DEFAULT 0;
    DECLARE v_issues JSON DEFAULT JSON_ARRAY();
    
    -- Get class and course details
    SELECT p.department_id, c.level_id INTO v_class_dept, v_class_level
    FROM classes c JOIN programs p ON c.program_id = p.id 
    WHERE c.id = p_class_id AND c.is_active = 1;
    
    SELECT department_id, level_id, course_type INTO v_course_dept, v_course_level, v_course_type
    FROM courses WHERE id = p_course_id AND is_active = 1;
    
    -- Validation logic
    IF v_class_dept IS NULL THEN
        SET v_issues = JSON_ARRAY_APPEND(v_issues, '$', 'Class not found');
    END IF;
    
    IF v_course_dept IS NULL THEN
        SET v_issues = JSON_ARRAY_APPEND(v_issues, '$', 'Course not found');
    END IF;
    
    IF v_class_level = v_course_level THEN
        SET v_quality_score = v_quality_score + 25;
    ELSE
        SET v_issues = JSON_ARRAY_APPEND(v_issues, '$', 'Level mismatch');
    END IF;
    
    IF v_class_dept = v_course_dept THEN
        SET v_quality_score = v_quality_score + 20;
    ELSEIF v_course_type = 'core' THEN
        SET v_issues = JSON_ARRAY_APPEND(v_issues, '$', 'Core course from different department');
    ELSE
        SET v_quality_score = v_quality_score + 8;
    END IF;
    
    RETURN JSON_OBJECT('valid', JSON_LENGTH(v_issues) = 0, 'quality_score', v_quality_score, 'errors', v_issues);
END$$

DELIMITER ;

-- ============================================================================
-- PROFESSIONAL VIEWS
-- ============================================================================

CREATE OR REPLACE VIEW `assignment_quality_professional` AS
SELECT 
    cc.id as assignment_id,
    cc.quality_score,
    cc.approval_status,
    cc.assigned_by,
    cc.created_at as assignment_date,
    c.name as class_name,
    c.code as class_code,
    s.name as stream_name,
    co.code as course_code,
    co.name as course_name,
    co.course_type,
    d1.name as class_department,
    d2.name as course_department,
    l1.name as class_level,
    l2.name as course_level,
    CASE 
        WHEN cc.quality_score >= 40 THEN 'excellent'
        WHEN cc.quality_score >= 30 THEN 'good'
        WHEN cc.quality_score >= 20 THEN 'acceptable'
        ELSE 'needs_review'
    END as quality_rating,
    d1.id = d2.id as dept_match,
    l1.id = l2.id as level_match,
    CASE 
        WHEN l1.id != l2.id THEN 'level_mismatch'
        WHEN d1.id != d2.id AND co.course_type = 'core' THEN 'core_wrong_dept'
        WHEN cc.quality_score < 15 THEN 'low_quality'
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
LEFT JOIN levels l2 ON co.level_id = l2.id
WHERE cc.is_active = 1;

-- Insert default configurations
INSERT IGNORE INTO `professional_config` (`config_key`, `config_value`, `config_type`, `category`, `is_system`) VALUES
('current_academic_year', '2024/2025', 'string', 'academic', 1),
('current_semester', 'first', 'string', 'academic', 1),
('min_assignment_quality_score', '15', 'integer', 'validation', 0);

/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;