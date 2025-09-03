-- Migration 001: Fix Stream Schema and Add Constraints
-- This migration addresses critical stream-related schema issues

USE timetable_system;

-- 1. Create stream-specific time slots mapping table
CREATE TABLE IF NOT EXISTS `stream_time_slots` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `stream_id` INT NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `duration_minutes` INT NOT NULL,
    `is_break` BOOLEAN DEFAULT FALSE,
    `day_of_week` ENUM('monday','tuesday','wednesday','thursday','friday','saturday','sunday') NULL,
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    FOREIGN KEY (`stream_id`) REFERENCES `streams`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_stream_slot` (`stream_id`, `start_time`, `end_time`, `day_of_week`),
    INDEX `idx_stream_active` (`stream_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 2. Enhance streams table with missing fields
ALTER TABLE `streams` 
ADD COLUMN IF NOT EXISTS `period_start` TIME DEFAULT '08:00:00',
ADD COLUMN IF NOT EXISTS `period_end` TIME DEFAULT '17:00:00',
ADD COLUMN IF NOT EXISTS `break_start` TIME DEFAULT '12:00:00',
ADD COLUMN IF NOT EXISTS `break_end` TIME DEFAULT '13:00:00',
ADD COLUMN IF NOT EXISTS `active_days` JSON DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `max_daily_hours` INT DEFAULT 8,
ADD COLUMN IF NOT EXISTS `max_weekly_hours` INT DEFAULT 40;

-- 3. Add stream_id to class_courses for denormalization (performance)
ALTER TABLE `class_courses` 
ADD COLUMN IF NOT EXISTS `stream_id` INT NOT NULL DEFAULT 1 AFTER `course_id`,
ADD COLUMN IF NOT EXISTS `semester` VARCHAR(20) DEFAULT 'current',
ADD COLUMN IF NOT EXISTS `academic_year` VARCHAR(10) DEFAULT '2024/2025';

-- 4. Add foreign key for class_courses.stream_id
ALTER TABLE `class_courses` 
ADD CONSTRAINT IF NOT EXISTS `fk_class_courses_stream` 
FOREIGN KEY (`stream_id`) REFERENCES `streams`(`id`) ON DELETE CASCADE;

-- 5. Update class_courses.stream_id based on classes.stream_id
UPDATE `class_courses` cc 
JOIN `classes` c ON cc.class_id = c.id 
SET cc.stream_id = c.stream_id 
WHERE cc.stream_id != c.stream_id OR cc.stream_id IS NULL;

-- 6. Create view for valid class-course combinations
CREATE OR REPLACE VIEW `valid_class_course_combinations` AS
SELECT 
    c.id as class_id,
    co.id as course_id,
    c.stream_id,
    c.name as class_name,
    co.course_code,
    co.course_name
FROM classes c
JOIN courses co ON c.stream_id = co.stream_id
WHERE c.is_active = 1 AND co.is_active = 1;

-- 7. Add performance indexes
CREATE INDEX IF NOT EXISTS `idx_classes_stream_active` ON `classes`(`stream_id`, `is_active`);
CREATE INDEX IF NOT EXISTS `idx_courses_stream_active` ON `courses`(`stream_id`, `is_active`);
CREATE INDEX IF NOT EXISTS `idx_lecturers_stream_active` ON `lecturers`(`stream_id`, `is_active`);
CREATE INDEX IF NOT EXISTS `idx_rooms_stream_active` ON `rooms`(`stream_id`, `is_active`);
CREATE INDEX IF NOT EXISTS `idx_departments_stream_active` ON `departments`(`stream_id`, `is_active`);
CREATE INDEX IF NOT EXISTS `idx_timetable_lookup` ON `timetable`(`day_id`, `time_slot_id`, `room_id`);
CREATE INDEX IF NOT EXISTS `idx_class_courses_active` ON `class_courses`(`is_active`, `class_id`, `course_id`);
CREATE INDEX IF NOT EXISTS `idx_class_courses_stream` ON `class_courses`(`stream_id`, `is_active`);

-- 8. Create stream days mapping table
CREATE TABLE IF NOT EXISTS `stream_days` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `stream_id` INT NOT NULL,
    `day_id` INT NOT NULL,
    `is_active` BOOLEAN DEFAULT TRUE,
    
    PRIMARY KEY (`id`),
    FOREIGN KEY (`stream_id`) REFERENCES `streams`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`day_id`) REFERENCES `days`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_stream_day` (`stream_id`, `day_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 9. Populate stream_days based on streams.active_days JSON
INSERT IGNORE INTO `stream_days` (stream_id, day_id)
SELECT s.id, d.id
FROM streams s
CROSS JOIN days d
WHERE s.is_active = 1 AND d.is_active = 1
AND (
    s.active_days IS NULL 
    OR JSON_CONTAINS(s.active_days, CONCAT('"', LOWER(d.name), '"'))
    OR JSON_CONTAINS(s.active_days, CONCAT('"', d.id, '"'))
);

-- 10. Add timetable constraints for better data integrity
ALTER TABLE `timetable`
ADD COLUMN IF NOT EXISTS `academic_year` VARCHAR(10) DEFAULT '2024/2025',
ADD COLUMN IF NOT EXISTS `semester` VARCHAR(20) DEFAULT 'current',
ADD COLUMN IF NOT EXISTS `division_label` VARCHAR(50) DEFAULT NULL;

-- 11. Create conflict prevention trigger
DELIMITER $$

DROP TRIGGER IF EXISTS `prevent_cross_stream_assignments`$$

CREATE TRIGGER `prevent_cross_stream_assignments`
BEFORE INSERT ON `class_courses`
FOR EACH ROW
BEGIN
    DECLARE class_stream_id INT;
    DECLARE course_stream_id INT;
    
    SELECT stream_id INTO class_stream_id FROM classes WHERE id = NEW.class_id;
    SELECT stream_id INTO course_stream_id FROM courses WHERE id = NEW.course_id;
    
    IF class_stream_id != course_stream_id THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Cannot assign course from different stream to class';
    END IF;
    
    SET NEW.stream_id = class_stream_id;
END$$

DELIMITER ;

-- 12. Update existing invalid assignments
DELETE cc FROM class_courses cc
JOIN classes c ON cc.class_id = c.id
JOIN courses co ON cc.course_id = co.id
WHERE c.stream_id != co.stream_id;

-- 13. Populate default stream time slots for existing streams
INSERT IGNORE INTO `stream_time_slots` (stream_id, start_time, end_time, duration_minutes, is_active)
SELECT 
    s.id,
    TIME(CONCAT(HOUR(s.period_start) + slot_offset, ':00:00')),
    TIME(CONCAT(HOUR(s.period_start) + slot_offset + 1, ':00:00')),
    60,
    TRUE
FROM streams s
CROSS JOIN (
    SELECT 0 as slot_offset UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL 
    SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL 
    SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9
) slots
WHERE s.is_active = 1
AND TIME(CONCAT(HOUR(s.period_start) + slot_offset + 1, ':00:00')) <= s.period_end
AND NOT (
    s.break_start IS NOT NULL 
    AND s.break_end IS NOT NULL
    AND TIME(CONCAT(HOUR(s.period_start) + slot_offset, ':00:00')) >= s.break_start
    AND TIME(CONCAT(HOUR(s.period_start) + slot_offset + 1, ':00:00')) <= s.break_end
);
