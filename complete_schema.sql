-- Complete Database Schema for AAMUSTED Timetable System
-- This file contains all table structures without sample data

-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS `timetable_system` 
CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;

USE `timetable_system`;

-- =====================================================
-- BUILDINGS TABLE
-- =====================================================
DROP TABLE IF EXISTS `timetable_lecturers`;
DROP TABLE IF EXISTS `timetable`;
DROP TABLE IF EXISTS `course_room_types`;
DROP TABLE IF EXISTS `class_courses`;
DROP TABLE IF EXISTS `lecturer_courses`;
DROP TABLE IF EXISTS `rooms`;
DROP TABLE IF EXISTS `classes`;
DROP TABLE IF EXISTS `programs`;
DROP TABLE IF EXISTS `lecturers`;
DROP TABLE IF EXISTS `courses`;
DROP TABLE IF EXISTS `levels`;
DROP TABLE IF EXISTS `departments`;
DROP TABLE IF EXISTS `time_slots`;
DROP TABLE IF EXISTS `days`;
DROP TABLE IF EXISTS `room_types`;
DROP TABLE IF EXISTS `streams`;
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
  UNIQUE KEY `uq_building_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================================================
-- STREAMS TABLE
-- =====================================================
CREATE TABLE `streams` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `code` varchar(20) NOT NULL,
  `description` text,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================================================
-- ROOMS TABLE
-- =====================================================
CREATE TABLE `rooms` (
  `id` int NOT NULL AUTO_INCREMENT,
  `building_id` int DEFAULT '1',
  `stream_id` int DEFAULT '1',
  `name` varchar(50) NOT NULL,
  `building` varchar(100) NOT NULL,
  `room_type` varchar(50) NOT NULL COMMENT 'Expected values: classroom, lecture_hall, laboratory, computer_lab, seminar_room, auditorium',
  `capacity` int NOT NULL,
  `stream_availability` json NOT NULL,
  `facilities` json DEFAULT NULL,
  `accessibility_features` json DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_room_name_building` (`name`,`building`),
  KEY `building_id` (`building_id`),
  KEY `stream_id` (`stream_id`),
  CONSTRAINT `rooms_ibfk_1` FOREIGN KEY (`building_id`) REFERENCES `buildings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rooms_ibfk_2` FOREIGN KEY (`stream_id`) REFERENCES `streams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================================================
-- DEPARTMENTS TABLE
-- =====================================================
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
  UNIQUE KEY `uq_dept_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================================================
-- PROGRAMS TABLE
-- =====================================================
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
  KEY `department_id` (`department_id`),
  CONSTRAINT `programs_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================================================
-- LEVELS TABLE
-- =====================================================
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
  UNIQUE KEY `uq_level_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================================================
-- CLASSES TABLE
-- =====================================================
CREATE TABLE `classes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `program_id` int NOT NULL,
  `level_id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `academic_year` varchar(9) NOT NULL,
  `semester` enum('first','second','summer') NOT NULL,
  `stream_id` int NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_class_code_year_semester` (`code`,`academic_year`,`semester`),
  KEY `program_id` (`program_id`),
  KEY `level_id` (`level_id`),
  KEY `stream_id` (`stream_id`),
  CONSTRAINT `classes_ibfk_1` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `classes_ibfk_2` FOREIGN KEY (`level_id`) REFERENCES `levels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `classes_ibfk_3` FOREIGN KEY (`stream_id`) REFERENCES `streams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================================================
-- COURSES TABLE
-- =====================================================
CREATE TABLE `courses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text,
  `credits` int NOT NULL,
  `lecture_hours` int DEFAULT '0',
  `tutorial_hours` int DEFAULT '0',
  `practical_hours` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_course_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================================================
