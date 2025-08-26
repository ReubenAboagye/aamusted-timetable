-- Update database schema for improved constraint satisfaction
-- Run this script to add missing fields to existing tables

-- Add class_size to class table if it doesn't exist
ALTER TABLE `class` ADD COLUMN IF NOT EXISTS `class_size` INT DEFAULT 25;

-- Add capacity and room_type to room table if they don't exist
ALTER TABLE `room` ADD COLUMN IF NOT EXISTS `capacity` INT DEFAULT 30;
ALTER TABLE `room` ADD COLUMN IF NOT EXISTS `room_type` VARCHAR(50) DEFAULT 'classroom';

-- Add lecturer constraint fields if they don't exist
ALTER TABLE `lecturer` ADD COLUMN IF NOT EXISTS `max_daily_courses` INT DEFAULT 4;
ALTER TABLE `lecturer` ADD COLUMN IF NOT EXISTS `preferred_times` TEXT DEFAULT NULL;

-- Add course constraint fields if they don't exist
ALTER TABLE `course` ADD COLUMN IF NOT EXISTS `preferred_room_type` VARCHAR(50) DEFAULT NULL;
ALTER TABLE `course` ADD COLUMN IF NOT EXISTS `min_duration` INT DEFAULT 3; -- in hours
ALTER TABLE `course` ADD COLUMN IF NOT EXISTS `max_daily_count` INT DEFAULT 2;

-- Add building constraint fields if they don't exist
ALTER TABLE `building` ADD COLUMN IF NOT EXISTS `max_floors` INT DEFAULT 3;
ALTER TABLE `building` ADD COLUMN IF NOT EXISTS `has_elevator` BOOLEAN DEFAULT FALSE;

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS `idx_timetable_semester_day_time` ON `timetable` (`semester`, `day`, `time_slot`);
CREATE INDEX IF NOT EXISTS `idx_timetable_class_time` ON `timetable` (`class_id`, `day`, `time_slot`);
CREATE INDEX IF NOT EXISTS `idx_timetable_lecturer_time` ON `timetable` (`lecturer_id`, `day`, `time_slot`);
CREATE INDEX IF NOT EXISTS `idx_timetable_room_time` ON `timetable` (`room_id`, `day`, `time_slot`);

-- Update existing data with reasonable defaults
UPDATE `class` SET `class_size` = 25 WHERE `class_size` IS NULL;
UPDATE `room` SET `capacity` = 30 WHERE `capacity` IS NULL;
UPDATE `room` SET `room_type` = 'classroom' WHERE `room_type` IS NULL;
UPDATE `lecturer` SET `max_daily_courses` = 4 WHERE `max_daily_courses` IS NULL;
UPDATE `course` SET `preferred_room_type` = 'classroom' WHERE `preferred_room_type` IS NULL;
UPDATE `course` SET `min_duration` = 3 WHERE `min_duration` IS NULL;
UPDATE `course` SET `max_daily_count` = 2 WHERE `max_daily_count` IS NULL;
