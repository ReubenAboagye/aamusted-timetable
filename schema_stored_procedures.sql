-- 1. Generate timetable for specific semester and session
DELIMITER //
CREATE PROCEDURE GenerateTimetable(
    IN p_semester_id INT,
    IN p_session_type VARCHAR(20)
)
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_class_id INT; 
    DECLARE v_course_id INT;
    DECLARE v_lecturer_id INT;
    DECLARE v_room_id INT;
    DECLARE v_time_slot_id INT;
    DECLARE v_day VARCHAR(20);
    
    -- Cursor for classes in the specified session
    DECLARE class_cursor CURSOR FOR 
        SELECT c.id FROM classes c
        JOIN sessions s ON c.session_id = s.id
        WHERE s.type = p_session_type AND c.is_active = TRUE;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    -- Clear existing timetable for this semester/session
    DELETE FROM timetable 
    WHERE semester_id = p_semester_id 
        AND session_type = p_session_type;
    
    OPEN class_cursor;
    
    read_loop: LOOP
        FETCH class_cursor INTO v_class_id;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        -- Get courses for this class from class_courses mapping
        SELECT cc.course_id INTO v_course_id 
        FROM class_courses cc
        WHERE cc.class_id = v_class_id 
            AND cc.semester_id = p_semester_id
            AND cc.is_active = TRUE
        LIMIT 1;
        
        IF v_course_id IS NOT NULL THEN
            -- Find available lecturer from lecturer_courses mapping
            SELECT lc.lecturer_id INTO v_lecturer_id
            FROM lecturer_courses lc
            JOIN lecturers l ON lc.lecturer_id = l.id
            WHERE lc.course_id = v_course_id 
                AND JSON_CONTAINS(l.session_availability, JSON_QUOTE(p_session_type))
                AND l.is_active = TRUE
                AND lc.is_active = TRUE
            LIMIT 1;
            
            -- Find available room
            SELECT r.id INTO v_room_id
            FROM rooms r
            WHERE JSON_CONTAINS(r.session_availability, JSON_QUOTE(p_session_type))
                AND r.is_active = TRUE
            LIMIT 1;
            
            -- Find available time slot (exclude break slots)
            SELECT ts.id INTO v_time_slot_id
            FROM time_slots ts
            WHERE ts.is_break = FALSE AND ts.slot_type != 'break'
            LIMIT 1;
            
            -- Get working days for this session
            SELECT JSON_UNQUOTE(JSON_EXTRACT(s.working_days, '$[0]')) INTO v_day
            FROM sessions s
            JOIN classes c ON s.id = c.session_id
            WHERE c.id = v_class_id;
            
            -- Insert timetable entry
            IF v_lecturer_id IS NOT NULL AND v_room_id IS NOT NULL AND v_time_slot_id IS NOT NULL THEN
                INSERT INTO timetable (class_id, course_id, lecturer_id, room_id, time_slot_id, day, semester_id, session_type)
                VALUES (v_class_id, v_course_id, v_lecturer_id, v_room_id, v_time_slot_id, v_day, p_semester_id, p_session_type);
                
                -- Add primary lecturer to timetable_lecturers
                INSERT INTO timetable_lecturers (timetable_id, lecturer_id, role)
                VALUES (LAST_INSERT_ID(), v_lecturer_id, 'primary');
            END IF;
        END IF;
        
    END LOOP;
    
    CLOSE class_cursor;
    
    -- Return summary
    SELECT 
        COUNT(*) as total_entries,
        p_semester_id as semester_id,
        p_session_type as session_type
    FROM timetable 
    WHERE semester_id = p_semester_id 
        AND session_type = p_session_type;
    
END //
DELIMITER ;

