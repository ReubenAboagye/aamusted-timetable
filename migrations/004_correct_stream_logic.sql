-- Migration 004: Correct Stream Logic
-- REMOVES stream_id from global tables and keeps it ONLY on classes
-- This reflects the correct business logic where only classes rotate with streams

USE timetable_system;

-- ============================================================================
-- STEP 1: BACKUP EXISTING DATA
-- ============================================================================

-- Create backup tables before making changes
CREATE TABLE IF NOT EXISTS `backup_courses` AS SELECT * FROM `courses`;
CREATE TABLE IF NOT EXISTS `backup_lecturers` AS SELECT * FROM `lecturers`;
CREATE TABLE IF NOT EXISTS `backup_rooms` AS SELECT * FROM `rooms`;
CREATE TABLE IF NOT EXISTS `backup_departments` AS SELECT * FROM `departments`;
CREATE TABLE IF NOT EXISTS `backup_programs` AS SELECT * FROM `programs`;

-- ============================================================================
-- STEP 2: REMOVE STREAM_ID FROM GLOBAL TABLES
-- ============================================================================

-- Remove stream_id from courses (courses are global)
ALTER TABLE `courses` DROP FOREIGN KEY IF EXISTS `courses_ibfk_2`;
ALTER TABLE `courses` DROP INDEX IF EXISTS `stream_id`;
ALTER TABLE `courses` DROP COLUMN IF EXISTS `stream_id`;

-- Remove stream_id from lecturers (lecturers are global)
ALTER TABLE `lecturers` DROP FOREIGN KEY IF EXISTS `lecturers_ibfk_2`;
ALTER TABLE `lecturers` DROP INDEX IF EXISTS `stream_id`;
ALTER TABLE `lecturers` DROP COLUMN IF EXISTS `stream_id`;

-- Remove stream_id from rooms (rooms are global)
ALTER TABLE `rooms` DROP FOREIGN KEY IF EXISTS `rooms_ibfk_2`;
ALTER TABLE `rooms` DROP INDEX IF EXISTS `stream_id`;
ALTER TABLE `rooms` DROP COLUMN IF EXISTS `stream_id`;

-- Remove stream_id from departments (departments are global)
ALTER TABLE `departments` DROP FOREIGN KEY IF EXISTS `departments_ibfk_2`;
ALTER TABLE `departments` DROP INDEX IF EXISTS `stream_id`;
ALTER TABLE `departments` DROP COLUMN IF EXISTS `stream_id`;

-- Remove stream_id from programs (programs are global)
ALTER TABLE `programs` DROP FOREIGN KEY IF EXISTS `programs_ibfk_2`;
ALTER TABLE `programs` DROP INDEX IF EXISTS `stream_id`;
ALTER TABLE `programs` DROP COLUMN IF EXISTS `stream_id`;

-- ============================================================================
-- STEP 3: ENHANCE CLASSES TABLE (The ONLY stream-specific table)
-- ============================================================================

-- Ensure classes table has all necessary fields
ALTER TABLE `classes` 
ADD COLUMN IF NOT EXISTS `class_code` VARCHAR(20) UNIQUE DEFAULT NULL AFTER `name`,
ADD COLUMN IF NOT EXISTS `program_id` INT DEFAULT NULL AFTER `department_id`,
ADD COLUMN IF NOT EXISTS `level_id` INT DEFAULT NULL AFTER `program_id`,
ADD COLUMN IF NOT EXISTS `academic_year` VARCHAR(10) NOT NULL DEFAULT '2024/2025' AFTER `stream_id`,
ADD COLUMN IF NOT EXISTS `semester` ENUM('first', 'second', 'summer') NOT NULL DEFAULT 'first' AFTER `academic_year`,
ADD COLUMN IF NOT EXISTS `divisions_count` INT DEFAULT 1 AFTER `current_enrollment`,
ADD COLUMN IF NOT EXISTS `division_capacity` INT DEFAULT 30 AFTER `divisions_count`;

