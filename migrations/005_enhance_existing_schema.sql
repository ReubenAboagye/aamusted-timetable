-- Migration 005: Enhance Your Existing Schema
-- Professional improvements while maintaining your current structure
-- Adds validation, quality scoring, and professional features

USE timetable_system;

-- ============================================================================
-- STEP 1: ENHANCE EXISTING TABLES WITH PROFESSIONAL FEATURES
-- ============================================================================

-- Enhance levels table with numeric values for proper sorting and validation
ALTER TABLE `levels` 
ADD COLUMN IF NOT EXISTS `numeric_value` INT UNIQUE DEFAULT NULL AFTER `code`,
ADD COLUMN IF NOT EXISTS `min_credits` INT DEFAULT 15 AFTER `description`,
ADD COLUMN IF NOT EXISTS `max_credits` INT DEFAULT 25 AFTER `min_credits`;

-- Update numeric values based on existing level names
UPDATE `levels` SET 
    `numeric_value` = 100,
    `min_credits` = 15,
    `max_credits` = 20
WHERE `name` LIKE '%100%' AND `numeric_value` IS NULL;

UPDATE `levels` SET 
    `numeric_value` = 200,
    `min_credits` = 18,
    `max_credits` = 23
WHERE `name` LIKE '%200%' AND `numeric_value` IS NULL;

UPDATE `levels` SET 
    `numeric_value` = 300,
    `min_credits` = 20,
    `max_credits` = 25
WHERE `name` LIKE '%300%' AND `numeric_value` IS NULL;

UPDATE `levels` SET 
    `numeric_value` = 400,
    `min_credits` = 18,
    `max_credits` = 22
WHERE `name` LIKE '%400%' AND `numeric_value` IS NULL;

-- Enhance courses table with professional academic features
ALTER TABLE `courses`
ADD COLUMN IF NOT EXISTS `level_id` INT DEFAULT NULL AFTER `department_id`,
ADD COLUMN IF NOT EXISTS `course_type` ENUM('core','elective','practical','project','seminar') DEFAULT 'core' AFTER `hours_per_week`,
ADD COLUMN IF NOT EXISTS `prerequisites` JSON DEFAULT NULL AFTER `course_type`,
ADD COLUMN IF NOT EXISTS `corequisites` JSON DEFAULT NULL AFTER `prerequisites`,
ADD COLUMN IF NOT EXISTS `max_class_size` INT DEFAULT 50 AFTER `corequisites`,
ADD COLUMN IF NOT EXISTS `description` TEXT DEFAULT NULL AFTER `max_class_size`,
ADD COLUMN IF NOT EXISTS `learning_outcomes` TEXT DEFAULT NULL AFTER `description`,
ADD COLUMN IF NOT EXISTS `assessment_methods` JSON DEFAULT NULL AFTER `learning_outcomes`;

-- Add foreign key for course level
ALTER TABLE `courses` 
ADD CONSTRAINT IF NOT EXISTS `fk_courses_level` FOREIGN KEY (`level_id`) REFERENCES `levels` (`id`) ON DELETE SET NULL;

-- Add check constraints for courses
ALTER TABLE `courses` 
ADD CONSTRAINT IF NOT EXISTS `chk_courses_credits` CHECK (`credits` > 0),
ADD CONSTRAINT IF NOT EXISTS `chk_courses_hours` CHECK (`hours_per_week` > 0),
ADD CONSTRAINT IF NOT EXISTS `chk_courses_max_size` CHECK (`max_class_size` > 0);

-- Enhance lecturers table with professional academic features
ALTER TABLE `lecturers`
ADD COLUMN IF NOT EXISTS `staff_id` VARCHAR(20) UNIQUE DEFAULT NULL AFTER `id`,
ADD COLUMN IF NOT EXISTS `title` VARCHAR(20) DEFAULT NULL AFTER `name`,
ADD COLUMN IF NOT EXISTS `email` VARCHAR(100) UNIQUE DEFAULT NULL AFTER `title`,
ADD COLUMN IF NOT EXISTS `phone` VARCHAR(20) DEFAULT NULL AFTER `email`,
ADD COLUMN IF NOT EXISTS `rank` ENUM('professor','associate_professor','assistant_professor','lecturer','assistant_lecturer','teaching_assistant') DEFAULT 'lecturer' AFTER `department_id`,
ADD COLUMN IF NOT EXISTS `specialization` TEXT DEFAULT NULL AFTER `rank`,
ADD COLUMN IF NOT EXISTS `qualifications` JSON DEFAULT NULL AFTER `specialization`,
ADD COLUMN IF NOT EXISTS `max_hours_per_week` INT DEFAULT 20 AFTER `qualifications`,
ADD COLUMN IF NOT EXISTS `max_classes_per_day` INT DEFAULT 4 AFTER `max_hours_per_week`,
ADD COLUMN IF NOT EXISTS `preferred_time_slots` JSON DEFAULT NULL AFTER `max_classes_per_day`,
ADD COLUMN IF NOT EXISTS `unavailable_days` JSON DEFAULT NULL AFTER `preferred_time_slots`,
ADD COLUMN IF NOT EXISTS `office_location` VARCHAR(100) DEFAULT NULL AFTER `unavailable_days`;

-- Add check constraints for lecturers
ALTER TABLE `lecturers` 
ADD CONSTRAINT IF NOT EXISTS `chk_lecturers_max_hours` CHECK (`max_hours_per_week` > 0),
ADD CONSTRAINT IF NOT EXISTS `chk_lecturers_max_classes` CHECK (`max_classes_per_day` > 0);