-- 2. Check for scheduling conflicts
DELIMITER //
CREATE PROCEDURE CheckConflicts(
    IN p_semester_id INT
)
BEGIN
    -- Check class conflicts (same class scheduled multiple times)
    SELECT 
        'class_conflict' as conflict_type,
        c.name as class_name,
        t1.day,
        ts1.start_time,
        ts1.end_time,
        co1.name as course1,
        co2.name as course2
    FROM timetable t1
    JOIN timetable t2 ON t1.class_id = t2.class_id 
        AND t1.day = t2.day 
        AND t1.time_slot_id = t2.time_slot_id
        AND t1.id < t2.id
    JOIN classes c ON t1.class_id = c.id
    JOIN courses co1 ON t1.course_id = co1.id
    JOIN courses co2 ON t2.course_id = co2.id
    JOIN time_slots ts1 ON t1.time_slot_id = ts1.id
    WHERE t1.semester_id = p_semester_id
    
    UNION ALL
    
    -- Check room conflicts (same room used multiple times)
    SELECT 
        'room_conflict' as conflict_type,
        r.name as room_name,
        t1.day,
        ts1.start_time,
        ts1.end_time,
        co1.name as course1,
        co2.name as course2
    FROM timetable t1
    JOIN timetable t2 ON t1.room_id = t2.room_id 
        AND t1.day = t2.day 
        AND t1.time_slot_id = t2.time_slot_id
        AND t1.id < t2.id
    JOIN rooms r ON t1.room_id = r.id
    JOIN courses co1 ON t1.course_id = co1.id
    JOIN courses co2 ON t2.course_id = co2.id
    JOIN time_slots ts1 ON t1.time_slot_id = ts1.id
    WHERE t1.semester_id = p_semester_id
    
    UNION ALL
    
    -- Check lecturer conflicts (same lecturer teaching multiple times)
    SELECT 
        'lecturer_conflict' as conflict_type,
        l.name as lecturer_name,
        t1.day,
        ts1.start_time,
        ts1.end_time,
        co1.name as course1,
        co2.name as course2
    FROM timetable t1
    JOIN timetable t2 ON t1.lecturer_id = t2.lecturer_id 
        AND t1.day = t2.day 
        AND t1.time_slot_id = t2.time_slot_id
        AND t1.id < t2.id
    JOIN lecturers l ON t1.lecturer_id = l.id
    JOIN courses co1 ON t1.course_id = co1.id
    JOIN courses co2 ON t2.course_id = co2.id
    JOIN time_slots ts1 ON t1.time_slot_id = ts1.id
    WHERE t1.semester_id = p_semester_id;
END //
DELIMITER ;