-- LECTURERS TABLE
-- =====================================================
CREATE TABLE `lecturers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20),
  `department_id` int NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lecturer_email` (`email`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `lecturers_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================================================
-- DAYS TABLE
-- =====================================================
CREATE TABLE `days` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(20) NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_day_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================================================
-- TIME_SLOTS TABLE
-- =====================================================
CREATE TABLE `time_slots` (
  `id` int NOT NULL AUTO_INCREMENT,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `duration` int NOT NULL,
  `is_break` tinyint(1) DEFAULT '0',
  `is_mandatory` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_times` (`start_time`,`end_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================================================
-- CLASS_COURSES TABLE
-- =====================================================
CREATE TABLE `class_courses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `class_id` int NOT NULL,
  `course_id` int NOT NULL,
  `lecturer_id` int NOT NULL,
  `semester` enum('first','second','summer') NOT NULL,
  `academic_year` varchar(9) NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_class_course_semester_year` (`class_id`,`course_id`,`semester`,`academic_year`),
  KEY `course_id` (`course_id`),
  KEY `lecturer_id` (`lecturer_id`),
  CONSTRAINT `class_courses_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `class_courses_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `class_courses_ibfk_3` FOREIGN KEY (`lecturer_id`) REFERENCES `lecturers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================================================
-- LECTURER_COURSES TABLE
-- =====================================================
CREATE TABLE `lecturer_courses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `lecturer_id` int NOT NULL,
  `course_id` int NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lecturer_course` (`lecturer_id`,`course_id`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `lecturer_courses_ibfk_1` FOREIGN KEY (`lecturer_id`) REFERENCES `lecturers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lecturer_courses_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================================================
-- ROOM_TYPES TABLE
-- =====================================================
CREATE TABLE `room_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` text,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_room_type_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================================================
-- COURSE_ROOM_TYPES TABLE
-- =====================================================
CREATE TABLE `course_room_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `course_id` int NOT NULL,
  `room_type_id` int NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_course_room_type` (`course_id`,`room_type_id`),
  KEY `room_id` (`room_type_id`),
  CONSTRAINT `course_room_types_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `course_room_types_ibfk_2` FOREIGN KEY (`room_type_id`) REFERENCES `room_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================================================
-- TIMETABLE TABLE
-- =====================================================
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
  `timetable_type` enum('lecture','exam') NOT NULL DEFAULT 'lecture',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_timetable_slot` (`room_id`,`day_id`,`time_slot_id`,`semester`,`academic_year`,`timetable_type`),
  KEY `class_id` (`class_id`),
  KEY `course_id` (`course_id`),
  KEY `lecturer_id` (`lecturer_id`),
  KEY `day_id` (`day_id`),
  KEY `time_slot_id` (`time_slot_id`),
  CONSTRAINT `timetable_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `timetable_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `timetable_ibfk_3` FOREIGN KEY (`lecturer_id`) REFERENCES `lecturers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `timetable_ibfk_4` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `timetable_ibfk_5` FOREIGN KEY (`day_id`) REFERENCES `days` (`id`) ON DELETE CASCADE,
  CONSTRAINT `timetable_ibfk_6` FOREIGN KEY (`time_slot_id`) REFERENCES `time_slots` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================================================
-- TIMETABLE_LECTURERS TABLE
-- =====================================================
CREATE TABLE `timetable_lecturers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `timetable_id` int NOT NULL,
  `lecturer_id` int NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_timetable_lecturer` (`timetable_id`,`lecturer_id`),
  KEY `lecturer_id` (`lecturer_id`),
  CONSTRAINT `timetable_lecturers_ibfk_1` FOREIGN KEY (`timetable_id`) REFERENCES `timetable` (`id`) ON DELETE CASCADE,
  CONSTRAINT `timetable_lecturers_ibfk_2` FOREIGN KEY (`lecturer_id`) REFERENCES `lecturers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================================================
-- INSERT BASIC REQUIRED DATA
-- =====================================================

-- Insert basic days
INSERT INTO `days` (`name`) VALUES 
('Monday'), ('Tuesday'), ('Wednesday'), ('Thursday'), ('Friday'), ('Saturday'), ('Sunday');

-- Insert basic room types
INSERT INTO `room_types` (`name`, `description`) VALUES 
('classroom', 'Standard classroom'),
('lecture_hall', 'Large lecture hall'),
('laboratory', 'Science laboratory'),
('computer_lab', 'Computer laboratory'),
('seminar_room', 'Small seminar room'),
('auditorium', 'Large auditorium');

-- Insert basic streams
INSERT INTO `streams` (`name`, `code`, `description`) VALUES 
('Regular', 'REG', 'Regular daytime program'),
('Evening', 'EVE', 'Evening program'),
('Weekend', 'WKD', 'Weekend program');

-- Insert basic time slots (1-hour intervals from 7 AM to 8 PM)
INSERT INTO `time_slots` (`start_time`, `end_time`, `duration`) VALUES 
('07:00:00', '08:00:00', 60),
('08:00:00', '09:00:00', 60),
('09:00:00', '10:00:00', 60),
('10:00:00', '11:00:00', 60),
('11:00:00', '12:00:00', 60),
('12:00:00', '13:00:00', 60),
('13:00:00', '14:00:00', 60),
('14:00:00', '15:00:00', 60),
('15:00:00', '16:00:00', 60),
('16:00:00', '17:00:00', 60),
('17:00:00', '18:00:00', 60),
('18:00:00', '19:00:00', 60),
('19:00:00', '20:00:00', 60);

-- Insert at least one building (required for foreign key constraints)
INSERT INTO `buildings` (`name`, `code`, `description`) VALUES 
('Main Building', 'MB', 'Main academic building');

-- =====================================================
-- SCHEMA COMPLETE
-- =====================================================
-- This schema includes all necessary tables for the timetable system
-- No sample data except for basic required data (days, room types, streams, time slots, buildings)
-- All foreign key relationships are properly established
-- Tables are ready for your application data
