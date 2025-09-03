-- Migration 002: Data Integrity Fixes and Validation
-- This migration adds data validation and integrity constraints

USE timetable_system;

-- 1. Create function to validate stream consistency
DELIMITER $$

DROP FUNCTION IF EXISTS `validate_stream_consistency`$$

CREATE FUNCTION `validate_stream_consistency`(
    p_class_id INT,
    p_course_id INT
) RETURNS BOOLEAN
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE class_stream INT;
    DECLARE course_stream INT;
    
    SELECT stream_id INTO class_stream FROM classes WHERE id = p_class_id;
    SELECT stream_id INTO course_stream FROM courses WHERE id = p_course_id;
    
    RETURN (class_stream = course_stream);
END$$

-- 2. Create stored procedure for safe class-course assignment
DROP PROCEDURE IF EXISTS `assign_course_to_class`$$

CREATE PROCEDURE `assign_course_to_class`(
    IN p_class_id INT,
    IN p_course_id INT,
    IN p_semester VARCHAR(20),
    IN p_academic_year VARCHAR(10)
)
BEGIN
    DECLARE v_stream_id INT;
    DECLARE v_class_stream INT;
    DECLARE v_course_stream INT;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Validate inputs
    IF p_class_id IS NULL OR p_course_id IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Class ID and Course ID cannot be NULL';
    END IF;
    
    -- Get stream IDs
    SELECT stream_id INTO v_class_stream FROM classes WHERE id = p_class_id AND is_active = 1;
    SELECT stream_id INTO v_course_stream FROM courses WHERE id = p_course_id AND is_active = 1;
    
    -- Validate class and course exist
    IF v_class_stream IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid or inactive class ID';
    END IF;
    
    IF v_course_stream IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid or inactive course ID';
    END IF;
    
    -- Validate stream consistency
    IF v_class_stream != v_course_stream THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot assign course from different stream to class';
    END IF;
    
    -- Insert assignment
    INSERT INTO class_courses (class_id, course_id, stream_id, semester, academic_year, is_active)
    VALUES (p_class_id, p_course_id, v_class_stream, p_semester, p_academic_year, 1)
    ON DUPLICATE KEY UPDATE
        is_active = 1,
        updated_at = CURRENT_TIMESTAMP;
    
    COMMIT;
END$$

-- 3. Create conflict detection function for timetable
DROP FUNCTION IF EXISTS `check_timetable_conflicts`$$

CREATE FUNCTION `check_timetable_conflicts`(
    p_class_course_id INT,
    p_lecturer_course_id INT,
    p_day_id INT,
    p_time_slot_id INT,
    p_room_id INT,
    p_division_label VARCHAR(50)
) RETURNS JSON
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE conflicts JSON DEFAULT JSON_ARRAY();
    DECLARE lecturer_conflict INT DEFAULT 0;
    DECLARE room_conflict INT DEFAULT 0;
    DECLARE class_conflict INT DEFAULT 0;
    DECLARE lecturer_id INT;
    DECLARE class_id INT;
    
    -- Get lecturer and class IDs
    SELECT lc.lecturer_id INTO lecturer_id 
    FROM lecturer_courses lc WHERE lc.id = p_lecturer_course_id;
    
    SELECT cc.class_id INTO class_id 
    FROM class_courses cc WHERE cc.id = p_class_course_id;
    
    -- Check lecturer conflicts
    SELECT COUNT(*) INTO lecturer_conflict
    FROM timetable t 
    JOIN lecturer_courses lc ON t.lecturer_course_id = lc.id 
    WHERE lc.lecturer_id = lecturer_id 
    AND t.day_id = p_day_id 
    AND t.time_slot_id = p_time_slot_id;
    
    IF lecturer_conflict > 0 THEN
        SET conflicts = JSON_ARRAY_APPEND(conflicts, '$', 'lecturer_conflict');
    END IF;
    
    -- Check room conflicts
    SELECT COUNT(*) INTO room_conflict
    FROM timetable 
    WHERE room_id = p_room_id 
    AND day_id = p_day_id 
    AND time_slot_id = p_time_slot_id;
    
    IF room_conflict > 0 THEN
        SET conflicts = JSON_ARRAY_APPEND(conflicts, '$', 'room_conflict');
    END IF;
    
    -- Check class conflicts (considering divisions)
    IF p_division_label IS NOT NULL THEN
        SELECT COUNT(*) INTO class_conflict
        FROM timetable t 
        JOIN class_courses cc ON t.class_course_id = cc.id 
        WHERE cc.class_id = class_id 
        AND t.division_label = p_division_label
        AND t.day_id = p_day_id 
        AND t.time_slot_id = p_time_slot_id;
    ELSE
        SELECT COUNT(*) INTO class_conflict
        FROM timetable t 
        JOIN class_courses cc ON t.class_course_id = cc.id 
        WHERE cc.class_id = class_id 
        AND t.day_id = p_day_id 
        AND t.time_slot_id = p_time_slot_id;
    END IF;
    
    IF class_conflict > 0 THEN
        SET conflicts = JSON_ARRAY_APPEND(conflicts, '$', 'class_conflict');
    END IF;
    
    RETURN conflicts;