-- 3. Get timetable for specific criteria
DELIMITER //
CREATE PROCEDURE GetTimetable(
    IN p_semester_id INT,
    IN p_session_type VARCHAR(20),
    IN p_class_id INT DEFAULT NULL,
    IN p_department_id INT DEFAULT NULL
)
BEGIN
    SELECT 
        t.day,
        ts.start_time,
        ts.end_time,
        c.name as class_name,
        c.level as class_level,
        co.name as course_name,
        co.code as course_code,
        l.name as lecturer_name,
        r.name as room_name,
        r.building,
        d.name as department_name,
        s.name as semester_name,
        t.is_confirmed,
        t.notes
    FROM timetable t
    JOIN classes c ON t.class_id = c.id
    JOIN courses co ON t.course_id = co.id
    JOIN lecturers l ON t.lecturer_id = l.id
    JOIN rooms r ON t.room_id = r.id
    JOIN time_slots ts ON t.time_slot_id = ts.id
    JOIN departments d ON c.department_id = d.id
    JOIN semesters s ON t.semester_id = s.id
    WHERE t.semester_id = p_semester_id 
        AND t.session_type = p_session_type
        AND (p_class_id IS NULL OR t.class_id = p_class_id)
        AND (p_department_id IS NULL OR c.department_id = p_department_id)
    ORDER BY 
        FIELD(t.day, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'),
        ts.start_time;
END //
DELIMITER ;

-- 4. Optimize timetable (resolve conflicts)
DELIMITER //
CREATE PROCEDURE OptimizeTimetable(
    IN p_semester_id INT,
    IN p_session_type VARCHAR(20)
)
BEGIN
    DECLARE v_conflict_count INT DEFAULT 0;
    DECLARE v_iterations INT DEFAULT 0;
    DECLARE max_iterations INT DEFAULT 100;
    
    -- Count initial conflicts
    SELECT COUNT(*) INTO v_conflict_count
    FROM (
        SELECT COUNT(*) as conflicts
        FROM timetable t1
        JOIN timetable t2 ON t1.class_id = t2.class_id 
            AND t1.day = t2.day 
            AND t1.time_slot_id = t2.time_slot_id
            AND t1.id < t2.id
        WHERE t1.semester_id = p_semester_id 
            AND t1.session_type = p_session_type
    ) as conflict_check;
    
    -- Try to resolve conflicts by swapping time slots
    WHILE v_conflict_count > 0 AND v_iterations < max_iterations DO
        SET v_iterations = v_iterations + 1;
        
        -- Find a conflict and try to resolve it
        UPDATE timetable t1
        JOIN (
            SELECT t1.id, t1.time_slot_id, t2.time_slot_id as new_time_slot
            FROM timetable t1
            JOIN timetable t2 ON t1.class_id = t2.class_id 
                AND t1.day = t2.day 
                AND t1.time_slot_id != t2.time_slot_id
            WHERE t1.semester_id = p_semester_id 
                AND t1.session_type = p_session_type
            LIMIT 1
        ) as conflict ON t1.id = conflict.id
        SET t1.time_slot_id = conflict.new_time_slot
        WHERE t1.id = conflict.id;
        
        -- Recalculate conflicts
        SELECT COUNT(*) INTO v_conflict_count
        FROM (
            SELECT COUNT(*) as conflicts
            FROM timetable t1
            JOIN timetable t2 ON t1.class_id = t2.class_id 
                AND t1.day = t2.day 
                AND t1.time_slot_id = t2.time_slot_id
                AND t1.id < t2.id
            WHERE t1.semester_id = p_semester_id 
                AND t1.session_type = p_session_type
        ) as conflict_check;
        
    END WHILE;
    
    -- Return optimization results
    SELECT 
        v_conflict_count as remaining_conflicts,
        v_iterations as iterations_performed,
        CASE 
            WHEN v_conflict_count = 0 THEN 'Optimization successful'
            ELSE 'Optimization incomplete - conflicts remain'
        END as status;
    
END //
DELIMITER ;

-- 5. Add course to timetable
DELIMITER //
CREATE PROCEDURE AddCourseToTimetable(
    IN p_class_id INT,
    IN p_course_id INT,
    IN p_semester_id INT,
    IN p_session_type VARCHAR(20),
    IN p_day VARCHAR(20),
    IN p_time_slot_id INT
)
BEGIN
    DECLARE v_lecturer_id INT;
    DECLARE v_room_id INT;
    DECLARE v_conflict_count INT DEFAULT 0;
    
    -- Check if course is assigned to this class
    IF NOT EXISTS (SELECT 1 FROM class_courses WHERE class_id = p_class_id AND course_id = p_course_id AND semester_id = p_semester_id) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Course not assigned to this class for the specified semester';
    END IF;
    
    -- Check for conflicts
    SELECT COUNT(*) INTO v_conflict_count
    FROM timetable
    WHERE (class_id = p_class_id OR room_id = p_room_id OR lecturer_id = p_lecturer_id)
        AND day = p_day 
        AND time_slot_id = p_time_slot_id
        AND semester_id = p_semester_id;
    
    IF v_conflict_count > 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Scheduling conflict detected';
    ELSE
        -- Find available lecturer from lecturer_courses mapping
        SELECT lc.lecturer_id INTO v_lecturer_id
        FROM lecturer_courses lc
        JOIN lecturers l ON lc.lecturer_id = l.id
        WHERE lc.course_id = p_course_id 
            AND JSON_CONTAINS(l.session_availability, JSON_QUOTE(p_session_type))
            AND l.is_active = TRUE
            AND lc.is_active = TRUE
        LIMIT 1;
        
        -- Find available room
        SELECT r.id INTO v_room_id
        FROM rooms r
        WHERE JSON_CONTAINS(r.session_availability, JSON_QUOTE(p_session_type))
            AND r.is_active = TRUE
        LIMIT 1;
        
        -- Insert timetable entry
        INSERT INTO timetable (class_id, course_id, lecturer_id, room_id, time_slot_id, day, semester_id, session_type)
        VALUES (p_class_id, p_course_id, v_lecturer_id, v_room_id, p_time_slot_id, p_day, p_semester_id, p_session_type);
        
        -- Add primary lecturer to timetable_lecturers
        INSERT INTO timetable_lecturers (timetable_id, lecturer_id, role)
        VALUES (LAST_INSERT_ID(), v_lecturer_id, 'primary');
        
        SELECT 'Course added successfully' as message;
    END IF;
    
END //
DELIMITER ;

-- 6. Get room availability
DELIMITER //
CREATE PROCEDURE GetRoomAvailability(
    IN p_room_id INT,
    IN p_day VARCHAR(20),
    IN p_semester_id INT
)
BEGIN
    SELECT 
        ts.start_time,
        ts.end_time,
        CASE 
            WHEN t.id IS NULL THEN 'Available'
            ELSE CONCAT('Booked: ', co.name, ' (', c.name, ')')
        END as status,
        t.class_id,
        t.course_id,
        c.name as class_name,
        co.name as course_name
    FROM time_slots ts
    LEFT JOIN timetable t ON ts.id = t.time_slot_id 
        AND t.room_id = p_room_id 
        AND t.day = p_day
        AND t.semester_id = p_semester_id
    LEFT JOIN classes c ON t.class_id = c.id
    LEFT JOIN courses co ON t.course_id = co.id
    WHERE ts.is_break = FALSE AND ts.slot_type != 'break'
    ORDER BY ts.start_time;
END //
DELIMITER ;

-- 7. Get lecturer workload
DELIMITER //
CREATE PROCEDURE GetLecturerWorkload(
    IN p_lecturer_id INT,
    IN p_semester_id INT
)
BEGIN
    SELECT 
        l.name as lecturer_name,
        d.name as department_name,
        COUNT(t.id) as total_courses,
        COUNT(DISTINCT t.day) as working_days,
        SUM(ts.duration) as total_hours,
        GROUP_CONCAT(DISTINCT t.session_type) as sessions_teaching
    FROM lecturers l
    JOIN departments d ON l.department_id = d.id
    LEFT JOIN timetable t ON l.id = t.lecturer_id
    LEFT JOIN time_slots ts ON t.time_slot_id = ts.id
    WHERE l.id = p_lecturer_id
        AND (t.semester_id = p_semester_id OR t.semester_id IS NULL)
    GROUP BY l.id;
END //
DELIMITER ;

-- 8. Validate timetable constraints
DELIMITER //
CREATE PROCEDURE ValidateTimetableConstraints(
    IN p_semester_id INT,
    IN p_session_type VARCHAR(20)
)
BEGIN
    DECLARE v_violations INT DEFAULT 0;
    
    -- Check for constraint violations
    SELECT COUNT(*) INTO v_violations
    FROM (
        -- Check if classes exceed max daily courses
        SELECT c.id, COUNT(t.id) as daily_courses
        FROM classes c
        JOIN timetable t ON c.id = t.class_id
        WHERE t.semester_id = p_semester_id 
            AND t.session_type = p_session_type
        GROUP BY c.id, t.day
        HAVING daily_courses > c.max_daily_courses
        
        UNION ALL
        
        -- Check if lecturers exceed max daily courses
        SELECT l.id, COUNT(t.id) as daily_courses
        FROM lecturers l
        JOIN timetable t ON l.id = t.lecturer_id
        WHERE t.semester_id = p_semester_id 
            AND t.session_type = p_session_type
        GROUP BY l.id, t.day
        HAVING daily_courses > l.max_daily_courses
        
        UNION ALL
        
        -- Check if rooms exceed capacity
        SELECT r.id, COUNT(t.id) as room_usage
        FROM rooms r
        JOIN timetable t ON r.id = t.room_id
        JOIN classes c ON t.class_id = c.id
        WHERE t.semester_id = p_semester_id 
            AND t.session_type = p_session_type
        GROUP BY r.id, t.day, t.time_slot_id
        HAVING room_usage > 1
    ) as violations;
    
    -- Return validation results
    SELECT 
        v_violations as total_violations,
        CASE 
            WHEN v_violations = 0 THEN 'All constraints satisfied'
            ELSE CONCAT(v_violations, ' constraint violations found')
        END as status;
    
END //
DELIMITER ;

-- 9. Generate weekly report
DELIMITER //
CREATE PROCEDURE GenerateWeeklyReport(
    IN p_semester_id INT,
    IN p_session_type VARCHAR(20),
    IN p_week_number INT DEFAULT 1
)
BEGIN
    SELECT 
        t.day,
        COUNT(t.id) as total_courses,
        COUNT(DISTINCT t.class_id) as total_classes,
        COUNT(DISTINCT t.lecturer_id) as total_lecturers,
        COUNT(DISTINCT t.room_id) as total_rooms,
        SUM(ts.duration) as total_hours
    FROM timetable t
    JOIN time_slots ts ON t.time_slot_id = ts.id
    WHERE t.semester_id = p_semester_id 
        AND t.session_type = p_session_type
        AND t.week_number = p_week_number
    GROUP BY t.day
    ORDER BY FIELD(t.day, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday');
    
    -- Department breakdown
    SELECT 
        d.name as department_name,
        COUNT(t.id) as total_courses,
        COUNT(DISTINCT t.class_id) as total_classes
    FROM timetable t
    JOIN classes c ON t.class_id = c.id
    JOIN departments d ON c.department_id = d.id
    WHERE t.semester_id = p_semester_id 
        AND t.session_type = p_session_type
        AND t.week_number = p_week_number
    GROUP BY d.id
    ORDER BY total_courses DESC;
    
END //
DELIMITER ;

-- 10. Clean up timetable
DELIMITER //
CREATE PROCEDURE CleanupTimetable(
    IN p_semester_id INT
)
BEGIN
    DECLARE v_deleted_count INT DEFAULT 0;
    
    -- Delete entries with invalid references
    DELETE t FROM timetable t
    LEFT JOIN classes c ON t.class_id = c.id
    LEFT JOIN courses co ON t.course_id = co.id
    LEFT JOIN lecturers l ON t.lecturer_id = l.id
    LEFT JOIN rooms r ON t.room_id = r.id
    LEFT JOIN time_slots ts ON t.time_slot_id = ts.id
    WHERE t.semester_id = p_semester_id
        AND (c.id IS NULL OR co.id IS NULL OR l.id IS NULL OR r.id IS NULL OR ts.id IS NULL);
    
    SET v_deleted_count = ROW_COUNT();
    
    -- Return cleanup results
    SELECT 
        v_deleted_count as deleted_entries,
        'Cleanup completed' as message;
    
END //
DELIMITER ;

-- 11. Add semester
DELIMITER //
CREATE PROCEDURE AddSemester(
    IN p_name VARCHAR(50),
    IN p_academic_year VARCHAR(10),
    IN p_start_date DATE,
    IN p_end_date DATE
)
BEGIN
    INSERT INTO semesters (name, academic_year, start_date, end_date)
    VALUES (p_name, p_academic_year, p_start_date, p_end_date);
    
    SELECT 'Semester added successfully' as message, LAST_INSERT_ID() as semester_id;
END //
DELIMITER ;

-- 12. Assign course to class
DELIMITER //
CREATE PROCEDURE AssignCourseToClass(
    IN p_class_id INT,
    IN p_course_id INT,
    IN p_semester_id INT
)
BEGIN
    INSERT INTO class_courses (class_id, course_id, semester_id)
    VALUES (p_class_id, p_course_id, p_semester_id)
    ON DUPLICATE KEY UPDATE is_active = TRUE;
    
    SELECT 'Course assigned to class successfully' as message;
END //
DELIMITER ;

-- 13. Assign lecturer to course
DELIMITER //
CREATE PROCEDURE AssignLecturerToCourse(
    IN p_lecturer_id INT,
    IN p_course_id INT
)
BEGIN
    INSERT INTO lecturer_courses (lecturer_id, course_id)
    VALUES (p_lecturer_id, p_course_id)
    ON DUPLICATE KEY UPDATE is_active = TRUE;
    
    SELECT 'Lecturer assigned to course successfully' as message;
END //
DELIMITER ;

-- 14. Get semester information
DELIMITER //
CREATE PROCEDURE GetSemesterInfo(
    IN p_semester_id INT
)
BEGIN
    SELECT 
        s.*,
        COUNT(DISTINCT t.class_id) as total_classes,
        COUNT(DISTINCT t.course_id) as total_courses,
        COUNT(DISTINCT t.lecturer_id) as total_lecturers
    FROM semesters s
    LEFT JOIN timetable t ON s.id = t.semester_id
    WHERE s.id = p_semester_id
    GROUP BY s.id;
END //
DELIMITER ;