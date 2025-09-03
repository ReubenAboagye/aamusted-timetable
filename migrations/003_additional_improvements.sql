-- Migration 003: Additional Improvements and Optimizations
-- This migration adds final touches and optimizations

USE timetable_system;

-- 1. Create improved timetable view with all necessary joins
CREATE OR REPLACE VIEW `timetable_view` AS
SELECT 
    t.id,
    t.class_course_id,
    t.lecturer_course_id,
    t.day_id,
    t.time_slot_id,
    t.room_id,
    t.stream_id,
    t.academic_year,
    t.semester,
    t.division_label,
    
    -- Class information
    c.id as class_id,
    c.name as class_name,
    c.level as class_level,
    c.capacity as class_capacity,
    c.current_enrollment,
    
    -- Course information
    co.id as course_id,
    co.course_code,
    co.course_name,
    co.credits,
    co.hours_per_week,
    co.preferred_room_type,
    
    -- Lecturer information
    l.id as lecturer_id,
    l.name as lecturer_name,
    l.email as lecturer_email,
    l.rank as lecturer_rank,
    
    -- Room information
    r.name as room_name,
    r.capacity as room_capacity,
    r.room_type,
    r.building_name,
    
    -- Day information
    d.name as day_name,
    
    -- Time slot information
    ts.start_time,
    ts.end_time,
    ts.duration,
    
    -- Department information
    dept.name as department_name,
    dept.code as department_code,
    
    -- Stream information
    s.name as stream_name,
    s.code as stream_code

FROM timetable t
JOIN class_courses cc ON t.class_course_id = cc.id
JOIN classes c ON cc.class_id = c.id
JOIN courses co ON cc.course_id = co.id
JOIN lecturer_courses lc ON t.lecturer_course_id = lc.id
JOIN lecturers l ON lc.lecturer_id = l.id
JOIN rooms r ON t.room_id = r.id
JOIN days d ON t.day_id = d.id
JOIN time_slots ts ON t.time_slot_id = ts.id
JOIN departments dept ON c.department_id = dept.id
JOIN streams s ON t.stream_id = s.id
WHERE t.id IS NOT NULL;

-- 2. Create timetable conflicts view for easy monitoring
CREATE OR REPLACE VIEW `timetable_conflicts` AS
SELECT 
    'lecturer_conflict' as conflict_type,
    t1.id as timetable_id_1,
    t2.id as timetable_id_2,
    l.name as lecturer_name,
    d.name as day_name,
    ts.start_time,
    ts.end_time,
    CONCAT(c1.name, ' - ', co1.course_code) as assignment_1,
    CONCAT(c2.name, ' - ', co2.course_code) as assignment_2
FROM timetable t1
JOIN timetable t2 ON t1.day_id = t2.day_id 
                 AND t1.time_slot_id = t2.time_slot_id 
                 AND t1.id != t2.id
JOIN lecturer_courses lc1 ON t1.lecturer_course_id = lc1.id
JOIN lecturer_courses lc2 ON t2.lecturer_course_id = lc2.id
JOIN lecturers l ON lc1.lecturer_id = l.id AND lc2.lecturer_id = l.id
JOIN class_courses cc1 ON t1.class_course_id = cc1.id
JOIN class_courses cc2 ON t2.class_course_id = cc2.id
JOIN classes c1 ON cc1.class_id = c1.id
JOIN classes c2 ON cc2.class_id = c2.id
JOIN courses co1 ON cc1.course_id = co1.id
JOIN courses co2 ON cc2.course_id = co2.id
JOIN days d ON t1.day_id = d.id
JOIN time_slots ts ON t1.time_slot_id = ts.id

UNION ALL

SELECT 
    'room_conflict' as conflict_type,
    t1.id as timetable_id_1,
    t2.id as timetable_id_2,
    r.name as room_name,
    d.name as day_name,
    ts.start_time,
    ts.end_time,
    CONCAT(c1.name, ' - ', co1.course_code) as assignment_1,
    CONCAT(c2.name, ' - ', co2.course_code) as assignment_2
FROM timetable t1
JOIN timetable t2 ON t1.day_id = t2.day_id 
                 AND t1.time_slot_id = t2.time_slot_id 
                 AND t1.room_id = t2.room_id
                 AND t1.id != t2.id
JOIN rooms r ON t1.room_id = r.id
JOIN class_courses cc1 ON t1.class_course_id = cc1.id
JOIN class_courses cc2 ON t2.class_course_id = cc2.id
JOIN classes c1 ON cc1.class_id = c1.id
JOIN classes c2 ON cc2.class_id = c2.id
JOIN courses co1 ON cc1.course_id = co1.id
JOIN courses co2 ON cc2.course_id = co2.id
JOIN days d ON t1.day_id = d.id
JOIN time_slots ts ON t1.time_slot_id = ts.id;