-- Enhance rooms table with professional features
ALTER TABLE `rooms`
ADD COLUMN IF NOT EXISTS `code` VARCHAR(20) UNIQUE DEFAULT NULL AFTER `name`,
ADD COLUMN IF NOT EXISTS `floor_number` INT DEFAULT NULL AFTER `building_id`,
ADD COLUMN IF NOT EXISTS `room_number` VARCHAR(20) DEFAULT NULL AFTER `floor_number`,
ADD COLUMN IF NOT EXISTS `equipment` JSON DEFAULT NULL AFTER `room_type`,
ADD COLUMN IF NOT EXISTS `accessibility_features` JSON DEFAULT NULL AFTER `equipment`,
ADD COLUMN IF NOT EXISTS `notes` TEXT DEFAULT NULL AFTER `accessibility_features`;

-- Add check constraint for rooms
ALTER TABLE `rooms` 
ADD CONSTRAINT IF NOT EXISTS `chk_rooms_capacity` CHECK (`capacity` > 0);

-- Enhance classes table with academic and professional features
ALTER TABLE `classes`
ADD COLUMN IF NOT EXISTS `academic_year` VARCHAR(9) NOT NULL DEFAULT '2024/2025' AFTER `stream_id`,
ADD COLUMN IF NOT EXISTS `semester` ENUM('first','second','summer') NOT NULL DEFAULT 'first' AFTER `academic_year`,
ADD COLUMN IF NOT EXISTS `current_enrollment` INT DEFAULT 0 AFTER `total_capacity`,
ADD COLUMN IF NOT EXISTS `division_capacity` INT DEFAULT NULL AFTER `divisions_count`,
ADD COLUMN IF NOT EXISTS `class_coordinator` VARCHAR(100) DEFAULT NULL AFTER `division_capacity`,
ADD COLUMN IF NOT EXISTS `preferred_start_time` TIME DEFAULT '08:00:00' AFTER `class_coordinator`,
ADD COLUMN IF NOT EXISTS `preferred_end_time` TIME DEFAULT '17:00:00' AFTER `preferred_start_time`,
ADD COLUMN IF NOT EXISTS `max_daily_courses` INT DEFAULT 4 AFTER `preferred_end_time`,
ADD COLUMN IF NOT EXISTS `max_weekly_hours` INT DEFAULT 25 AFTER `max_daily_courses`,
ADD COLUMN IF NOT EXISTS `special_requirements` JSON DEFAULT NULL AFTER `max_weekly_hours`;

-- Add check constraints for classes
ALTER TABLE `classes` 
ADD CONSTRAINT IF NOT EXISTS `chk_classes_capacity` CHECK (`total_capacity` > 0),
ADD CONSTRAINT IF NOT EXISTS `chk_classes_enrollment` CHECK (`current_enrollment` >= 0),
ADD CONSTRAINT IF NOT EXISTS `chk_classes_enrollment_capacity` CHECK (`current_enrollment` <= `total_capacity`),
ADD CONSTRAINT IF NOT EXISTS `chk_classes_divisions` CHECK (`divisions_count` > 0),
ADD CONSTRAINT IF NOT EXISTS `chk_classes_max_daily` CHECK (`max_daily_courses` > 0),
ADD CONSTRAINT IF NOT EXISTS `chk_classes_max_weekly` CHECK (`max_weekly_hours` > 0);

-- Update unique constraint for classes to include academic context
ALTER TABLE `classes` 
DROP INDEX IF EXISTS `uq_class_code_year_semester`,
ADD UNIQUE KEY `uq_class_code_stream_academic` (`code`, `stream_id`, `academic_year`, `semester`);

-- Enhance class_courses table with professional assignment features
ALTER TABLE `class_courses`
ADD COLUMN IF NOT EXISTS `assignment_type` ENUM('automatic','manual','recommended','smart') DEFAULT 'manual' AFTER `academic_year`,
ADD COLUMN IF NOT EXISTS `assigned_by` VARCHAR(100) DEFAULT NULL AFTER `assignment_type`,
ADD COLUMN IF NOT EXISTS `assignment_reason` TEXT DEFAULT NULL AFTER `assigned_by`,
ADD COLUMN IF NOT EXISTS `approval_status` ENUM('pending','approved','rejected') DEFAULT 'approved' AFTER `assignment_reason`,
ADD COLUMN IF NOT EXISTS `approved_by` VARCHAR(100) DEFAULT NULL AFTER `approval_status`,
ADD COLUMN IF NOT EXISTS `approval_date` TIMESTAMP NULL DEFAULT NULL AFTER `approved_by`,
ADD COLUMN IF NOT EXISTS `quality_score` INT DEFAULT NULL AFTER `approval_date`,
ADD COLUMN IF NOT EXISTS `validation_notes` JSON DEFAULT NULL AFTER `quality_score`,
ADD COLUMN IF NOT EXISTS `is_mandatory` TINYINT(1) DEFAULT 1 AFTER `validation_notes`;

-- Add check constraint for quality score
ALTER TABLE `class_courses` 
ADD CONSTRAINT IF NOT EXISTS `chk_cc_quality_score` CHECK (`quality_score` IS NULL OR (`quality_score` >= 0 AND `quality_score` <= 50));

-- Add indexes for performance
CREATE INDEX IF NOT EXISTS `idx_cc_approval` ON `class_courses`(`approval_status`);
CREATE INDEX IF NOT EXISTS `idx_cc_quality` ON `class_courses`(`quality_score`);
CREATE INDEX IF NOT EXISTS `idx_cc_assignment_type` ON `class_courses`(`assignment_type`);

-- Enhance lecturer_courses table
ALTER TABLE `lecturer_courses`
ADD COLUMN IF NOT EXISTS `is_primary` TINYINT(1) DEFAULT 0 AFTER `course_id`,
ADD COLUMN IF NOT EXISTS `competency_level` ENUM('basic','intermediate','advanced','expert') DEFAULT 'intermediate' AFTER `is_primary`,
ADD COLUMN IF NOT EXISTS `max_classes_per_week` INT DEFAULT 5 AFTER `competency_level`,
ADD COLUMN IF NOT EXISTS `preferred_time_slots` JSON DEFAULT NULL AFTER `max_classes_per_week`;