-- Add foreign keys for enhanced classes table
ALTER TABLE `classes` 
ADD CONSTRAINT IF NOT EXISTS `fk_classes_program` FOREIGN KEY (`program_id`) REFERENCES `programs`(`id`) ON DELETE SET NULL,
ADD CONSTRAINT IF NOT EXISTS `fk_classes_level` FOREIGN KEY (`level_id`) REFERENCES `levels`(`id`) ON DELETE SET NULL;

-- Update classes table with proper data
UPDATE `classes` c 
LEFT JOIN `programs` p ON p.department_id = c.department_id 
SET c.program_id = p.id 
WHERE c.program_id IS NULL AND p.id IS NOT NULL;

-- Map existing level strings to level_id
UPDATE `classes` c 
JOIN `levels` l ON (
    (c.level LIKE '%100%' AND l.numeric_value = 100) OR
    (c.level LIKE '%200%' AND l.numeric_value = 200) OR
    (c.level LIKE '%300%' AND l.numeric_value = 300) OR
    (c.level LIKE '%400%' AND l.numeric_value = 400) OR
    (c.level LIKE '%500%' AND l.numeric_value = 500)
)
SET c.level_id = l.id
WHERE c.level_id IS NULL;

-- ============================================================================
-- STEP 4: ENHANCE COURSES TABLE STRUCTURE
-- ============================================================================

-- Ensure courses table has level_id instead of numeric level
ALTER TABLE `courses` 
ADD COLUMN IF NOT EXISTS `level_id` INT DEFAULT NULL AFTER `department_id`,
ADD COLUMN IF NOT EXISTS `course_type` ENUM('core', 'elective', 'practical', 'project') DEFAULT 'core' AFTER `hours_per_week`,
ADD COLUMN IF NOT EXISTS `prerequisites` JSON DEFAULT NULL AFTER `course_type`;

-- Add foreign key for course level
ALTER TABLE `courses` 
ADD CONSTRAINT IF NOT EXISTS `fk_courses_level` FOREIGN KEY (`level_id`) REFERENCES `levels`(`id`) ON DELETE SET NULL;

-- Map existing numeric level to level_id
UPDATE `courses` c 
JOIN `levels` l ON c.level = l.numeric_value 
SET c.level_id = l.id 
WHERE c.level_id IS NULL;

-- ============================================================================
-- STEP 5: UPDATE CLASS_COURSES TABLE
-- ============================================================================