-- 3. Create stored procedure for safe timetable insertion
DELIMITER $$

DROP PROCEDURE IF EXISTS `insert_timetable_entry`$$

CREATE PROCEDURE `insert_timetable_entry`(
    IN p_class_course_id INT,
    IN p_lecturer_course_id INT,
    IN p_day_id INT,
    IN p_time_slot_id INT,
    IN p_room_id INT,
    IN p_division_label VARCHAR(50),
    IN p_semester VARCHAR(20),
    IN p_academic_year VARCHAR(10),
    OUT p_result VARCHAR(100)
)
BEGIN
    DECLARE v_stream_id INT;
    DECLARE v_conflicts JSON;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_result = 'ERROR: Database error occurred';
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Get stream_id from class_courses
    SELECT stream_id INTO v_stream_id FROM class_courses WHERE id = p_class_course_id;
    
    IF v_stream_id IS NULL THEN
        SET p_result = 'ERROR: Invalid class_course_id';
        ROLLBACK;
    ELSE
        -- Check for conflicts using the function
        SET v_conflicts = check_timetable_conflicts(p_class_course_id, p_lecturer_course_id, p_day_id, p_time_slot_id, p_room_id, p_division_label);
        
        IF JSON_LENGTH(v_conflicts) > 0 THEN
            SET p_result = CONCAT('CONFLICT: ', CAST(v_conflicts AS CHAR));
            ROLLBACK;
        ELSE
            -- Insert the timetable entry
            INSERT INTO timetable (
                class_course_id, lecturer_course_id, day_id, time_slot_id, room_id, 
                stream_id, division_label, semester, academic_year
            ) VALUES (
                p_class_course_id, p_lecturer_course_id, p_day_id, p_time_slot_id, p_room_id,
                v_stream_id, p_division_label, p_semester, p_academic_year
            );
            
            SET p_result = 'SUCCESS';
            COMMIT;
        END IF;
    END IF;
END$$

DELIMITER ;

-- 4. Add additional indexes for better performance
CREATE INDEX IF NOT EXISTS `idx_timetable_stream_day_time` ON `timetable`(`stream_id`, `day_id`, `time_slot_id`);
CREATE INDEX IF NOT EXISTS `idx_timetable_semester_year` ON `timetable`(`semester`, `academic_year`);
CREATE INDEX IF NOT EXISTS `idx_class_courses_semester_year` ON `class_courses`(`semester`, `academic_year`, `is_active`);
CREATE INDEX IF NOT EXISTS `idx_lecturer_courses_active` ON `lecturer_courses`(`is_active`, `lecturer_id`, `course_id`);

-- 5. Create summary statistics view
CREATE OR REPLACE VIEW `stream_statistics` AS
SELECT 
    s.id as stream_id,
    s.name as stream_name,
    s.code as stream_code,
    s.is_active as stream_active,
    
    -- Counts
    (SELECT COUNT(*) FROM classes WHERE stream_id = s.id AND is_active = 1) as total_classes,
    (SELECT COUNT(*) FROM courses WHERE stream_id = s.id AND is_active = 1) as total_courses,
    (SELECT COUNT(*) FROM lecturers WHERE stream_id = s.id AND is_active = 1) as total_lecturers,
    (SELECT COUNT(*) FROM rooms WHERE stream_id = s.id AND is_active = 1) as total_rooms,
    (SELECT COUNT(*) FROM class_courses cc JOIN classes c ON cc.class_id = c.id WHERE c.stream_id = s.id AND cc.is_active = 1) as total_assignments,
    (SELECT COUNT(*) FROM timetable WHERE stream_id = s.id) as total_timetable_entries,
    
    -- Utilization metrics
    (SELECT COUNT(DISTINCT t.room_id) FROM timetable t WHERE t.stream_id = s.id) as rooms_in_use,
    (SELECT COUNT(DISTINCT lc.lecturer_id) FROM timetable t JOIN lecturer_courses lc ON t.lecturer_course_id = lc.id WHERE t.stream_id = s.id) as lecturers_in_use,
    
    -- Time range
    s.period_start,
    s.period_end,
    s.break_start,
    s.break_end,
    
    -- Calculated metrics
    ROUND(
        (SELECT COUNT(DISTINCT t.room_id) FROM timetable t WHERE t.stream_id = s.id) * 100.0 / 
        NULLIF((SELECT COUNT(*) FROM rooms WHERE stream_id = s.id AND is_active = 1), 0), 
        2
    ) as room_utilization_percent,
    
    ROUND(
        (SELECT COUNT(DISTINCT lc.lecturer_id) FROM timetable t JOIN lecturer_courses lc ON t.lecturer_course_id = lc.id WHERE t.stream_id = s.id) * 100.0 / 
        NULLIF((SELECT COUNT(*) FROM lecturers WHERE stream_id = s.id AND is_active = 1), 0), 
        2
    ) as lecturer_utilization_percent