-- Add check constraint for lecturer_courses
ALTER TABLE `lecturer_courses` 
ADD CONSTRAINT IF NOT EXISTS `chk_lc_max_classes` CHECK (`max_classes_per_week` > 0);

-- Enhance timetable table with professional features
ALTER TABLE `timetable`
ADD COLUMN IF NOT EXISTS `division_label` VARCHAR(10) DEFAULT NULL AFTER `academic_year`,
ADD COLUMN IF NOT EXISTS `session_duration` INT DEFAULT NULL AFTER `timetable_type`,
ADD COLUMN IF NOT EXISTS `attendance_required` TINYINT(1) DEFAULT 1 AFTER `session_duration`,
ADD COLUMN IF NOT EXISTS `notes` TEXT DEFAULT NULL AFTER `attendance_required`,
ADD COLUMN IF NOT EXISTS `created_by` VARCHAR(100) DEFAULT NULL AFTER `notes`,
ADD COLUMN IF NOT EXISTS `approved_by` VARCHAR(100) DEFAULT NULL AFTER `created_by`,
ADD COLUMN IF NOT EXISTS `approval_date` TIMESTAMP NULL DEFAULT NULL AFTER `approved_by`;

-- Enhance streams table with additional professional features
ALTER TABLE `streams`
ADD COLUMN IF NOT EXISTS `max_daily_hours` INT DEFAULT 8 AFTER `break_end`,
ADD COLUMN IF NOT EXISTS `max_weekly_hours` INT DEFAULT 40 AFTER `max_daily_hours`,
ADD COLUMN IF NOT EXISTS `color_code` VARCHAR(7) DEFAULT '#007bff' AFTER `max_weekly_hours`,
ADD COLUMN IF NOT EXISTS `sort_order` INT DEFAULT 1 AFTER `color_code`,
ADD COLUMN IF NOT EXISTS `is_current` TINYINT(1) DEFAULT 0 AFTER `is_active`;

-- Add check constraints for streams
ALTER TABLE `streams` 
ADD CONSTRAINT IF NOT EXISTS `chk_streams_period` CHECK (`period_start` < `period_end`),
ADD CONSTRAINT IF NOT EXISTS `chk_streams_max_daily` CHECK (`max_daily_hours` > 0),
ADD CONSTRAINT IF NOT EXISTS `chk_streams_max_weekly` CHECK (`max_weekly_hours` > 0);

-- Add indexes for streams
CREATE INDEX IF NOT EXISTS `idx_stream_current` ON `streams`(`is_current`);
CREATE INDEX IF NOT EXISTS `idx_stream_sort` ON `streams`(`sort_order`);

-- Enhance days table
ALTER TABLE `days`
ADD COLUMN IF NOT EXISTS `short_name` VARCHAR(3) UNIQUE DEFAULT NULL AFTER `name`,
ADD COLUMN IF NOT EXISTS `sort_order` INT UNIQUE DEFAULT NULL AFTER `short_name`,
ADD COLUMN IF NOT EXISTS `is_weekend` TINYINT(1) DEFAULT 0 AFTER `sort_order`;

-- Update days with proper data
UPDATE `days` SET 
    `short_name` = 'Mon', `sort_order` = 1, `is_weekend` = 0 
WHERE `name` = 'Monday';

UPDATE `days` SET 
    `short_name` = 'Tue', `sort_order` = 2, `is_weekend` = 0 
WHERE `name` = 'Tuesday';

UPDATE `days` SET 
    `short_name` = 'Wed', `sort_order` = 3, `is_weekend` = 0 
WHERE `name` = 'Wednesday';

UPDATE `days` SET 
    `short_name` = 'Thu', `sort_order` = 4, `is_weekend` = 0 
WHERE `name` = 'Thursday';

UPDATE `days` SET 
    `short_name` = 'Fri', `sort_order` = 5, `is_weekend` = 0 
WHERE `name` = 'Friday';

UPDATE `days` SET 
    `short_name` = 'Sat', `sort_order` = 6, `is_weekend` = 1 
WHERE `name` = 'Saturday';

UPDATE `days` SET 
    `short_name` = 'Sun', `sort_order` = 7, `is_weekend` = 1 
WHERE `name` = 'Sunday';

-- Enhance time_slots table
ALTER TABLE `time_slots`
ADD COLUMN IF NOT EXISTS `slot_name` VARCHAR(50) DEFAULT NULL AFTER `duration`,
ADD COLUMN IF NOT EXISTS `sort_order` INT DEFAULT NULL AFTER `is_mandatory`;

-- Update time slots with names and order
UPDATE `time_slots` SET 
    `slot_name` = CONCAT('Period ', 
        CASE 
            WHEN `start_time` = '07:00:00' THEN '1'
            WHEN `start_time` = '08:00:00' THEN '2'
            WHEN `start_time` = '09:00:00' THEN '3'
            WHEN `start_time` = '10:00:00' THEN '4'
            WHEN `start_time` = '11:00:00' THEN '5'
            WHEN `start_time` = '12:00:00' THEN 'Lunch'
            WHEN `start_time` = '13:00:00' THEN '6'
            WHEN `start_time` = '14:00:00' THEN '7'
            WHEN `start_time` = '15:00:00' THEN '8'
            WHEN `start_time` = '16:00:00' THEN '9'
            WHEN `start_time` = '17:00:00' THEN '10'
            WHEN `start_time` >= '18:00:00' THEN 'Evening'
            ELSE 'Other'
        END
    ),
    `sort_order` = HOUR(`start_time`)
WHERE `slot_name` IS NULL;