-- Remove stream_id from class_courses (it's derived from classes.stream_id)
ALTER TABLE `class_courses` DROP FOREIGN KEY IF EXISTS `fk_class_courses_stream`;
ALTER TABLE `class_courses` DROP INDEX IF EXISTS `idx_class_courses_stream`;
ALTER TABLE `class_courses` DROP COLUMN IF EXISTS `stream_id`;

-- Ensure class_courses has proper academic context
ALTER TABLE `class_courses`
ADD COLUMN IF NOT EXISTS `semester` ENUM('first', 'second', 'summer') NOT NULL DEFAULT 'first' AFTER `course_id`,
ADD COLUMN IF NOT EXISTS `academic_year` VARCHAR(10) NOT NULL DEFAULT '2024/2025' AFTER `semester`,
ADD COLUMN IF NOT EXISTS `assigned_by` VARCHAR(100) DEFAULT NULL AFTER `academic_year`,
ADD COLUMN IF NOT EXISTS `assignment_reason` TEXT DEFAULT NULL AFTER `assigned_by`,
ADD COLUMN IF NOT EXISTS `is_mandatory` BOOLEAN DEFAULT TRUE AFTER `assignment_reason`;

-- Update unique constraint for class_courses
ALTER TABLE `class_courses` 
DROP INDEX IF EXISTS `uq_class_course`,
DROP INDEX IF EXISTS `uq_class_course_semester`,
ADD UNIQUE KEY `unique_class_course_semester` (`class_id`, `course_id`, `semester`, `academic_year`);

-- ============================================================================
-- STEP 6: UPDATE TIMETABLE TABLE
-- ============================================================================

-- Remove stream_id from timetable (it's derived from classes.stream_id via class_courses)
ALTER TABLE `timetable` DROP FOREIGN KEY IF EXISTS `fk_timetable_stream`;
ALTER TABLE `timetable` DROP INDEX IF EXISTS `idx_timetable_stream_day_time`;
ALTER TABLE `timetable` DROP COLUMN IF EXISTS `stream_id`;

-- Ensure timetable has proper fields
ALTER TABLE `timetable`
ADD COLUMN IF NOT EXISTS `semester` ENUM('first', 'second', 'summer') NOT NULL DEFAULT 'first' AFTER `room_id`,
ADD COLUMN IF NOT EXISTS `academic_year` VARCHAR(10) NOT NULL DEFAULT '2024/2025' AFTER `semester`,
ADD COLUMN IF NOT EXISTS `division_label` VARCHAR(10) DEFAULT NULL AFTER `academic_year`,
ADD COLUMN IF NOT EXISTS `notes` TEXT DEFAULT NULL AFTER `division_label`,
ADD COLUMN IF NOT EXISTS `created_by` VARCHAR(100) DEFAULT NULL AFTER `notes`;

-- Update timetable unique constraints (removed stream_id)
ALTER TABLE `timetable`
DROP INDEX IF EXISTS `uq_tt_slot`,
DROP INDEX IF EXISTS `uq_timetable_slot_stream`,
DROP INDEX IF EXISTS `unique_room_time_slot`,
DROP INDEX IF EXISTS `unique_lecturer_time_slot`,
DROP INDEX IF EXISTS `unique_class_time_division`,
ADD UNIQUE KEY `unique_room_time_slot` (`room_id`, `day_id`, `time_slot_id`, `semester`, `academic_year`),
ADD UNIQUE KEY `unique_lecturer_time_slot` (`lecturer_course_id`, `day_id`, `time_slot_id`, `semester`, `academic_year`),
ADD UNIQUE KEY `unique_class_time_division` (`class_course_id`, `day_id`, `time_slot_id`, `division_label`, `semester`, `academic_year`);

-- ============================================================================
-- STEP 7: CREATE PROFESSIONAL VALIDATION FUNCTIONS
-- ============================================================================

DELIMITER $$

-- Function to validate department-oriented class-course assignment
DROP FUNCTION IF EXISTS `validate_class_course_assignment_professional`$$
CREATE FUNCTION `validate_class_course_assignment_professional`(
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
    DECLARE v_course_type VARCHAR(50);
    DECLARE v_issues JSON DEFAULT JSON_ARRAY();
    DECLARE v_warnings JSON DEFAULT JSON_ARRAY();
    
    -- Get class details
    SELECT c.department_id, c.level_id, c.program_id
    INTO v_class_dept, v_class_level, v_class_program
    FROM classes c WHERE c.id = p_class_id AND c.is_active = 1;
    
    -- Get course details
    SELECT co.department_id, co.level_id, co.course_type
    INTO v_course_dept, v_course_level, v_course_type
    FROM courses co WHERE co.id = p_course_id AND co.is_active = 1;
    
    -- Validation 1: Both must exist and be active
    IF v_class_dept IS NULL THEN
        SET v_issues = JSON_ARRAY_APPEND(v_issues, '$', 'Class not found or inactive');
    END IF;
    
    IF v_course_dept IS NULL THEN
        SET v_issues = JSON_ARRAY_APPEND(v_issues, '$', 'Course not found or inactive');
    END IF;
    
    -- Validation 2: Level must match (CRITICAL)
    IF v_class_level IS NOT NULL AND v_course_level IS NOT NULL AND v_class_level != v_course_level THEN
        SET v_issues = JSON_ARRAY_APPEND(v_issues, '$', 
            CONCAT('Level mismatch: Class level ', v_class_level, ', Course level ', v_course_level));
    END IF;
    
    -- Validation 3: Department compatibility (PROFESSIONAL RULE)
    IF v_class_dept IS NOT NULL AND v_course_dept IS NOT NULL THEN
        IF v_class_dept != v_course_dept THEN
            IF v_course_type = 'core' THEN
                -- Core courses should generally be from same department
                SET v_issues = JSON_ARRAY_APPEND(v_issues, '$', 
                    CONCAT('Core course from different department: Class dept ', v_class_dept, ', Course dept ', v_course_dept));
            ELSE
                -- Electives can be cross-departmental but warn
                SET v_warnings = JSON_ARRAY_APPEND(v_warnings, '$', 
                    CONCAT('Cross-departmental assignment: ', v_course_type, ' course from different department'));
            END IF;
        END IF;
    END IF;
    
    -- Validation 4: Check if already assigned
    IF EXISTS (
        SELECT 1 FROM class_courses 
        WHERE class_id = p_class_id AND course_id = p_course_id AND is_active = 1
    ) THEN
        SET v_warnings = JSON_ARRAY_APPEND(v_warnings, '$', 'Course already assigned to this class');
    END IF;
    
    -- Return comprehensive validation result
    RETURN JSON_OBJECT(
        'valid', JSON_LENGTH(v_issues) = 0,
        'errors', v_issues,
        'warnings', v_warnings,
        'class_dept', v_class_dept,
        'course_dept', v_course_dept,
        'class_level', v_class_level,
        'course_level', v_course_level,
        'course_type', v_course_type
    );
END$$

-- Enhanced stored procedure for professional assignment
DROP PROCEDURE IF EXISTS `assign_course_to_class_professional`$$
CREATE PROCEDURE `assign_course_to_class_professional`(
    IN p_class_id INT,
    IN p_course_id INT,
    IN p_semester VARCHAR(20),
    IN p_academic_year VARCHAR(10),
    IN p_assigned_by VARCHAR(100),
    IN p_assignment_reason TEXT,
    OUT p_result VARCHAR(100),
    OUT p_warnings JSON
)
BEGIN
    DECLARE v_validation JSON;
    DECLARE v_is_valid BOOLEAN DEFAULT FALSE;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_result = 'ERROR: Database error occurred';
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Validate assignment using professional rules
    SET v_validation = validate_class_course_assignment_professional(p_class_id, p_course_id);
    SET v_is_valid = JSON_EXTRACT(v_validation, '$.valid');
    SET p_warnings = JSON_EXTRACT(v_validation, '$.warnings');
    
    IF NOT v_is_valid THEN
        SET p_result = CONCAT('VALIDATION_FAILED: ', CAST(JSON_EXTRACT(v_validation, '$.errors') AS CHAR));
        ROLLBACK;
    ELSE
        -- Insert/update assignment
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
        
        SET p_result = 'SUCCESS';
        COMMIT;
    END IF;
END$$

-- Function to get stream-specific available time slots
DROP FUNCTION IF EXISTS `get_stream_available_time_slots`$$
CREATE FUNCTION `get_stream_available_time_slots`(p_stream_id INT)
RETURNS JSON
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_slot_id INT;
    DECLARE v_start_time TIME;
    DECLARE v_end_time TIME;
    DECLARE v_slot_name VARCHAR(50);
    DECLARE v_result JSON DEFAULT JSON_ARRAY();
    
    DECLARE slot_cursor CURSOR FOR
        SELECT ts.id, ts.start_time, ts.end_time, ts.slot_name
        FROM time_slots ts
        JOIN stream_time_slots sts ON ts.id = sts.time_slot_id
        WHERE sts.stream_id = p_stream_id 
        AND sts.is_active = 1 
        AND ts.is_active = 1
        ORDER BY ts.start_time;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN slot_cursor;
    
    slot_loop: LOOP
        FETCH slot_cursor INTO v_slot_id, v_start_time, v_end_time, v_slot_name;
        IF done THEN
            LEAVE slot_loop;
        END IF;
        
        SET v_result = JSON_ARRAY_APPEND(v_result, '$', JSON_OBJECT(
            'id', v_slot_id,
            'start_time', v_start_time,
            'end_time', v_end_time,
            'slot_name', v_slot_name
        ));
    END LOOP;
    
    CLOSE slot_cursor;
    RETURN v_result;
END$$

-- Function to check if time slot is within stream period
DROP FUNCTION IF EXISTS `is_time_slot_in_stream_period`$$
CREATE FUNCTION `is_time_slot_in_stream_period`(
    p_stream_id INT,
    p_time_slot_id INT
) RETURNS BOOLEAN
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE v_stream_start TIME;
    DECLARE v_stream_end TIME;
    DECLARE v_break_start TIME;
    DECLARE v_break_end TIME;
    DECLARE v_slot_start TIME;
    DECLARE v_slot_end TIME;
    
    -- Get stream settings
    SELECT period_start, period_end, break_start, break_end
    INTO v_stream_start, v_stream_end, v_break_start, v_break_end
    FROM streams WHERE id = p_stream_id AND is_active = 1;
    
    -- Get time slot details
    SELECT start_time, end_time
    INTO v_slot_start, v_slot_end
    FROM time_slots WHERE id = p_time_slot_id AND is_active = 1;
    
    -- Check if slot is within stream period and not during break
    IF v_slot_start >= v_stream_start 
       AND v_slot_end <= v_stream_end 
       AND NOT (v_slot_start >= v_break_start AND v_slot_end <= v_break_end) THEN
        RETURN TRUE;
    ELSE
        RETURN FALSE;
    END IF;
END$$

DELIMITER ;

-- ============================================================================
-- STEP 8: CREATE CORRECTED VIEWS
-- ============================================================================

-- View showing classes with their stream context
CREATE OR REPLACE VIEW `classes_with_context` AS
SELECT 
    c.id,
    c.name as class_name,
    c.class_code,
    c.capacity,
    c.current_enrollment,
    c.academic_year,
    c.semester,
    c.stream_id,
    c.divisions_count,
    c.is_active,
    
    -- Department (global)
    d.name as department_name,
    d.code as department_code,
    
    -- Program (global)
    p.name as program_name,
    p.code as program_code,
    p.degree_type,
    
    -- Level (global)
    l.name as level_name,
    l.numeric_value as level_number,
    
    -- Stream context
    s.name as stream_name,
    s.code as stream_code,
    s.period_start,
    s.period_end,
    
    -- Calculated fields
    ROUND((c.current_enrollment * 100.0) / NULLIF(c.capacity, 0), 1) as enrollment_percentage,
    CASE 
        WHEN c.current_enrollment >= c.capacity THEN 'Full'
        WHEN c.current_enrollment >= (c.capacity * 0.9) THEN 'Nearly Full'
        WHEN c.current_enrollment >= (c.capacity * 0.5) THEN 'Half Full'
        ELSE 'Available'
    END as enrollment_status

FROM classes c
LEFT JOIN departments d ON c.department_id = d.id
LEFT JOIN programs p ON c.program_id = p.id
LEFT JOIN levels l ON c.level_id = l.id
LEFT JOIN streams s ON c.stream_id = s.id;

-- View for department-oriented course recommendations
CREATE OR REPLACE VIEW `course_recommendations` AS
SELECT 
    c.id as class_id,
    c.name as class_name,
    c.department_id as class_dept_id,
    c.level_id as class_level_id,
    co.id as course_id,
    co.course_code,
    co.course_name,
    co.department_id as course_dept_id,
    co.level_id as course_level_id,
    co.course_type,
    co.credits,
    
    -- Professional recommendation scoring
    (
        CASE WHEN c.department_id = co.department_id THEN 15 ELSE 0 END +
        CASE WHEN c.level_id = co.level_id THEN 15 ELSE -20 END +
        CASE WHEN co.course_type = 'core' THEN 10 ELSE 0 END +
        CASE WHEN co.course_type = 'elective' THEN 5 ELSE 0 END +
        CASE WHEN co.course_type = 'practical' THEN 8 ELSE 0 END
    ) as recommendation_score,
    
    -- Assignment status
    CASE 
        WHEN cc.id IS NOT NULL AND cc.is_active = 1 THEN 'assigned'
        WHEN c.department_id = co.department_id AND c.level_id = co.level_id THEN 'highly_recommended'
        WHEN c.level_id = co.level_id AND co.course_type IN ('elective', 'practical') THEN 'recommended'
        WHEN c.level_id = co.level_id THEN 'possible'
        ELSE 'not_suitable'
    END as recommendation_status,
    
    -- Compatibility indicators
    c.department_id = co.department_id as dept_match,
    c.level_id = co.level_id as level_match,
    
    -- Department names for display
    d1.name as class_department,
    d2.name as course_department

FROM classes c
CROSS JOIN courses co
LEFT JOIN departments d1 ON c.department_id = d1.id
LEFT JOIN departments d2 ON co.department_id = d2.id
LEFT JOIN class_courses cc ON c.id = cc.class_id AND co.id = cc.course_id AND cc.is_active = 1
WHERE c.is_active = 1 AND co.is_active = 1;

-- Comprehensive timetable view (corrected without stream_id conflicts)
CREATE OR REPLACE VIEW `timetable_comprehensive` AS
SELECT 
    t.id as timetable_id,
    
    -- Academic context
    t.semester,
    t.academic_year,
    t.division_label,
    
    -- Class information (stream-specific)
    c.id as class_id,
    c.name as class_name,
    c.stream_id,
    s.name as stream_name,
    s.code as stream_code,
    
    -- Course information (global)
    co.id as course_id,
    co.course_code,
    co.course_name,
    co.credits,
    co.course_type,
    
    -- Lecturer information (global)
    l.id as lecturer_id,
    l.name as lecturer_name,
    l.rank as lecturer_rank,
    
    -- Room information (global)
    r.id as room_id,
    r.name as room_name,
    r.room_type,
    r.capacity as room_capacity,
    r.building_name,
    
    -- Time information
    d.name as day_name,
    d.short_name as day_short,
    ts.start_time,
    ts.end_time,
    ts.slot_name,
    
    -- Department information
    dept.name as department_name,
    dept.code as department_code,
    
    -- Validation indicators
    CASE WHEN c.current_enrollment > r.capacity THEN 'overcapacity' ELSE 'ok' END as capacity_status,
    CASE WHEN dept_c.id = dept_co.id THEN 'same_dept' ELSE 'cross_dept' END as dept_assignment_type

FROM timetable t
JOIN class_courses cc ON t.class_course_id = cc.id
JOIN classes c ON cc.class_id = c.id
JOIN courses co ON cc.course_id = co.id
JOIN streams s ON c.stream_id = s.id
JOIN lecturer_courses lc ON t.lecturer_course_id = lc.id
JOIN lecturers l ON lc.lecturer_id = l.id
JOIN rooms r ON t.room_id = r.id
JOIN days d ON t.day_id = d.id
JOIN time_slots ts ON t.time_slot_id = ts.id
JOIN departments dept ON c.department_id = dept.id
LEFT JOIN departments dept_c ON c.department_id = dept_c.id
LEFT JOIN departments dept_co ON co.department_id = dept_co.id;

-- ============================================================================
-- STEP 9: UPDATE INDEXES FOR PERFORMANCE
-- ============================================================================

-- Remove old stream-based indexes from global tables
ALTER TABLE `courses` DROP INDEX IF EXISTS `idx_courses_stream_active`;
ALTER TABLE `lecturers` DROP INDEX IF EXISTS `idx_lecturers_stream_active`;
ALTER TABLE `rooms` DROP INDEX IF EXISTS `idx_rooms_stream_active`;
ALTER TABLE `departments` DROP INDEX IF EXISTS `idx_departments_stream_active`;

-- Add proper indexes for global tables
CREATE INDEX IF NOT EXISTS `idx_courses_dept_level_active` ON `courses`(`department_id`, `level_id`, `is_active`);
CREATE INDEX IF NOT EXISTS `idx_lecturers_dept_active` ON `lecturers`(`department_id`, `is_active`);
CREATE INDEX IF NOT EXISTS `idx_rooms_type_capacity` ON `rooms`(`room_type`, `capacity`, `is_active`);

-- Ensure classes table has proper stream-based indexes
CREATE INDEX IF NOT EXISTS `idx_classes_stream_active` ON `classes`(`stream_id`, `is_active`);
CREATE INDEX IF NOT EXISTS `idx_classes_dept_prog_level` ON `classes`(`department_id`, `program_id`, `level_id`);
CREATE INDEX IF NOT EXISTS `idx_classes_academic` ON `classes`(`academic_year`, `semester`, `is_active`);

-- Add indexes for class_courses (no stream_id needed)
CREATE INDEX IF NOT EXISTS `idx_cc_class_active` ON `class_courses`(`class_id`, `is_active`);
CREATE INDEX IF NOT EXISTS `idx_cc_course_active` ON `class_courses`(`course_id`, `is_active`);
CREATE INDEX IF NOT EXISTS `idx_cc_academic` ON `class_courses`(`academic_year`, `semester`, `is_active`);

-- Add indexes for timetable (no stream_id needed)
CREATE INDEX IF NOT EXISTS `idx_timetable_academic` ON `timetable`(`academic_year`, `semester`);
CREATE INDEX IF NOT EXISTS `idx_timetable_day_time` ON `timetable`(`day_id`, `time_slot_id`);

-- ============================================================================
-- STEP 10: CLEAN UP OLD DATA AND VALIDATE
-- ============================================================================

-- Remove any orphaned records
DELETE cc FROM class_courses cc 
LEFT JOIN classes c ON cc.class_id = c.id 
WHERE c.id IS NULL;

DELETE cc FROM class_courses cc 
LEFT JOIN courses co ON cc.course_id = co.id 
WHERE co.id IS NULL;

-- Update any missing academic year/semester in class_courses
UPDATE class_courses SET 
    academic_year = '2024/2025',
    semester = 'first'
WHERE academic_year IS NULL OR semester IS NULL;

-- Update any missing academic year/semester in classes
UPDATE classes SET 
    academic_year = '2024/2025',
    semester = 'first'
WHERE academic_year IS NULL OR semester IS NULL;

-- Ensure all classes have proper level_id and program_id
UPDATE classes c 
JOIN levels l ON (
    (c.level LIKE '%100%' AND l.numeric_value = 100) OR
    (c.level LIKE '%200%' AND l.numeric_value = 200) OR
    (c.level LIKE '%300%' AND l.numeric_value = 300) OR
    (c.level LIKE '%400%' AND l.numeric_value = 400)
)
SET c.level_id = l.id 
WHERE c.level_id IS NULL;

-- ============================================================================
-- STEP 11: CREATE MONITORING AND REPORTING
-- ============================================================================

-- Create table for monitoring assignment quality
CREATE TABLE IF NOT EXISTS `assignment_quality_report` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `class_id` INT NOT NULL,
    `course_id` INT NOT NULL,
    `department_match` BOOLEAN,
    `level_match` BOOLEAN,
    `course_type` VARCHAR(20),
    `quality_score` INT,
    `recommendation_status` VARCHAR(50),
    `issues` JSON DEFAULT NULL,
    `generated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    INDEX `idx_quality_score` (`quality_score`),
    INDEX `idx_quality_date` (`generated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Populate assignment quality report
INSERT INTO assignment_quality_report (
    class_id, course_id, department_match, level_match, 
    course_type, quality_score, recommendation_status
)
SELECT 
    cr.class_id,
    cr.course_id,
    cr.dept_match,
    cr.level_match,
    cr.course_type,
    cr.recommendation_score,
    cr.recommendation_status
FROM course_recommendations cr
WHERE cr.recommendation_status = 'assigned';

-- ============================================================================
-- STEP 12: FINAL VALIDATION AND SETUP
-- ============================================================================

-- Ensure streams have proper time slot mappings
INSERT IGNORE INTO stream_time_slots (stream_id, time_slot_id)
SELECT s.id, ts.id
FROM streams s
CROSS JOIN time_slots ts
WHERE s.is_active = 1 
AND ts.is_active = 1
AND (
    (s.id = 1 AND ts.start_time >= '08:00:00' AND ts.end_time <= '17:00:00' AND NOT ts.is_break_time) OR  -- Regular
    (s.id = 2 AND ts.start_time >= '09:00:00' AND ts.end_time <= '17:00:00' AND NOT ts.is_break_time) OR  -- Weekend
    (s.id = 3 AND ts.start_time >= '18:00:00' AND ts.end_time <= '20:00:00')                             -- Evening
);

-- Ensure streams have proper day mappings
INSERT IGNORE INTO stream_days (stream_id, day_id)
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