FROM streams s
ORDER BY s.id;

-- 6. Add validation for room capacity vs class enrollment
DELIMITER $$

DROP TRIGGER IF EXISTS `validate_room_capacity`$$

CREATE TRIGGER `validate_room_capacity`
BEFORE INSERT ON `timetable`
FOR EACH ROW
BEGIN
    DECLARE v_room_capacity INT;
    DECLARE v_class_enrollment INT;
    DECLARE v_room_name VARCHAR(100);
    DECLARE v_class_name VARCHAR(100);
    
    -- Get room capacity
    SELECT capacity, name INTO v_room_capacity, v_room_name FROM rooms WHERE id = NEW.room_id;
    
    -- Get class enrollment
    SELECT c.current_enrollment, c.name INTO v_class_enrollment, v_class_name
    FROM class_courses cc 
    JOIN classes c ON cc.class_id = c.id 
    WHERE cc.id = NEW.class_course_id;
    
    -- Check capacity
    IF v_class_enrollment > v_room_capacity THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = CONCAT('Room capacity exceeded: ', v_room_name, ' (capacity: ', v_room_capacity, ') cannot accommodate ', v_class_name, ' (enrollment: ', v_class_enrollment, ')');
    END IF;
END$$

DELIMITER ;

-- 7. Create cleanup procedure for removing invalid data
DELIMITER $$

DROP PROCEDURE IF EXISTS `cleanup_invalid_stream_data`$$

CREATE PROCEDURE `cleanup_invalid_stream_data`()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_count INT;
    
    -- Remove class_courses with stream inconsistencies
    DELETE cc FROM class_courses cc
    JOIN classes c ON cc.class_id = c.id
    JOIN courses co ON cc.course_id = co.id
    WHERE c.stream_id != co.stream_id;
    
    SELECT ROW_COUNT() INTO v_count;
    SELECT CONCAT('Removed ', v_count, ' invalid class_courses entries') as cleanup_result;
    
    -- Remove lecturer_courses with stream inconsistencies
    DELETE lc FROM lecturer_courses lc
    JOIN lecturers l ON lc.lecturer_id = l.id
    JOIN courses co ON lc.course_id = co.id
    WHERE l.stream_id != co.stream_id;
    
    SELECT ROW_COUNT() INTO v_count;
    SELECT CONCAT('Removed ', v_count, ' invalid lecturer_courses entries') as cleanup_result;
    
    -- Remove timetable entries with stream inconsistencies
    DELETE t FROM timetable t
    JOIN class_courses cc ON t.class_course_id = cc.id
    JOIN classes c ON cc.class_id = c.id
    JOIN lecturer_courses lc ON t.lecturer_course_id = lc.id
    JOIN lecturers l ON lc.lecturer_id = l.id
    JOIN rooms r ON t.room_id = r.id
    WHERE c.stream_id != l.stream_id 
       OR c.stream_id != r.stream_id 
       OR t.stream_id != c.stream_id;
    
    SELECT ROW_COUNT() INTO v_count;
    SELECT CONCAT('Removed ', v_count, ' invalid timetable entries') as cleanup_result;
END$$

DELIMITER ;

-- 8. Add logging table for timetable generation
CREATE TABLE IF NOT EXISTS `timetable_generation_log` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `stream_id` INT NOT NULL,
    `semester` VARCHAR(20) DEFAULT 'current',
    `academic_year` VARCHAR(10) DEFAULT '2024/2025',
    `total_assignments` INT DEFAULT 0,
    `successful_placements` INT DEFAULT 0,
    `failed_placements` INT DEFAULT 0,
    `conflicts_detected` INT DEFAULT 0,
    `generation_time_seconds` DECIMAL(10,2) DEFAULT 0,
    `generated_by` VARCHAR(100),
    `generation_notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    FOREIGN KEY (`stream_id`) REFERENCES `streams`(`id`) ON DELETE CASCADE,
    INDEX `idx_log_stream_date` (`stream_id`, `created_at`),
    INDEX `idx_log_semester_year` (`semester`, `academic_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 9. Create function to get stream utilization
DELIMITER $$

DROP FUNCTION IF EXISTS `get_stream_utilization`$$