-- ============================================================================
-- STEP 2: CREATE PROFESSIONAL VALIDATION FUNCTIONS
-- ============================================================================

DELIMITER $$

-- Professional class-course assignment validation
DROP FUNCTION IF EXISTS `validate_class_course_professional`$$
CREATE FUNCTION `validate_class_course_professional`(
    p_class_id INT,
    p_course_id INT
) RETURNS JSON
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE v_class_program_dept INT;
    DECLARE v_class_level_id INT;
    DECLARE v_class_level_numeric INT;
    DECLARE v_course_dept INT;
    DECLARE v_course_level_id INT;
    DECLARE v_course_level_numeric INT;
    DECLARE v_course_type VARCHAR(50);
    DECLARE v_issues JSON DEFAULT JSON_ARRAY();
    DECLARE v_warnings JSON DEFAULT JSON_ARRAY();
    DECLARE v_quality_score INT DEFAULT 0;
    
    -- Get class details (classes are stream-specific)
    SELECT p.department_id, c.level_id, l.numeric_value
    INTO v_class_program_dept, v_class_level_id, v_class_level_numeric
    FROM classes c
    JOIN programs p ON c.program_id = p.id
    LEFT JOIN levels l ON c.level_id = l.id
    WHERE c.id = p_class_id AND c.is_active = 1;
    
    -- Get course details (courses are global)
    SELECT co.department_id, co.level_id, l.numeric_value, co.course_type
    INTO v_course_dept, v_course_level_id, v_course_level_numeric, v_course_type
    FROM courses co 
    LEFT JOIN levels l ON co.level_id = l.id
    WHERE co.id = p_course_id AND co.is_active = 1;
    
    -- Validation 1: Both must exist and be active
    IF v_class_program_dept IS NULL THEN
        SET v_issues = JSON_ARRAY_APPEND(v_issues, '$', 'Class not found or inactive');
        RETURN JSON_OBJECT('valid', FALSE, 'errors', v_issues, 'warnings', v_warnings, 'quality_score', 0);
    END IF;
    
    IF v_course_dept IS NULL THEN
        SET v_issues = JSON_ARRAY_APPEND(v_issues, '$', 'Course not found or inactive');
        RETURN JSON_OBJECT('valid', FALSE, 'errors', v_issues, 'warnings', v_warnings, 'quality_score', 0);
    END IF;
    
    -- Professional Validation 2: Level compatibility (CRITICAL)
    IF v_class_level_id IS NOT NULL AND v_course_level_id IS NOT NULL THEN
        IF v_class_level_id = v_course_level_id THEN
            SET v_quality_score = v_quality_score + 25; -- Major points for exact level match
        ELSE
            -- Check if levels are close (within 100 points)
            IF ABS(v_class_level_numeric - v_course_level_numeric) <= 100 THEN
                SET v_warnings = JSON_ARRAY_APPEND(v_warnings, '$', 
                    CONCAT('Level close but not exact: Class level ', v_class_level_numeric, ', Course level ', v_course_level_numeric));
                SET v_quality_score = v_quality_score + 5; -- Some points for close levels
            ELSE
                SET v_issues = JSON_ARRAY_APPEND(v_issues, '$', 
                    CONCAT('Level mismatch: Class level ', v_class_level_numeric, ', Course level ', v_course_level_numeric));
            END IF;
        END IF;
    END IF;
    
    -- Professional Validation 3: Department alignment (BUSINESS RULE)
    IF v_class_program_dept = v_course_dept THEN
        SET v_quality_score = v_quality_score + 20; -- Points for same department
    ELSE
        IF v_course_type = 'core' THEN
            -- Core courses should generally be from same department
            SET v_issues = JSON_ARRAY_APPEND(v_issues, '$', 
                CONCAT('Core course from different department: Class dept ', v_class_program_dept, ', Course dept ', v_course_dept));
        ELSEIF v_course_type IN ('elective', 'seminar') THEN
            -- Cross-departmental electives are acceptable
            SET v_quality_score = v_quality_score + 8;
            SET v_warnings = JSON_ARRAY_APPEND(v_warnings, '$', 
                CONCAT('Cross-departmental ', v_course_type, ' assignment'));
        ELSE
            SET v_quality_score = v_quality_score + 5;
            SET v_warnings = JSON_ARRAY_APPEND(v_warnings, '$', 
                CONCAT('Cross-departmental ', v_course_type, ' assignment'));
        END IF;
    END IF;
    
    -- Professional Validation 4: Course type scoring
    CASE v_course_type
        WHEN 'core' THEN SET v_quality_score = v_quality_score + 15;
        WHEN 'practical' THEN SET v_quality_score = v_quality_score + 12;
        WHEN 'project' THEN SET v_quality_score = v_quality_score + 10;
        WHEN 'elective' THEN SET v_quality_score = v_quality_score + 8;
        WHEN 'seminar' THEN SET v_quality_score = v_quality_score + 6;
    END CASE;
    
    -- Professional Validation 5: Check if already assigned
    IF EXISTS (
        SELECT 1 FROM class_courses 
        WHERE class_id = p_class_id AND course_id = p_course_id AND is_active = 1
    ) THEN
        SET v_warnings = JSON_ARRAY_APPEND(v_warnings, '$', 'Course already assigned to this class');
    END IF;
    
    -- Return comprehensive professional validation
    RETURN JSON_OBJECT(
        'valid', JSON_LENGTH(v_issues) = 0,
        'errors', v_issues,
        'warnings', v_warnings,
        'quality_score', GREATEST(0, v_quality_score), -- Ensure non-negative
        'class_department', v_class_program_dept,
        'course_department', v_course_dept,
        'class_level', v_class_level_numeric,
        'course_level', v_course_level_numeric,
        'course_type', v_course_type
    );
END$$

