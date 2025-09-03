-- CORRECTED TIMETABLE SYSTEM SCHEMA
-- Only CLASSES are stream-specific, everything else is GLOBAL
-- This reflects the actual business logic where streams only affect class scheduling

CREATE DATABASE IF NOT EXISTS `timetable_system` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
USE `timetable_system`;

-- ============================================================================
-- GLOBAL TABLES (Shared across all streams)
-- ============================================================================

-- 1. DEPARTMENTS (Global - all streams share departments)
CREATE TABLE `departments` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `code` VARCHAR(20) NOT NULL UNIQUE,
    `short_name` VARCHAR(10) DEFAULT NULL,
    `head_of_department` VARCHAR(100) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    INDEX `idx_dept_active` (`is_active`),
    INDEX `idx_dept_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 2. PROGRAMS (Global - all streams share programs)
CREATE TABLE `programs` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `code` VARCHAR(20) NOT NULL UNIQUE,
    `department_id` INT NOT NULL,
    `duration_years` INT DEFAULT 4,
    `degree_type` ENUM('certificate', 'diploma', 'bachelor', 'master', 'phd') DEFAULT 'bachelor',
    `description` TEXT DEFAULT NULL,
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE,
    INDEX `idx_prog_dept` (`department_id`, `is_active`),
    INDEX `idx_prog_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 3. LEVELS (Global - standardized academic levels)
CREATE TABLE `levels` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(50) NOT NULL UNIQUE,
    `numeric_value` INT NOT NULL UNIQUE,
    `description` TEXT DEFAULT NULL,
    `is_active` BOOLEAN DEFAULT TRUE,
    
    PRIMARY KEY (`id`),
    INDEX `idx_level_numeric` (`numeric_value`),
    INDEX `idx_level_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Insert standard levels
INSERT INTO `levels` (`name`, `numeric_value`, `description`) VALUES
('Level 100', 100, 'First Year'),
('Level 200', 200, 'Second Year'),
('Level 300', 300, 'Third Year'),
('Level 400', 400, 'Fourth Year'),
('Level 500', 500, 'Fifth Year (Graduate)'),
('Level 600', 600, 'Sixth Year (Graduate)');

-- 4. COURSES (Global - all streams share courses)
CREATE TABLE `courses` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `course_code` VARCHAR(20) NOT NULL UNIQUE,
    `course_name` VARCHAR(200) NOT NULL,
    `department_id` INT NOT NULL,
    `level_id` INT NOT NULL,
    `credits` INT NOT NULL DEFAULT 3,
    `hours_per_week` INT NOT NULL DEFAULT 3,
    `course_type` ENUM('core', 'elective', 'practical', 'project') DEFAULT 'core',
    `prerequisites` JSON DEFAULT NULL, -- Array of course IDs that must be completed first
    `preferred_room_type` ENUM('classroom', 'lecture_hall', 'laboratory', 'computer_lab', 'seminar_room', 'auditorium') DEFAULT 'classroom',
    `description` TEXT DEFAULT NULL,
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`level_id`) REFERENCES `levels`(`id`) ON DELETE CASCADE,
    
    INDEX `idx_course_dept_level` (`department_id`, `level_id`, `is_active`),
    INDEX `idx_course_code` (`course_code`),
    INDEX `idx_course_active` (`is_active`),
    INDEX `idx_course_type` (`course_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 5. LECTURERS (Global - all streams share lecturers)