CREATE FUNCTION `get_stream_utilization`(p_stream_id INT) 
RETURNS JSON
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE v_total_slots INT DEFAULT 0;
    DECLARE v_used_slots INT DEFAULT 0;
    DECLARE v_total_rooms INT DEFAULT 0;
    DECLARE v_used_rooms INT DEFAULT 0;
    DECLARE v_total_lecturers INT DEFAULT 0;
    DECLARE v_used_lecturers INT DEFAULT 0;
    DECLARE v_result JSON;
    
    -- Calculate total available slots (days * time_slots * rooms)
    SELECT COUNT(*) INTO v_total_slots
    FROM stream_days sd
    JOIN stream_time_slots sts ON sd.stream_id = sts.stream_id
    JOIN rooms r ON r.stream_id = sd.stream_id
    WHERE sd.stream_id = p_stream_id AND sd.is_active = 1 AND sts.is_active = 1 AND r.is_active = 1;
    
    -- Calculate used slots
    SELECT COUNT(*) INTO v_used_slots
    FROM timetable t
    WHERE t.stream_id = p_stream_id;
    
    -- Total and used rooms
    SELECT COUNT(*) INTO v_total_rooms FROM rooms WHERE stream_id = p_stream_id AND is_active = 1;
    SELECT COUNT(DISTINCT room_id) INTO v_used_rooms FROM timetable WHERE stream_id = p_stream_id;
    
    -- Total and used lecturers
    SELECT COUNT(*) INTO v_total_lecturers FROM lecturers WHERE stream_id = p_stream_id AND is_active = 1;
    SELECT COUNT(DISTINCT lc.lecturer_id) INTO v_used_lecturers 
    FROM timetable t 
    JOIN lecturer_courses lc ON t.lecturer_course_id = lc.id 
    WHERE t.stream_id = p_stream_id;
    
    SET v_result = JSON_OBJECT(
        'total_slots', v_total_slots,
        'used_slots', v_used_slots,
        'slot_utilization_percent', ROUND(v_used_slots * 100.0 / NULLIF(v_total_slots, 0), 2),
        'total_rooms', v_total_rooms,
        'used_rooms', v_used_rooms,
        'room_utilization_percent', ROUND(v_used_rooms * 100.0 / NULLIF(v_total_rooms, 0), 2),
        'total_lecturers', v_total_lecturers,
        'used_lecturers', v_used_lecturers,
        'lecturer_utilization_percent', ROUND(v_used_lecturers * 100.0 / NULLIF(v_total_lecturers, 0), 2)
    );
    
    RETURN v_result;
END$$

DELIMITER ;

-- 10. Add some useful indexes for reporting and analytics
CREATE INDEX IF NOT EXISTS `idx_classes_department_stream` ON `classes`(`department_id`, `stream_id`, `is_active`);
CREATE INDEX IF NOT EXISTS `idx_courses_department_stream` ON `courses`(`department_id`, `stream_id`, `is_active`);
CREATE INDEX IF NOT EXISTS `idx_courses_level_stream` ON `courses`(`level`, `stream_id`, `is_active`);
CREATE INDEX IF NOT EXISTS `idx_timetable_created_at` ON `timetable`(`created_at`);

-- 11. Update existing data to ensure consistency
UPDATE class_courses cc 
JOIN classes c ON cc.class_id = c.id 
SET cc.stream_id = c.stream_id 
WHERE cc.stream_id != c.stream_id;

-- 12. Add constraint to prevent future inconsistencies in timetable
DELIMITER $$

DROP TRIGGER IF EXISTS `validate_timetable_stream_consistency`$$

CREATE TRIGGER `validate_timetable_stream_consistency`
BEFORE INSERT ON `timetable`
FOR EACH ROW
BEGIN
    DECLARE v_class_stream INT;
    DECLARE v_course_stream INT;
    DECLARE v_lecturer_stream INT;
    DECLARE v_room_stream INT;
    
    -- Get all stream IDs
    SELECT c.stream_id INTO v_class_stream
    FROM class_courses cc 
    JOIN classes c ON cc.class_id = c.id 
    WHERE cc.id = NEW.class_course_id;
    
    SELECT co.stream_id INTO v_course_stream
    FROM class_courses cc 
    JOIN courses co ON cc.course_id = co.id 
    WHERE cc.id = NEW.class_course_id;
    
    SELECT l.stream_id INTO v_lecturer_stream
    FROM lecturer_courses lc 
    JOIN lecturers l ON lc.lecturer_id = l.id 
    WHERE lc.id = NEW.lecturer_course_id;
    
    SELECT stream_id INTO v_room_stream FROM rooms WHERE id = NEW.room_id;
    
    -- Validate all streams match
    IF v_class_stream != v_course_stream OR v_class_stream != v_lecturer_stream OR v_class_stream != v_room_stream THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Stream mismatch in timetable entry: all entities must belong to the same stream';
    END IF;
    
    -- Set the stream_id automatically
    SET NEW.stream_id = v_class_stream;
END$$

DELIMITER ;

-- 13. Final data consistency check and fix
-- Ensure all stream_id fields are properly set
UPDATE timetable t 
JOIN class_courses cc ON t.class_course_id = cc.id 
JOIN classes c ON cc.class_id = c.id 
SET t.stream_id = c.stream_id 
WHERE t.stream_id IS NULL OR t.stream_id != c.stream_id;