END$$

DELIMITER ;

-- 4. Add constraints to prevent invalid assignments
-- Note: We'll add these as triggers since MySQL doesn't support subqueries in CHECK constraints

DELIMITER $$

DROP TRIGGER IF EXISTS `validate_class_courses_stream`$$

CREATE TRIGGER `validate_class_courses_stream`
BEFORE INSERT ON `class_courses`
FOR EACH ROW
BEGIN
    DECLARE class_stream_id INT;
    DECLARE course_stream_id INT;
    
    -- Get stream IDs
    SELECT stream_id INTO class_stream_id FROM classes WHERE id = NEW.class_id AND is_active = 1;
    SELECT stream_id INTO course_stream_id FROM courses WHERE id = NEW.course_id AND is_active = 1;
    
    -- Validate class exists and is active
    IF class_stream_id IS NULL THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Invalid or inactive class ID';
    END IF;
    
    -- Validate course exists and is active
    IF course_stream_id IS NULL THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Invalid or inactive course ID';
    END IF;
    
    -- Validate stream consistency
    IF class_stream_id != course_stream_id THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Cannot assign course from different stream to class';
    END IF;
    
    -- Set the stream_id automatically
    SET NEW.stream_id = class_stream_id;
    
    -- Set default semester and academic year if not provided
    IF NEW.semester IS NULL OR NEW.semester = '' THEN
        SET NEW.semester = 'current';
    END IF;
    
    IF NEW.academic_year IS NULL OR NEW.academic_year = '' THEN
        SET NEW.academic_year = '2024/2025';
    END IF;
END$$

DROP TRIGGER IF EXISTS `validate_class_courses_stream_update`$$

CREATE TRIGGER `validate_class_courses_stream_update`
BEFORE UPDATE ON `class_courses`
FOR EACH ROW
BEGIN
    DECLARE class_stream_id INT;
    DECLARE course_stream_id INT;
    
    -- Only validate if class_id or course_id is being changed
    IF NEW.class_id != OLD.class_id OR NEW.course_id != OLD.course_id THEN
        -- Get stream IDs
        SELECT stream_id INTO class_stream_id FROM classes WHERE id = NEW.class_id AND is_active = 1;
        SELECT stream_id INTO course_stream_id FROM courses WHERE id = NEW.course_id AND is_active = 1;
        
        -- Validate class exists and is active
        IF class_stream_id IS NULL THEN
            SIGNAL SQLSTATE '45000' 
            SET MESSAGE_TEXT = 'Invalid or inactive class ID';
        END IF;
        
        -- Validate course exists and is active
        IF course_stream_id IS NULL THEN
            SIGNAL SQLSTATE '45000' 
            SET MESSAGE_TEXT = 'Invalid or inactive course ID';
        END IF;
        
        -- Validate stream consistency
        IF class_stream_id != course_stream_id THEN
            SIGNAL SQLSTATE '45000' 
            SET MESSAGE_TEXT = 'Cannot assign course from different stream to class';
        END IF;
        
        -- Update the stream_id automatically
        SET NEW.stream_id = class_stream_id;
    END IF;
