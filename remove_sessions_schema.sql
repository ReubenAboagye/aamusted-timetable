-- Remove Sessions from Database Schema
-- This file removes all session-related tables, columns, and constraints

-- 1. Drop session-related tables
DROP TABLE IF EXISTS `sessions`;
DROP TABLE IF EXISTS `course_session_availability`;
DROP TABLE IF EXISTS `lecturer_session_availability`;

-- 2. Remove session_id columns from existing tables
ALTER TABLE `class_courses` DROP COLUMN `session_id`;
ALTER TABLE `timetable` DROP COLUMN `session_id`;

-- 3. Drop foreign key constraints that reference sessions
ALTER TABLE `class_courses` DROP FOREIGN KEY `class_courses_ibfk_3`;
ALTER TABLE `timetable` DROP FOREIGN KEY `timetable_ibfk_1`;

-- 4. Create new course_room_types table for room type preferences
CREATE TABLE IF NOT EXISTS `course_room_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `course_id` int NOT NULL,
  `preferred_room_type` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_course_room_type` (`course_id`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `course_room_types_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 5. Update unique constraints in class_courses table
ALTER TABLE `class_courses` DROP INDEX `uq_class_course_sem`;
ALTER TABLE `class_courses` ADD UNIQUE KEY `uq_class_course` (`class_id`, `course_id`);

-- 6. Update unique constraints in timetable table
ALTER TABLE `timetable` DROP INDEX `uq_tt_slot`;
ALTER TABLE `timetable` ADD UNIQUE KEY `uq_tt_slot` (`day_id`, `time_slot_id`, `room_id`);

-- 7. Insert sample room types for courses (optional)
INSERT INTO `course_room_types` (`course_id`, `preferred_room_type`) VALUES
(101, 'Lecture Hall'),
(102, 'Laboratory'),
(103, 'Computer Lab'),
(104, 'Seminar Room'),
(105, 'Lecture Hall');

-- 8. Clean up any remaining session references in data
-- This will remove any timetable entries that had session_id references
DELETE FROM `timetable` WHERE `session_id` IS NOT NULL;

-- 9. Update any remaining references to use default values
-- For example, if you want to keep existing class_courses but without sessions
-- (This step is optional and depends on your data migration strategy)

-- 10. Verify the changes
-- You can run these queries to verify the session tables are gone:
-- SHOW TABLES LIKE '%session%';
-- DESCRIBE class_courses;
-- DESCRIBE timetable;