-- Professional assignment procedure
DROP PROCEDURE IF EXISTS `assign_course_professional`$$
CREATE PROCEDURE `assign_course_professional`(
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
    SET v_validation = validate_class_course_professional(p_class_id, p_course_id);
    SET v_is_valid = JSON_EXTRACT(v_validation, '$.valid');
    SET v_warnings = JSON_EXTRACT(v_validation, '$.warnings');
    SET p_quality_score = JSON_EXTRACT(v_validation, '$.quality_score');
    
    IF NOT v_is_valid THEN
        SET p_result = CONCAT('VALIDATION_FAILED: ', CAST(JSON_EXTRACT(v_validation, '$.errors') AS CHAR));
        ROLLBACK;
    ELSE
        -- Insert/update assignment with professional metadata
        INSERT INTO class_courses (
            class_id, course_id, lecturer_id, semester, academic_year,
            assignment_type, assigned_by, assignment_reason, quality_score, 
            validation_notes, is_active
        ) VALUES (
            p_class_id, p_course_id, p_lecturer_id, p_semester, p_academic_year,
            'manual', p_assigned_by, 'Professional assignment with validation', 
            p_quality_score, v_warnings, 1
        ) ON DUPLICATE KEY UPDATE
            lecturer_id = VALUES(lecturer_id),
            assignment_type = VALUES(assignment_type),
            assigned_by = VALUES(assigned_by),
            quality_score = VALUES(quality_score),
            validation_notes = VALUES(validation_notes),
            is_active = 1,
            updated_at = CURRENT_TIMESTAMP;
        
        IF JSON_LENGTH(v_warnings) > 0 THEN
            SET p_result = CONCAT('SUCCESS_WITH_WARNINGS: Quality Score ', p_quality_score, '/50');
        ELSE
            SET p_result = CONCAT('SUCCESS: Quality Score ', p_quality_score, '/50');
        END IF;
        
        COMMIT;
    END IF;
END$$

-- Function to get professional course recommendations
DROP FUNCTION IF EXISTS `get_course_recommendations_professional`$$
CREATE FUNCTION `get_course_recommendations_professional`(p_class_id INT)
RETURNS JSON
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_course_id INT;
    DECLARE v_course_code VARCHAR(20);
    DECLARE v_course_name VARCHAR(200);
    DECLARE v_recommendation_score INT;
    DECLARE v_result JSON DEFAULT JSON_ARRAY();
    
    DECLARE course_cursor CURSOR FOR
        SELECT 
            co.id,
            co.code,
            co.name,
            (
                CASE WHEN p.department_id = co.department_id THEN 20 ELSE 0 END +
                CASE WHEN c.level_id = co.level_id THEN 25 ELSE -20 END +
                CASE WHEN co.course_type = 'core' THEN 15 ELSE 0 END +
                CASE WHEN co.course_type = 'practical' THEN 12 ELSE 0 END +
                CASE WHEN co.course_type = 'elective' THEN 8 ELSE 0 END
            ) as recommendation_score
        FROM classes c
        JOIN programs p ON c.program_id = p.id
        CROSS JOIN courses co
        WHERE c.id = p_class_id 
        AND c.is_active = 1 
        AND co.is_active = 1
        AND co.id NOT IN (
            SELECT cc.course_id 
            FROM class_courses cc 
            WHERE cc.class_id = p_class_id AND cc.is_active = 1
        )
        HAVING recommendation_score > 0
        ORDER BY recommendation_score DESC, co.code
        LIMIT 20;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN course_cursor;
    
    course_loop: LOOP
        FETCH course_cursor INTO v_course_id, v_course_code, v_course_name, v_recommendation_score;
        IF done THEN
            LEAVE course_loop;
        END IF;
        
        SET v_result = JSON_ARRAY_APPEND(v_result, '$', JSON_OBJECT(
            'id', v_course_id,
            'code', v_course_code,
            'name', v_course_name,
            'score', v_recommendation_score
        ));
    END LOOP;
    
    CLOSE course_cursor;
    RETURN v_result;
END$$

DELIMITER ;

-- ============================================================================
-- STEP 3: CREATE PROFESSIONAL TRIGGERS
-- ============================================================================

DELIMITER $$

-- Trigger to automatically calculate quality scores
DROP TRIGGER IF EXISTS `auto_calculate_assignment_quality`$$
CREATE TRIGGER `auto_calculate_assignment_quality`
BEFORE INSERT ON `class_courses`
FOR EACH ROW
BEGIN
    DECLARE v_validation JSON;
    
    IF NEW.quality_score IS NULL THEN
        SET v_validation = validate_class_course_professional(NEW.class_id, NEW.course_id);
        SET NEW.quality_score = JSON_EXTRACT(v_validation, '$.quality_score');
        SET NEW.validation_notes = JSON_EXTRACT(v_validation, '$.warnings');
    END IF;
END$$

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

-- Trigger to auto-calculate division capacity
DROP TRIGGER IF EXISTS `auto_division_capacity`$$
CREATE TRIGGER `auto_division_capacity`
BEFORE INSERT ON `classes`
FOR EACH ROW
BEGIN
    IF NEW.division_capacity IS NULL AND NEW.divisions_count > 0 THEN
        SET NEW.division_capacity = CEIL(NEW.total_capacity / NEW.divisions_count);
    END IF;
END$$

DROP TRIGGER IF EXISTS `auto_division_capacity_update`$$
CREATE TRIGGER `auto_division_capacity_update`
BEFORE UPDATE ON `classes`
FOR EACH ROW
BEGIN
    IF (NEW.total_capacity != OLD.total_capacity OR NEW.divisions_count != OLD.divisions_count) 
       AND NEW.divisions_count > 0 THEN
        SET NEW.division_capacity = CEIL(NEW.total_capacity / NEW.divisions_count);
    END IF;
END$$

-- Trigger for professional timetable validation
DROP TRIGGER IF EXISTS `validate_timetable_professional`$$
CREATE TRIGGER `validate_timetable_professional`
BEFORE INSERT ON `timetable`
FOR EACH ROW
BEGIN
    DECLARE v_room_capacity INT;
    DECLARE v_class_enrollment INT;
    DECLARE v_stream_id INT;
    DECLARE v_stream_start TIME;
    DECLARE v_stream_end TIME;
    DECLARE v_slot_start TIME;
    DECLARE v_slot_end TIME;
    
    -- Get room capacity
    SELECT capacity INTO v_room_capacity FROM rooms WHERE id = NEW.room_id;
    
    -- Get class enrollment and stream
    SELECT current_enrollment, stream_id INTO v_class_enrollment, v_stream_id 
    FROM classes WHERE id = NEW.class_id;
    
    -- Check room capacity
    IF v_class_enrollment > v_room_capacity THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = CONCAT('Room capacity (', v_room_capacity, ') exceeded by class enrollment (', v_class_enrollment, ')');
    END IF;
    
    -- Check if time slot is within stream period
    SELECT period_start, period_end INTO v_stream_start, v_stream_end
    FROM streams WHERE id = v_stream_id;
    
    SELECT start_time, end_time INTO v_slot_start, v_slot_end
    FROM time_slots WHERE id = NEW.time_slot_id;
    
    IF v_slot_start < v_stream_start OR v_slot_end > v_stream_end THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = CONCAT('Time slot (', v_slot_start, '-', v_slot_end, 
                                 ') is outside stream period (', v_stream_start, '-', v_stream_end, ')');
    END IF;
END$$

DELIMITER ;

-- ============================================================================
-- STEP 4: CREATE PROFESSIONAL VIEWS
-- ============================================================================

-- Comprehensive classes view with all context
CREATE OR REPLACE VIEW `classes_comprehensive` AS
SELECT 
    c.id,
    c.name as class_name,
    c.code as class_code,
    c.total_capacity,
    c.current_enrollment,
    c.divisions_count,
    c.division_capacity,
    c.academic_year,
    c.semester,
    c.is_active,
    
    -- Program and department context (global entities)
    p.name as program_name,
    p.code as program_code,
    p.duration_years,
    d.name as department_name,
    d.code as department_code,
    
    -- Level context (global)
    l.name as level_name,
    l.code as level_code,
    l.numeric_value as level_number,
    
    -- Stream context (only classes are stream-specific)
    s.name as stream_name,
    s.code as stream_code,
    s.period_start,
    s.period_end,
    s.color_code as stream_color,
    
    -- Professional metrics
    ROUND((c.current_enrollment * 100.0) / NULLIF(c.total_capacity, 0), 1) as enrollment_percentage,
    CASE 
        WHEN c.current_enrollment >= c.total_capacity THEN 'Full'
        WHEN c.current_enrollment >= (c.total_capacity * 0.9) THEN 'Nearly Full'
        WHEN c.current_enrollment >= (c.total_capacity * 0.5) THEN 'Half Full'
        ELSE 'Available'
    END as enrollment_status,
    
    -- Assignment statistics
    (SELECT COUNT(*) FROM class_courses cc WHERE cc.class_id = c.id AND cc.is_active = 1) as assigned_courses_count,
    (SELECT AVG(cc.quality_score) FROM class_courses cc WHERE cc.class_id = c.id AND cc.is_active = 1) as avg_assignment_quality,
    (SELECT COUNT(*) FROM timetable t WHERE t.class_id = c.id) as scheduled_sessions_count

FROM classes c
LEFT JOIN programs p ON c.program_id = p.id
LEFT JOIN departments d ON p.department_id = d.id
LEFT JOIN levels l ON c.level_id = l.id
LEFT JOIN streams s ON c.stream_id = s.id;

-- Professional assignment quality monitoring
CREATE OR REPLACE VIEW `assignment_quality_professional` AS
SELECT 
    cc.id as assignment_id,
    cc.quality_score,
    cc.approval_status,
    cc.assignment_type,
    cc.assigned_by,
    cc.created_at as assignment_date,
    
    -- Class context (stream-specific)
    c.name as class_name,
    c.code as class_code,
    s.name as stream_name,
    s.code as stream_code,
    
    -- Course context (global)
    co.code as course_code,
    co.name as course_name,
    co.course_type,
    co.credits,
    
    -- Department context (global)
    d1.name as class_department,
    d2.name as course_department,
    
    -- Level context (global)
    l1.name as class_level,
    l1.numeric_value as class_level_number,
    l2.name as course_level,
    l2.numeric_value as course_level_number,
    
    -- Professional quality indicators
    CASE 
        WHEN cc.quality_score >= 45 THEN 'excellent'
        WHEN cc.quality_score >= 35 THEN 'very_good'
        WHEN cc.quality_score >= 25 THEN 'good'
        WHEN cc.quality_score >= 15 THEN 'acceptable'
        WHEN cc.quality_score >= 5 THEN 'needs_review'
        ELSE 'poor'
    END as quality_rating,
    
    -- Compatibility flags
    d1.id = d2.id as dept_match,
    l1.id = l2.id as level_match,
    
    -- Issue identification
    CASE 
        WHEN l1.id != l2.id THEN 'level_mismatch'
        WHEN d1.id != d2.id AND co.course_type = 'core' THEN 'core_course_wrong_dept'
        WHEN cc.quality_score < 15 THEN 'low_quality'
        WHEN cc.approval_status = 'pending' THEN 'pending_approval'
        WHEN cc.approval_status = 'rejected' THEN 'rejected'
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

-- Professional timetable view with stream awareness
CREATE OR REPLACE VIEW `timetable_professional` AS
SELECT 
    t.id as timetable_id,
    t.semester,
    t.academic_year,
    t.division_label,
    t.timetable_type,
    
    -- Class information (stream-specific)
    c.name as class_name,
    c.code as class_code,
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
    
    -- Professional quality indicators
    CASE 
        WHEN c.current_enrollment > r.capacity THEN 'room_overcapacity'
        WHEN ts.start_time < s.period_start OR ts.end_time > s.period_end THEN 'outside_stream_period'
        WHEN c.current_enrollment = 0 THEN 'no_enrollment'
        ELSE 'valid'
    END as validation_status,
    
    -- Utilization metrics
    ROUND((c.current_enrollment * 100.0) / NULLIF(r.capacity, 0), 1) as room_utilization_percent

FROM timetable t
JOIN classes c ON t.class_id = c.id
JOIN programs p ON c.program_id = p.id
JOIN departments dept ON p.department_id = dept.id
JOIN streams s ON c.stream_id = s.id
JOIN courses co ON t.course_id = co.id
JOIN lecturers l ON t.lecturer_id = l.id
JOIN rooms r ON t.room_id = r.id
LEFT JOIN buildings b ON r.building_id = b.id
JOIN days d ON t.day_id = d.id
JOIN time_slots ts ON t.time_slot_id = ts.id
WHERE t.is_active = 1;

-- Stream utilization with professional metrics
CREATE OR REPLACE VIEW `stream_utilization_professional` AS
SELECT 
    s.id as stream_id,
    s.name as stream_name,
    s.code as stream_code,
    s.period_start,
    s.period_end,
    s.color_code,
    s.is_current,
    
    -- Class metrics (only classes are stream-specific)
    COUNT(DISTINCT c.id) as total_classes,
    SUM(c.total_capacity) as total_capacity,
    SUM(c.current_enrollment) as total_enrollment,
    
    -- Assignment metrics
    COUNT(DISTINCT cc.course_id) as unique_courses_assigned,
    COUNT(cc.id) as total_assignments,
    AVG(cc.quality_score) as avg_assignment_quality,
    
    -- Timetable metrics
    COUNT(DISTINCT t.id) as scheduled_sessions,
    COUNT(DISTINCT t.room_id) as rooms_used,
    COUNT(DISTINCT t.lecturer_id) as lecturers_used,
    
    -- Professional quality metrics
    SUM(CASE WHEN cc.quality_score >= 35 THEN 1 ELSE 0 END) as high_quality_assignments,
    SUM(CASE WHEN cc.quality_score < 15 THEN 1 ELSE 0 END) as low_quality_assignments,
    
    -- Utilization percentages
    ROUND((SUM(c.current_enrollment) * 100.0) / NULLIF(SUM(c.total_capacity), 0), 2) as enrollment_utilization_percent,
    ROUND((COUNT(DISTINCT t.id) * 100.0) / NULLIF(COUNT(cc.id), 0), 2) as scheduling_completion_percent

FROM streams s
LEFT JOIN classes c ON s.id = c.stream_id AND c.is_active = 1
LEFT JOIN class_courses cc ON c.id = cc.class_id AND cc.is_active = 1
LEFT JOIN timetable t ON c.id = t.class_id AND cc.course_id = t.course_id
GROUP BY s.id
ORDER BY s.sort_order, s.id;

-- ============================================================================
-- STEP 5: CREATE AUDIT AND MONITORING TABLES
-- ============================================================================

-- Professional audit log
CREATE TABLE IF NOT EXISTS `professional_audit_log` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `table_name` VARCHAR(50) NOT NULL,
    `record_id` INT NOT NULL,
    `action` ENUM('insert','update','delete','approve','reject') NOT NULL,
    `old_values` JSON DEFAULT NULL,
    `new_values` JSON DEFAULT NULL,
    `quality_impact` JSON DEFAULT NULL,
    `changed_by` VARCHAR(100) DEFAULT NULL,
    `change_reason` TEXT DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `session_id` VARCHAR(100) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    INDEX `idx_audit_table_record` (`table_name`, `record_id`),
    INDEX `idx_audit_user` (`changed_by`),
    INDEX `idx_audit_date` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Assignment quality tracking
CREATE TABLE IF NOT EXISTS `assignment_quality_history` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `class_course_id` INT NOT NULL,
    `old_quality_score` INT DEFAULT NULL,
    `new_quality_score` INT NOT NULL,
    `change_reason` TEXT DEFAULT NULL,
    `changed_by` VARCHAR(100) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    FOREIGN KEY (`class_course_id`) REFERENCES `class_courses` (`id`) ON DELETE CASCADE,
    INDEX `idx_quality_history_date` (`created_at`),
    INDEX `idx_quality_history_score` (`new_quality_score`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Professional system configuration
CREATE TABLE IF NOT EXISTS `professional_config` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `config_key` VARCHAR(100) NOT NULL UNIQUE,
    `config_value` TEXT NOT NULL,
    `config_type` ENUM('string','integer','boolean','json','time','date') DEFAULT 'string',
    `category` VARCHAR(50) DEFAULT 'general',
    `description` TEXT DEFAULT NULL,
    `validation_rules` JSON DEFAULT NULL,
    `is_system` TINYINT(1) DEFAULT 0,
    `requires_approval` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    INDEX `idx_config_category` (`category`),
    INDEX `idx_config_system` (`is_system`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Insert professional configurations
INSERT INTO `professional_config` (`config_key`, `config_value`, `config_type`, `category`, `description`, `is_system`) VALUES
('min_assignment_quality_score', '15', 'integer', 'validation', 'Minimum quality score for assignments', 0),
('require_approval_cross_dept_core', 'true', 'boolean', 'validation', 'Require approval for cross-departmental core courses', 0),
('max_assignments_per_class', '12', 'integer', 'academic', 'Maximum course assignments per class', 0),
('enable_smart_assignment', 'true', 'boolean', 'features', 'Enable smart assignment feature', 0),
('default_assignment_quality_threshold', '25', 'integer', 'validation', 'Default quality threshold for smart assignments', 0),
('enable_real_time_validation', 'true', 'boolean', 'features', 'Enable real-time assignment validation', 0),
('current_academic_year', '2024/2025', 'string', 'academic', 'Current academic year', 1),
('current_semester', 'first', 'string', 'academic', 'Current semester', 1);

-- ============================================================================
-- STEP 6: POPULATE STREAM MAPPINGS AND DATA
-- ============================================================================

-- Ensure stream time slot mappings exist
INSERT IGNORE INTO `stream_time_slots` (`stream_id`, `time_slot_id`, `is_active`)
SELECT s.id, ts.id, 1
FROM streams s
CROSS JOIN time_slots ts
WHERE s.is_active = 1 
AND ts.is_active = 1 
AND (
    (s.code = 'REG' AND ts.start_time >= '08:00:00' AND ts.end_time <= '17:00:00' AND ts.is_break = 0) OR
    (s.code = 'WKD' AND ts.start_time >= '09:00:00' AND ts.end_time <= '17:00:00' AND ts.is_break = 0) OR
    (s.code = 'EVE' AND ts.start_time >= '18:00:00' AND ts.end_time <= '22:00:00')
);

-- Create stream_days table if it doesn't exist
CREATE TABLE IF NOT EXISTS `stream_days` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `stream_id` INT NOT NULL,
    `day_id` INT NOT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_stream_day` (`stream_id`, `day_id`),
    FOREIGN KEY (`stream_id`) REFERENCES `streams` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`day_id`) REFERENCES `days` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Populate stream-day mappings
INSERT IGNORE INTO `stream_days` (`stream_id`, `day_id`)
SELECT s.id, d.id
FROM streams s
CROSS JOIN days d
WHERE s.is_active = 1 
AND d.is_active = 1
AND (
    (s.code = 'REG' AND d.sort_order BETWEEN 1 AND 5) OR  -- Regular: Mon-Fri
    (s.code = 'WKD' AND d.sort_order BETWEEN 6 AND 7) OR  -- Weekend: Sat-Sun
    (s.code = 'EVE' AND d.sort_order BETWEEN 1 AND 5)     -- Evening: Mon-Fri
);

-- Update existing data to ensure consistency
UPDATE `class_courses` cc 
SET cc.quality_score = (
    SELECT JSON_EXTRACT(validate_class_course_professional(cc.class_id, cc.course_id), '$.quality_score')
)
WHERE cc.quality_score IS NULL AND cc.is_active = 1;

-- ============================================================================
-- STEP 7: CREATE PROFESSIONAL INDEXES FOR PERFORMANCE
-- ============================================================================

-- Professional indexes for classes (stream-specific)
CREATE INDEX IF NOT EXISTS `idx_classes_stream_academic` ON `classes`(`stream_id`, `academic_year`, `semester`, `is_active`);
CREATE INDEX IF NOT EXISTS `idx_classes_program_level` ON `classes`(`program_id`, `level_id`, `is_active`);
CREATE INDEX IF NOT EXISTS `idx_classes_enrollment` ON `classes`(`current_enrollment`, `total_capacity`);

-- Professional indexes for courses (global)
CREATE INDEX IF NOT EXISTS `idx_courses_dept_level_type` ON `courses`(`department_id`, `level_id`, `course_type`, `is_active`);
CREATE INDEX IF NOT EXISTS `idx_courses_type_active` ON `courses`(`course_type`, `is_active`);

-- Professional indexes for class_courses
CREATE INDEX IF NOT EXISTS `idx_cc_quality_approval` ON `class_courses`(`quality_score`, `approval_status`);
CREATE INDEX IF NOT EXISTS `idx_cc_assignment_type_active` ON `class_courses`(`assignment_type`, `is_active`);

-- Professional indexes for timetable
CREATE INDEX IF NOT EXISTS `idx_timetable_class_academic` ON `timetable`(`class_id`, `academic_year`, `semester`);
CREATE INDEX IF NOT EXISTS `idx_timetable_validation` ON `timetable`(`day_id`, `time_slot_id`, `room_id`, `semester`, `academic_year`);

-- ============================================================================
-- STEP 8: FINAL DATA CONSISTENCY AND VALIDATION
-- ============================================================================

-- Map courses to levels based on course codes (if level_id is null)
UPDATE `courses` co
JOIN `levels` l ON (
    (co.code REGEXP '^[A-Z]+[[:space:]]*1[0-9][0-9]' AND l.numeric_value = 100) OR
    (co.code REGEXP '^[A-Z]+[[:space:]]*2[0-9][0-9]' AND l.numeric_value = 200) OR
    (co.code REGEXP '^[A-Z]+[[:space:]]*3[0-9][0-9]' AND l.numeric_value = 300) OR
    (co.code REGEXP '^[A-Z]+[[:space:]]*4[0-9][0-9]' AND l.numeric_value = 400)
)
SET co.level_id = l.id
WHERE co.level_id IS NULL;

-- Ensure all classes have proper division capacity
UPDATE `classes` 
SET `division_capacity` = CEIL(`total_capacity` / `divisions_count`)
WHERE `division_capacity` IS NULL AND `divisions_count` > 0;

-- Set default stream as current if none is set
UPDATE `streams` SET `is_current` = 1 
WHERE `code` = 'REG' AND NOT EXISTS (SELECT 1 FROM streams WHERE is_current = 1);

-- Ensure stream sort orders
UPDATE `streams` SET `sort_order` = 1 WHERE `code` = 'REG';
UPDATE `streams` SET `sort_order` = 2 WHERE `code` = 'WKD';
UPDATE `streams` SET `sort_order` = 3 WHERE `code` = 'EVE';

/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Professional schema enhancement completed