END$$

DELIMITER ;

-- 5. Create unique constraint for class_courses with semester/year
ALTER TABLE `class_courses` 
DROP INDEX IF EXISTS `uq_class_course`,
ADD UNIQUE KEY `uq_class_course_semester` (`class_id`, `course_id`, `semester`, `academic_year`);

-- 6. Update timetable table for better conflict detection
ALTER TABLE `timetable`
ADD COLUMN IF NOT EXISTS `stream_id` INT NOT NULL DEFAULT 1 AFTER `room_id`;

-- Add foreign key for timetable.stream_id
ALTER TABLE `timetable` 
ADD CONSTRAINT IF NOT EXISTS `fk_timetable_stream` 
FOREIGN KEY (`stream_id`) REFERENCES `streams`(`id`) ON DELETE CASCADE;

-- Update timetable.stream_id based on class_courses.stream_id
UPDATE `timetable` t 
JOIN `class_courses` cc ON t.class_course_id = cc.id 
SET t.stream_id = cc.stream_id 
WHERE t.stream_id != cc.stream_id OR t.stream_id IS NULL;

-- 7. Create comprehensive unique constraint for timetable
ALTER TABLE `timetable`
DROP INDEX IF EXISTS `uq_tt_slot`,
ADD UNIQUE KEY `uq_timetable_slot_stream` (`day_id`, `time_slot_id`, `room_id`, `stream_id`),
ADD UNIQUE KEY `uq_class_time_division` (`class_course_id`, `day_id`, `time_slot_id`, `division_label`);

-- 8. Create audit table for tracking stream changes
CREATE TABLE IF NOT EXISTS `stream_audit` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `table_name` VARCHAR(50) NOT NULL,
    `record_id` INT NOT NULL,
    `old_stream_id` INT,
    `new_stream_id` INT NOT NULL,
    `changed_by` VARCHAR(100),
    `change_reason` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    INDEX `idx_audit_table_record` (`table_name`, `record_id`),
    INDEX `idx_audit_stream` (`new_stream_id`),
    INDEX `idx_audit_date` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 9. Update default values for streams
UPDATE `streams` SET 
    `period_start` = '08:00:00',
    `period_end` = '17:00:00',
    `break_start` = '12:00:00',
    `break_end` = '13:00:00',
    `active_days` = JSON_ARRAY('monday', 'tuesday', 'wednesday', 'thursday', 'friday'),
    `max_daily_hours` = 8,
    `max_weekly_hours` = 40
WHERE `id` = 1 AND `name` = 'Regular';

UPDATE `streams` SET 
    `period_start` = '18:00:00',
    `period_end` = '22:00:00',
    `break_start` = '20:00:00',
    `break_end` = '20:15:00',
    `active_days` = JSON_ARRAY('monday', 'tuesday', 'wednesday', 'thursday', 'friday'),
    `max_daily_hours` = 4,
    `max_weekly_hours` = 20
WHERE `id` = 3 AND `name` = 'Evening';

UPDATE `streams` SET 
    `period_start` = '09:00:00',
    `period_end` = '17:00:00',
    `break_start` = '12:00:00',
    `break_end` = '13:00:00',
    `active_days` = JSON_ARRAY('saturday', 'sunday'),
    `max_daily_hours` = 8,
    `max_weekly_hours` = 16
WHERE `id` = 2 AND `name` = 'Weekend';