CREATE TABLE `lecturers` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `staff_id` VARCHAR(20) UNIQUE DEFAULT NULL,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) UNIQUE DEFAULT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `department_id` INT NOT NULL,
    `rank` ENUM('professor', 'associate_professor', 'assistant_professor', 'lecturer', 'assistant_lecturer', 'teaching_assistant') DEFAULT 'lecturer',
    `specialization` TEXT DEFAULT NULL,
    `max_hours_per_week` INT DEFAULT 20,
    `preferred_time_slots` JSON DEFAULT NULL, -- Array of preferred time slot IDs
    `unavailable_days` JSON DEFAULT NULL, -- Array of day names lecturer is unavailable
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE,
    
    INDEX `idx_lecturer_dept` (`department_id`, `is_active`),
    INDEX `idx_lecturer_active` (`is_active`),
    INDEX `idx_lecturer_rank` (`rank`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 6. ROOMS (Global - all streams share rooms)
CREATE TABLE `rooms` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `building_name` VARCHAR(100) DEFAULT NULL,
    `floor_number` INT DEFAULT NULL,
    `room_type` ENUM('classroom', 'lecture_hall', 'laboratory', 'computer_lab', 'seminar_room', 'auditorium', 'workshop') DEFAULT 'classroom',
    `capacity` INT NOT NULL DEFAULT 30,
    `equipment` JSON DEFAULT NULL, -- Array of available equipment
    `accessibility_features` JSON DEFAULT NULL, -- Array of accessibility features
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    INDEX `idx_room_type_capacity` (`room_type`, `capacity`),
    INDEX `idx_room_building` (`building_name`),
    INDEX `idx_room_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 7. DAYS (Global - standardized days)
CREATE TABLE `days` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(20) NOT NULL UNIQUE,
    `short_name` VARCHAR(3) NOT NULL UNIQUE,
    `sort_order` INT NOT NULL UNIQUE,
    `is_active` BOOLEAN DEFAULT TRUE,
    
    PRIMARY KEY (`id`),
    INDEX `idx_day_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Insert standard days
INSERT INTO `days` (`name`, `short_name`, `sort_order`) VALUES
('Monday', 'Mon', 1),
('Tuesday', 'Tue', 2),
('Wednesday', 'Wed', 3),
('Thursday', 'Thu', 4),
('Friday', 'Fri', 5),
('Saturday', 'Sat', 6),
('Sunday', 'Sun', 7);

-- 8. TIME_SLOTS (Global - but streams will filter which ones they use)
CREATE TABLE `time_slots` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `duration_minutes` INT NOT NULL,
    `slot_name` VARCHAR(50) DEFAULT NULL, -- e.g., "Morning Session 1"
    `is_break_time` BOOLEAN DEFAULT FALSE,
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_time_range` (`start_time`, `end_time`),
    INDEX `idx_timeslot_active` (`is_active`),
    INDEX `idx_timeslot_duration` (`duration_minutes`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Insert standard time slots (hourly from 7 AM to 8 PM)
INSERT INTO `time_slots` (`start_time`, `end_time`, `duration_minutes`, `slot_name`) VALUES
('07:00:00', '08:00:00', 60, 'Period 1'),
('08:00:00', '09:00:00', 60, 'Period 2'),
('09:00:00', '10:00:00', 60, 'Period 3'),
('10:00:00', '11:00:00', 60, 'Period 4'),
('11:00:00', '12:00:00', 60, 'Period 5'),
('12:00:00', '13:00:00', 60, 'Lunch Break'),
('13:00:00', '14:00:00', 60, 'Period 6'),
('14:00:00', '15:00:00', 60, 'Period 7'),
('15:00:00', '16:00:00', 60, 'Period 8'),
('16:00:00', '17:00:00', 60, 'Period 9'),
('17:00:00', '18:00:00', 60, 'Period 10'),
('18:00:00', '19:00:00', 60, 'Evening Period 1'),
('19:00:00', '20:00:00', 60, 'Evening Period 2');

-- Update lunch break
UPDATE `time_slots` SET `is_break_time` = TRUE WHERE `start_time` = '12:00:00';

-- ============================================================================
-- STREAM-SPECIFIC TABLES
-- ============================================================================

-- 9. STREAMS (Defines different scheduling streams)
CREATE TABLE `streams` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(50) NOT NULL UNIQUE,
    `code` VARCHAR(20) NOT NULL UNIQUE,
    `description` TEXT DEFAULT NULL,
    
    -- Time period settings for this stream
    `period_start` TIME NOT NULL DEFAULT '08:00:00',
    `period_end` TIME NOT NULL DEFAULT '17:00:00',
    `break_start` TIME DEFAULT '12:00:00',
    `break_end` TIME DEFAULT '13:00:00',
    
    -- Active days for this stream (JSON array of day names)
    `active_days` JSON NOT NULL DEFAULT ('["monday", "tuesday", "wednesday", "thursday", "friday"]'),
    
    -- Stream constraints
    `max_daily_hours` INT DEFAULT 8,
    `max_weekly_hours` INT DEFAULT 40,
    `max_concurrent_classes` INT DEFAULT 10,
    
    -- Status
    `is_active` BOOLEAN DEFAULT TRUE,
    `is_current` BOOLEAN DEFAULT FALSE, -- Only one stream can be current at a time
    
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    INDEX `idx_stream_active` (`is_active`),
    INDEX `idx_stream_current` (`is_current`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Insert default streams
INSERT INTO `streams` (`name`, `code`, `description`, `period_start`, `period_end`, `break_start`, `break_end`, `active_days`, `is_current`) VALUES
('Regular', 'REG', 'Regular weekday classes', '08:00:00', '17:00:00', '12:00:00', '13:00:00', '["monday", "tuesday", "wednesday", "thursday", "friday"]', TRUE),
('Weekend', 'WKD', 'Weekend classes', '09:00:00', '17:00:00', '12:00:00', '13:00:00', '["saturday", "sunday"]', FALSE),
('Evening', 'EVE', 'Evening classes', '18:00:00', '22:00:00', '20:00:00', '20:15:00', '["monday", "tuesday", "wednesday", "thursday", "friday"]', FALSE);

-- 10. STREAM_TIME_SLOTS (Maps which time slots each stream uses)
CREATE TABLE `stream_time_slots` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `stream_id` INT NOT NULL,
    `time_slot_id` INT NOT NULL,
    `is_active` BOOLEAN DEFAULT TRUE,
    
    PRIMARY KEY (`id`),
    FOREIGN KEY (`stream_id`) REFERENCES `streams`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`time_slot_id`) REFERENCES `time_slots`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_stream_timeslot` (`stream_id`, `time_slot_id`),
    INDEX `idx_stream_slots_active` (`stream_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 11. STREAM_DAYS (Maps which days each stream uses)
CREATE TABLE `stream_days` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `stream_id` INT NOT NULL,
    `day_id` INT NOT NULL,
    `is_active` BOOLEAN DEFAULT TRUE,
    
    PRIMARY KEY (`id`),
    FOREIGN KEY (`stream_id`) REFERENCES `streams`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`day_id`) REFERENCES `days`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_stream_day` (`stream_id`, `day_id`),
    INDEX `idx_stream_days_active` (`stream_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 12. CLASSES (ONLY table that is stream-specific)
CREATE TABLE `classes` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `class_code` VARCHAR(20) UNIQUE DEFAULT NULL,
    `department_id` INT NOT NULL,
    `program_id` INT NOT NULL,
    `level_id` INT NOT NULL,
    `stream_id` INT NOT NULL, -- THIS IS THE ONLY STREAM-SPECIFIC FIELD
    
    -- Class capacity and enrollment
    `capacity` INT NOT NULL DEFAULT 30,
    `current_enrollment` INT DEFAULT 0,
    
    -- Academic details
    `academic_year` VARCHAR(10) NOT NULL DEFAULT '2024/2025',
    `semester` ENUM('first', 'second', 'summer') NOT NULL DEFAULT 'first',
    
    -- Scheduling preferences
    `preferred_start_time` TIME DEFAULT '08:00:00',
    `preferred_end_time` TIME DEFAULT '17:00:00',
    `max_daily_courses` INT DEFAULT 4,
    `max_weekly_hours` INT DEFAULT 25,
    
    -- Division support for large classes
    `divisions_count` INT DEFAULT 1,
    `division_capacity` INT DEFAULT 30,
    
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`program_id`) REFERENCES `programs`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`level_id`) REFERENCES `levels`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`stream_id`) REFERENCES `streams`(`id`) ON DELETE CASCADE,
    
    -- Unique constraint: same class name cannot exist in same stream/semester/year
    UNIQUE KEY `unique_class_stream_semester` (`name`, `stream_id`, `semester`, `academic_year`),
    
    -- Performance indexes
    INDEX `idx_class_stream_active` (`stream_id`, `is_active`),
    INDEX `idx_class_dept_prog_level` (`department_id`, `program_id`, `level_id`),
    INDEX `idx_class_academic` (`academic_year`, `semester`),
    
    -- Validation constraints
    CHECK (`capacity` > 0),
    CHECK (`current_enrollment` >= 0),
    CHECK (`current_enrollment` <= `capacity`),
    CHECK (`divisions_count` > 0),
    CHECK (`division_capacity` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ============================================================================
-- RELATIONSHIP TABLES (Connect global entities)
-- ============================================================================

-- 13. LECTURER_COURSES (Global - lecturers can teach courses across streams)
CREATE TABLE `lecturer_courses` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `lecturer_id` INT NOT NULL,
    `course_id` INT NOT NULL,
    `is_primary` BOOLEAN DEFAULT FALSE, -- Is this the primary lecturer for this course?
    `max_classes_per_week` INT DEFAULT 5, -- Max classes this lecturer can teach for this course per week
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    FOREIGN KEY (`lecturer_id`) REFERENCES `lecturers`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE,
    
    UNIQUE KEY `unique_lecturer_course` (`lecturer_id`, `course_id`),
    INDEX `idx_lc_active` (`is_active`),
    INDEX `idx_lc_primary` (`is_primary`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 14. COURSE_ROOM_TYPES (Maps courses to suitable room types)
CREATE TABLE `course_room_types` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `course_id` INT NOT NULL,
    `room_type` ENUM('classroom', 'lecture_hall', 'laboratory', 'computer_lab', 'seminar_room', 'auditorium', 'workshop') NOT NULL,
    `priority` INT DEFAULT 1, -- 1 = highest priority, 5 = lowest
    `is_required` BOOLEAN DEFAULT FALSE, -- Must this course use this room type?
    
    PRIMARY KEY (`id`),
    FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE,
    
    UNIQUE KEY `unique_course_room_type` (`course_id`, `room_type`),
    INDEX `idx_crt_priority` (`course_id`, `priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 15. CLASS_COURSES (Department-oriented assignments with professional validation)
CREATE TABLE `class_courses` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `class_id` INT NOT NULL,
    `course_id` INT NOT NULL,
    
    -- Academic context
    `semester` ENUM('first', 'second', 'summer') NOT NULL DEFAULT 'first',
    `academic_year` VARCHAR(10) NOT NULL DEFAULT '2024/2025',
    
    -- Assignment metadata
    `assigned_by` VARCHAR(100) DEFAULT NULL, -- Who made this assignment
    `assignment_reason` TEXT DEFAULT NULL, -- Why was this course assigned to this class
    `is_mandatory` BOOLEAN DEFAULT TRUE, -- Is this course mandatory for this class?
    
    -- Status
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE,
    
    -- Business rules enforced by unique constraints
    UNIQUE KEY `unique_class_course_semester` (`class_id`, `course_id`, `semester`, `academic_year`),
    
    -- Performance indexes
    INDEX `idx_cc_class_active` (`class_id`, `is_active`),
    INDEX `idx_cc_course_active` (`course_id`, `is_active`),
    INDEX `idx_cc_academic` (`academic_year`, `semester`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ============================================================================
-- TIMETABLE TABLES
-- ============================================================================

-- 16. TIMETABLE (The main scheduling table)
CREATE TABLE `timetable` (
    `id` INT NOT NULL AUTO_INCREMENT,
    
    -- Core relationships
    `class_course_id` INT NOT NULL,
    `lecturer_course_id` INT NOT NULL,
    `day_id` INT NOT NULL,
    `time_slot_id` INT NOT NULL,
    `room_id` INT NOT NULL,
    
    -- Academic context
    `semester` ENUM('first', 'second', 'summer') NOT NULL DEFAULT 'first',
    `academic_year` VARCHAR(10) NOT NULL DEFAULT '2024/2025',
    
    -- Division support for large classes
    `division_label` VARCHAR(10) DEFAULT NULL, -- e.g., 'A', 'B', 'C' for class divisions
    
    -- Metadata
    `notes` TEXT DEFAULT NULL,
    `created_by` VARCHAR(100) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    FOREIGN KEY (`class_course_id`) REFERENCES `class_courses`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`lecturer_course_id`) REFERENCES `lecturer_courses`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`day_id`) REFERENCES `days`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`time_slot_id`) REFERENCES `time_slots`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`room_id`) REFERENCES `rooms`(`id`) ON DELETE CASCADE,
    
    -- Prevent conflicts: same room cannot be used at same time
    UNIQUE KEY `unique_room_time_slot` (`room_id`, `day_id`, `time_slot_id`, `semester`, `academic_year`),
    
    -- Prevent conflicts: same lecturer cannot teach at same time
    UNIQUE KEY `unique_lecturer_time_slot` (`lecturer_course_id`, `day_id`, `time_slot_id`, `semester`, `academic_year`),
    
    -- Prevent conflicts: same class cannot have multiple courses at same time (considering divisions)
    UNIQUE KEY `unique_class_time_division` (`class_course_id`, `day_id`, `time_slot_id`, `division_label`, `semester`, `academic_year`),
    
    -- Performance indexes
    INDEX `idx_timetable_academic` (`academic_year`, `semester`),
    INDEX `idx_timetable_day_time` (`day_id`, `time_slot_id`),
    INDEX `idx_timetable_class_course` (`class_course_id`),
    INDEX `idx_timetable_lecturer_course` (`lecturer_course_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ============================================================================
-- VALIDATION AND BUSINESS RULES
-- ============================================================================

-- 17. Create validation functions and triggers
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
    DECLARE v_class_dept INT;
    DECLARE v_class_level INT;
    DECLARE v_class_program INT;
    DECLARE v_course_dept INT;
    DECLARE v_course_level INT;
    DECLARE v_issues JSON DEFAULT JSON_ARRAY();
    
    -- Get class details
    SELECT c.department_id, c.level_id, c.program_id
    INTO v_class_dept, v_class_level, v_class_program
    FROM classes c WHERE c.id = p_class_id AND c.is_active = 1;
    
    -- Get course details
    SELECT co.department_id, co.level_id
    INTO v_course_dept, v_course_level
    FROM courses co WHERE co.id = p_course_id AND co.is_active = 1;
    
    -- Validation 1: Both class and course must exist
    IF v_class_dept IS NULL THEN
        SET v_issues = JSON_ARRAY_APPEND(v_issues, '$', 'Class not found or inactive');
    END IF;
    
    IF v_course_dept IS NULL THEN
        SET v_issues = JSON_ARRAY_APPEND(v_issues, '$', 'Course not found or inactive');
    END IF;
    
    -- Validation 2: Department consistency (preferred but not strict)
    IF v_class_dept != v_course_dept THEN
        -- Check if this is a cross-departmental course (allowed for some courses)
        SET v_issues = JSON_ARRAY_APPEND(v_issues, '$', 
            CONCAT('Cross-departmental assignment: Class from dept ', v_class_dept, ', Course from dept ', v_course_dept));
    END IF;
    
    -- Validation 3: Level consistency (strict)
    IF v_class_level != v_course_level THEN
        SET v_issues = JSON_ARRAY_APPEND(v_issues, '$', 
            CONCAT('Level mismatch: Class level ', v_class_level, ', Course level ', v_course_level));
    END IF;
    
    -- Validation 4: Check prerequisites (if any)
    IF EXISTS (
        SELECT 1 FROM courses 
        WHERE id = p_course_id 
        AND prerequisites IS NOT NULL 
        AND JSON_LENGTH(prerequisites) > 0
    ) THEN
        -- This is a simplified check - in practice, you'd verify student completion
        SET v_issues = JSON_ARRAY_APPEND(v_issues, '$', 'Course has prerequisites that should be verified');
    END IF;
    
    RETURN v_issues;
END$$

-- Trigger to validate class-course assignments
DROP TRIGGER IF EXISTS `validate_class_course_assignment_trigger`$$
CREATE TRIGGER `validate_class_course_assignment_trigger`
BEFORE INSERT ON `class_courses`
FOR EACH ROW
BEGIN
    DECLARE v_validation_result JSON;
    DECLARE v_error_count INT;
    
    SET v_validation_result = validate_class_course_assignment(NEW.class_id, NEW.course_id);
    SET v_error_count = JSON_LENGTH(v_validation_result);
    
    -- Allow warnings but block critical errors
    IF v_error_count > 0 THEN
        -- Check for critical errors (level mismatch, non-existent records)
        IF JSON_SEARCH(v_validation_result, 'one', '%not found%') IS NOT NULL 
           OR JSON_SEARCH(v_validation_result, 'one', '%Level mismatch%') IS NOT NULL THEN
            SIGNAL SQLSTATE '45000' 
            SET MESSAGE_TEXT = CONCAT('Assignment validation failed: ', CAST(v_validation_result AS CHAR));
        END IF;
    END IF;
END$$

-- Function to get available courses for a class (department-oriented)
DROP FUNCTION IF EXISTS `get_available_courses_for_class`$$
CREATE FUNCTION `get_available_courses_for_class`(p_class_id INT)
RETURNS JSON
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE v_class_dept INT;
    DECLARE v_class_level INT;
    DECLARE v_class_program INT;
    DECLARE v_result JSON DEFAULT JSON_ARRAY();
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_course_id INT;
    DECLARE v_course_code VARCHAR(20);
    DECLARE v_course_name VARCHAR(200);
    DECLARE v_is_suitable BOOLEAN;
    
    DECLARE course_cursor CURSOR FOR
        SELECT co.id, co.course_code, co.course_name,
               (co.department_id = v_class_dept OR co.course_type = 'elective') as is_suitable
        FROM courses co
        WHERE co.level_id = v_class_level 
        AND co.is_active = 1
        AND co.id NOT IN (
            SELECT cc.course_id 
            FROM class_courses cc 
            WHERE cc.class_id = p_class_id AND cc.is_active = 1
        )
        ORDER BY co.department_id = v_class_dept DESC, co.course_code;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    -- Get class details
    SELECT c.department_id, c.level_id, c.program_id
    INTO v_class_dept, v_class_level, v_class_program
    FROM classes c WHERE c.id = p_class_id AND c.is_active = 1;
    
    IF v_class_dept IS NULL THEN
        RETURN JSON_ARRAY();
    END IF;
    
    OPEN course_cursor;
    
    read_loop: LOOP
        FETCH course_cursor INTO v_course_id, v_course_code, v_course_name, v_is_suitable;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        SET v_result = JSON_ARRAY_APPEND(v_result, '$', JSON_OBJECT(
            'id', v_course_id,
            'code', v_course_code,
            'name', v_course_name,
            'is_recommended', v_is_suitable
        ));
    END LOOP;
    
    CLOSE course_cursor;
    RETURN v_result;
END$$

-- Trigger to ensure only one current stream
DROP TRIGGER IF EXISTS `ensure_single_current_stream`$$
CREATE TRIGGER `ensure_single_current_stream`
BEFORE UPDATE ON `streams`
FOR EACH ROW
BEGIN
    IF NEW.is_current = TRUE AND OLD.is_current = FALSE THEN
        -- Deactivate all other current streams
        UPDATE streams SET is_current = FALSE WHERE id != NEW.id;
    END IF;
END$$

DELIMITER ;

-- ============================================================================
-- VIEWS FOR EASY DATA ACCESS
-- ============================================================================

-- 18. Comprehensive class view
CREATE OR REPLACE VIEW `class_details` AS
SELECT 
    c.id,
    c.name as class_name,
    c.class_code,
    c.capacity,
    c.current_enrollment,
    c.academic_year,
    c.semester,
    c.divisions_count,
    c.is_active,
    
    -- Department info
    d.name as department_name,
    d.code as department_code,
    
    -- Program info  
    p.name as program_name,
    p.code as program_code,
    p.degree_type,
    
    -- Level info
    l.name as level_name,
    l.numeric_value as level_number,
    
    -- Stream info
    s.name as stream_name,
    s.code as stream_code,
    s.period_start,
    s.period_end,
    
    -- Calculated fields
    ROUND((c.current_enrollment * 100.0) / c.capacity, 1) as enrollment_percentage,
    CASE 
        WHEN c.current_enrollment >= c.capacity THEN 'Full'
        WHEN c.current_enrollment >= (c.capacity * 0.9) THEN 'Nearly Full'
        WHEN c.current_enrollment >= (c.capacity * 0.5) THEN 'Half Full'
        ELSE 'Available'
    END as enrollment_status

FROM classes c
JOIN departments d ON c.department_id = d.id
JOIN programs p ON c.program_id = p.id
JOIN levels l ON c.level_id = l.id
JOIN streams s ON c.stream_id = s.id;

-- 19. Course assignment recommendations view
CREATE OR REPLACE VIEW `course_assignment_recommendations` AS
SELECT 
    c.id as class_id,
    c.name as class_name,
    co.id as course_id,
    co.course_code,
    co.course_name,
    
    -- Recommendation score based on multiple factors
    (
        CASE WHEN c.department_id = co.department_id THEN 10 ELSE 0 END +
        CASE WHEN c.level_id = co.level_id THEN 10 ELSE -5 END +
        CASE WHEN co.course_type = 'core' THEN 5 ELSE 2 END +
        CASE WHEN co.course_type = 'elective' THEN 3 ELSE 0 END
    ) as recommendation_score,
    
    -- Recommendation reasons
    CONCAT_WS('; ',
        CASE WHEN c.department_id = co.department_id THEN 'Same department' ELSE 'Cross-department' END,
        CASE WHEN c.level_id = co.level_id THEN 'Appropriate level' ELSE 'Level mismatch' END,
        CASE WHEN co.course_type = 'core' THEN 'Core course' ELSE CONCAT(co.course_type, ' course') END
    ) as recommendation_reason,
    
    -- Assignment status
    CASE 
        WHEN EXISTS (SELECT 1 FROM class_courses cc WHERE cc.class_id = c.id AND cc.course_id = co.id AND cc.is_active = 1) 
        THEN 'Assigned'
        ELSE 'Available'
    END as assignment_status

FROM classes c
CROSS JOIN courses co
WHERE c.is_active = 1 AND co.is_active = 1
ORDER BY c.id, recommendation_score DESC, co.course_code;

-- 20. Timetable summary view
CREATE OR REPLACE VIEW `timetable_summary` AS
SELECT 
    t.id,
    
    -- Class information
    cd.class_name,
    cd.department_name,
    cd.program_name,
    cd.level_name,
    cd.stream_name,
    
    -- Course information
    co.course_code,
    co.course_name,
    co.credits,
    co.course_type,
    
    -- Lecturer information
    l.name as lecturer_name,
    l.rank as lecturer_rank,
    
    -- Schedule information
    d.name as day_name,
    d.short_name as day_short,
    ts.start_time,
    ts.end_time,
    ts.slot_name,
    
    -- Room information
    r.name as room_name,
    r.building_name,
    r.room_type,
    r.capacity as room_capacity,
    
    -- Academic context
    t.semester,
    t.academic_year,
    t.division_label,
    
    -- Status indicators
    CASE WHEN cd.current_enrollment > r.capacity THEN 'Overcapacity' ELSE 'OK' END as capacity_status

FROM timetable t
JOIN class_courses cc ON t.class_course_id = cc.id
JOIN class_details cd ON cc.class_id = cd.id
JOIN courses co ON cc.course_id = co.id
JOIN lecturer_courses lc ON t.lecturer_course_id = lc.id
JOIN lecturers l ON lc.lecturer_id = l.id
JOIN days d ON t.day_id = d.id
JOIN time_slots ts ON t.time_slot_id = ts.id
JOIN rooms r ON t.room_id = r.id;

-- ============================================================================
-- STORED PROCEDURES FOR PROFESSIONAL OPERATIONS
-- ============================================================================

DELIMITER $$

-- Professional class-course assignment procedure
DROP PROCEDURE IF EXISTS `assign_course_to_class_professional`$$
CREATE PROCEDURE `assign_course_to_class_professional`(
    IN p_class_id INT,
    IN p_course_id INT,
    IN p_semester VARCHAR(10),
    IN p_academic_year VARCHAR(10),
    IN p_assigned_by VARCHAR(100),
    IN p_assignment_reason TEXT,
    OUT p_result VARCHAR(100),
    OUT p_warnings JSON
)
BEGIN
    DECLARE v_validation_result JSON;
    DECLARE v_warning_count INT;
    DECLARE v_error_count INT;
    DECLARE v_class_dept INT;
    DECLARE v_course_dept INT;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_result = 'ERROR: Database error occurred';
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Validate the assignment
    SET v_validation_result = validate_class_course_assignment(p_class_id, p_course_id);
    
    -- Count critical errors vs warnings
    SET v_error_count = 0;
    SET v_warning_count = 0;
    
    -- Check for critical errors
    IF JSON_SEARCH(v_validation_result, 'one', '%not found%') IS NOT NULL 
       OR JSON_SEARCH(v_validation_result, 'one', '%Level mismatch%') IS NOT NULL THEN
        SET v_error_count = 1;
        SET p_result = CONCAT('VALIDATION_ERROR: ', CAST(v_validation_result AS CHAR));
        ROLLBACK;
    ELSE
        -- Check for warnings
        IF JSON_LENGTH(v_validation_result) > 0 THEN
            SET v_warning_count = JSON_LENGTH(v_validation_result);
            SET p_warnings = v_validation_result;
        END IF;
        
        -- Proceed with assignment
        INSERT INTO class_courses (
            class_id, course_id, semester, academic_year, 
            assigned_by, assignment_reason, is_active
        ) VALUES (
            p_class_id, p_course_id, p_semester, p_academic_year,
            p_assigned_by, p_assignment_reason, 1
        ) ON DUPLICATE KEY UPDATE
            is_active = 1,
            assigned_by = VALUES(assigned_by),
            assignment_reason = VALUES(assignment_reason),
            updated_at = CURRENT_TIMESTAMP;
        
        IF v_warning_count > 0 THEN
            SET p_result = CONCAT('SUCCESS_WITH_WARNINGS: Assignment completed with ', v_warning_count, ' warnings');
        ELSE
            SET p_result = 'SUCCESS: Assignment completed successfully';
        END IF;
        
        COMMIT;
    END IF;
END$$

-- Procedure to get stream-appropriate time slots
DROP PROCEDURE IF EXISTS `get_stream_time_slots`$$
CREATE PROCEDURE `get_stream_time_slots`(
    IN p_stream_id INT
)
BEGIN
    DECLARE v_period_start TIME;
    DECLARE v_period_end TIME;
    DECLARE v_break_start TIME;
    DECLARE v_break_end TIME;
    
    -- Get stream settings
    SELECT period_start, period_end, break_start, break_end
    INTO v_period_start, v_period_end, v_break_start, v_break_end
    FROM streams WHERE id = p_stream_id AND is_active = 1;
    
    -- Return appropriate time slots for this stream
    SELECT ts.id, ts.start_time, ts.end_time, ts.duration_minutes, ts.slot_name,
           CASE 
               WHEN ts.start_time >= v_break_start AND ts.end_time <= v_break_end THEN TRUE
               ELSE FALSE
           END as is_break_time,
           CASE
               WHEN ts.start_time >= v_period_start AND ts.end_time <= v_period_end THEN TRUE
               ELSE FALSE
           END as is_in_period
    FROM time_slots ts
    WHERE ts.is_active = 1
    AND ts.start_time >= v_period_start
    AND ts.end_time <= v_period_end
    AND NOT (ts.start_time >= v_break_start AND ts.end_time <= v_break_end)
    ORDER BY ts.start_time;
END$$

-- Procedure to generate timetable for a specific stream
DROP PROCEDURE IF EXISTS `generate_stream_timetable`$$
CREATE PROCEDURE `generate_stream_timetable`(
    IN p_stream_id INT,
    IN p_semester VARCHAR(10),
    IN p_academic_year VARCHAR(10),
    OUT p_success_count INT,
    OUT p_error_count INT,
    OUT p_conflict_count INT
)
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_class_course_id INT;
    DECLARE v_lecturer_course_id INT;
    DECLARE v_placement_attempts INT DEFAULT 0;
    DECLARE v_max_attempts INT DEFAULT 50;
    
    DECLARE assignment_cursor CURSOR FOR
        SELECT cc.id as class_course_id, lc.id as lecturer_course_id
        FROM class_courses cc
        JOIN classes c ON cc.class_id = c.id
        JOIN lecturer_courses lc ON cc.course_id = lc.course_id
        WHERE c.stream_id = p_stream_id 
        AND cc.semester = p_semester 
        AND cc.academic_year = p_academic_year
        AND cc.is_active = 1
        AND lc.is_active = 1
        ORDER BY RAND(); -- Randomize to avoid bias
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    SET p_success_count = 0;
    SET p_error_count = 0;
    SET p_conflict_count = 0;
    
    -- Clear existing timetable for this stream/semester/year
    DELETE FROM timetable 
    WHERE semester = p_semester 
    AND academic_year = p_academic_year
    AND class_course_id IN (
        SELECT cc.id FROM class_courses cc 
        JOIN classes c ON cc.class_id = c.id 
        WHERE c.stream_id = p_stream_id
    );
    
    OPEN assignment_cursor;
    
    assignment_loop: LOOP
        FETCH assignment_cursor INTO v_class_course_id, v_lecturer_course_id;
        IF done THEN
            LEAVE assignment_loop;
        END IF;
        
        -- Try to place this assignment (simplified logic for stored procedure)
        -- In practice, this would call the full scheduling algorithm
        SET v_placement_attempts = v_placement_attempts + 1;
        
        -- For now, just count as success (actual logic would be implemented in PHP)
        SET p_success_count = p_success_count + 1;
        
        IF v_placement_attempts >= v_max_attempts THEN
            LEAVE assignment_loop;
        END IF;
    END LOOP;
    
    CLOSE assignment_cursor;
END$$

DELIMITER ;

-- ============================================================================
-- AUDIT AND MONITORING
-- ============================================================================

-- 21. Audit table for tracking changes
CREATE TABLE `audit_log` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `table_name` VARCHAR(50) NOT NULL,
    `record_id` INT NOT NULL,
    `action` ENUM('insert', 'update', 'delete') NOT NULL,
    `old_values` JSON DEFAULT NULL,
    `new_values` JSON DEFAULT NULL,
    `changed_by` VARCHAR(100) DEFAULT NULL,
    `change_reason` TEXT DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    INDEX `idx_audit_table_record` (`table_name`, `record_id`),
    INDEX `idx_audit_date` (`created_at`),
    INDEX `idx_audit_user` (`changed_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 22. System configuration table
CREATE TABLE `system_config` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `config_key` VARCHAR(100) NOT NULL UNIQUE,
    `config_value` TEXT NOT NULL,
    `config_type` ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
    `description` TEXT DEFAULT NULL,
    `is_system` BOOLEAN DEFAULT FALSE, -- System configs cannot be deleted
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    INDEX `idx_config_key` (`config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Insert default system configurations
INSERT INTO `system_config` (`config_key`, `config_value`, `config_type`, `description`, `is_system`) VALUES
('current_academic_year', '2024/2025', 'string', 'Current academic year', TRUE),
('current_semester', 'first', 'string', 'Current semester', TRUE),
('max_daily_hours_per_class', '8', 'integer', 'Maximum daily hours per class', FALSE),
('max_weekly_hours_per_class', '25', 'integer', 'Maximum weekly hours per class', FALSE),
('default_class_capacity', '30', 'integer', 'Default capacity for new classes', FALSE),
('enable_room_capacity_validation', 'true', 'boolean', 'Enable room capacity validation', FALSE),
('enable_prerequisite_checking', 'false', 'boolean', 'Enable prerequisite checking', FALSE),
('timetable_generation_algorithm', 'random_placement', 'string', 'Algorithm used for timetable generation', FALSE);

-- ============================================================================
-- SAMPLE DATA FOR TESTING
-- ============================================================================

-- Insert sample departments
INSERT INTO `departments` (`name`, `code`, `short_name`) VALUES
('Computer Science', 'CS', 'CS'),
('Mathematics', 'MATH', 'MATH'),
('Business Administration', 'BA', 'BA'),
('Engineering', 'ENG', 'ENG');

-- Insert sample programs
INSERT INTO `programs` (`name`, `code`, `department_id`, `degree_type`) VALUES
('Bachelor of Science in Computer Science', 'BSC_CS', 1, 'bachelor'),
('Bachelor of Science in Mathematics', 'BSC_MATH', 2, 'bachelor'),
('Bachelor of Business Administration', 'BBA', 3, 'bachelor'),
('Bachelor of Engineering', 'BENG', 4, 'bachelor');

-- Insert sample courses
INSERT INTO `courses` (`course_code`, `course_name`, `department_id`, `level_id`, `credits`, `course_type`) VALUES
('CS101', 'Introduction to Programming', 1, 1, 3, 'core'),
('CS102', 'Data Structures', 1, 1, 3, 'core'),
('MATH101', 'Calculus I', 2, 1, 4, 'core'),
('BA101', 'Principles of Management', 3, 1, 3, 'core'),
('ENG101', 'Engineering Mathematics', 4, 1, 4, 'core');

-- Insert sample lecturers
INSERT INTO `lecturers` (`staff_id`, `name`, `email`, `department_id`, `rank`) VALUES
('L001', 'Dr. John Smith', 'john.smith@university.edu', 1, 'professor'),
('L002', 'Prof. Jane Doe', 'jane.doe@university.edu', 2, 'professor'),
('L003', 'Dr. Mike Johnson', 'mike.johnson@university.edu', 3, 'associate_professor'),
('L004', 'Dr. Sarah Wilson', 'sarah.wilson@university.edu', 4, 'assistant_professor');

-- Insert sample rooms
INSERT INTO `rooms` (`name`, `building_name`, `room_type`, `capacity`) VALUES
('Room 101', 'Main Building', 'classroom', 40),
('Room 102', 'Main Building', 'classroom', 35),
('Lab 201', 'Science Building', 'computer_lab', 30),
('Hall A', 'Main Building', 'lecture_hall', 100),
('Workshop 1', 'Engineering Building', 'workshop', 25);

-- Insert sample classes (stream-specific)
INSERT INTO `classes` (`name`, `class_code`, `department_id`, `program_id`, `level_id`, `stream_id`, `capacity`, `academic_year`, `semester`) VALUES
-- Regular stream classes
('CS Level 100A', 'CS100A', 1, 1, 1, 1, 35, '2024/2025', 'first'),
('CS Level 100B', 'CS100B', 1, 1, 1, 1, 35, '2024/2025', 'first'),
('MATH Level 100A', 'MATH100A', 2, 2, 1, 1, 40, '2024/2025', 'first'),

-- Weekend stream classes  
('CS Level 100 Weekend', 'CS100W', 1, 1, 1, 2, 30, '2024/2025', 'first'),
('BA Level 100 Weekend', 'BA100W', 3, 3, 1, 2, 25, '2024/2025', 'first'),

-- Evening stream classes
('CS Level 100 Evening', 'CS100E', 1, 1, 1, 3, 25, '2024/2025', 'first');

-- Set up lecturer-course relationships
INSERT INTO `lecturer_courses` (`lecturer_id`, `course_id`, `is_primary`) VALUES
(1, 1, TRUE),  -- Dr. Smith teaches CS101
(1, 2, TRUE),  -- Dr. Smith teaches CS102
(2, 3, TRUE),  -- Prof. Doe teaches MATH101
(3, 4, TRUE),  -- Dr. Johnson teaches BA101
(4, 5, TRUE);  -- Dr. Wilson teaches ENG101

-- Set up course-room type preferences
INSERT INTO `course_room_types` (`course_id`, `room_type`, `priority`, `is_required`) VALUES
(1, 'computer_lab', 1, TRUE),   -- CS101 requires computer lab
(1, 'classroom', 2, FALSE),     -- CS101 can use classroom as backup
(2, 'computer_lab', 1, TRUE),   -- CS102 requires computer lab
(3, 'classroom', 1, FALSE),     -- MATH101 can use classroom
(3, 'lecture_hall', 2, FALSE),  -- MATH101 can use lecture hall
(4, 'classroom', 1, FALSE),     -- BA101 can use classroom
(5, 'classroom', 1, FALSE);     -- ENG101 can use classroom

-- Populate stream time slots and days
INSERT INTO `stream_time_slots` (`stream_id`, `time_slot_id`)
SELECT s.id, ts.id
FROM streams s
CROSS JOIN time_slots ts
WHERE (s.id = 1 AND ts.start_time >= '08:00:00' AND ts.end_time <= '17:00:00' AND NOT ts.is_break_time)  -- Regular
   OR (s.id = 2 AND ts.start_time >= '09:00:00' AND ts.end_time <= '17:00:00' AND NOT ts.is_break_time)  -- Weekend  
   OR (s.id = 3 AND ts.start_time >= '18:00:00' AND ts.end_time <= '20:00:00');                          -- Evening

INSERT INTO `stream_days` (`stream_id`, `day_id`)
SELECT s.id, d.id
FROM streams s
CROSS JOIN days d
WHERE (s.id = 1 AND d.sort_order BETWEEN 1 AND 5)  -- Regular: Mon-Fri
   OR (s.id = 2 AND d.sort_order BETWEEN 6 AND 7)  -- Weekend: Sat-Sun
   OR (s.id = 3 AND d.sort_order BETWEEN 1 AND 5); -- Evening: Mon-Fri