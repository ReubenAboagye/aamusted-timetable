-- View 1: Complete timetable overview
CREATE VIEW `complete_timetable` AS
SELECT 
    t.id,
    t.semester,
    t.academic_year,
    t.session_type,
    t.day,
    t.week_number,
    ts.start_time,
    ts.end_time,
    ts.duration,
    c.name as class_name,
    c.level as class_level,
    c.capacity as class_capacity,
    c.current_enrollment,
    co.name as course_name,
    co.code as course_code,
    co.credits,
    co.hours_per_week,
    l.name as lecturer_name,
    l.email as lecturer_email,
    r.name as room_name,
    r.building,
    r.room_type,
    r.capacity as room_capacity,
    d.name as department_name,
    d.code as department_code,
    t.is_confirmed,
    t.notes,
    t.created_at
FROM timetable t
JOIN classes c ON t.class_id = c.id
JOIN courses co ON t.course_id = co.id
JOIN lecturers l ON t.lecturer_id = l.id
JOIN rooms r ON t.room_id = r.id
JOIN time_slots ts ON t.time_slot_id = ts.id
JOIN departments d ON c.department_id = d.id;

-- View 2: Session overview with statistics
CREATE VIEW `session_overview` AS
SELECT 
    s.id as session_id,
    s.name as session_name,
    s.type as session_type,
    s.is_active,
    s.start_time,
    s.end_time,
    COUNT(DISTINCT t.id) as total_courses,
    COUNT(DISTINCT t.class_id) as total_classes,
    COUNT(DISTINCT t.room_id) as total_rooms_used,
    COUNT(DISTINCT t.lecturer_id) as total_lecturers,
    COUNT(DISTINCT t.day) as working_days_count
FROM sessions s
LEFT JOIN timetable t ON s.type = t.session_type
GROUP BY s.id;

-- View 3: Cross-session conflicts detection
CREATE VIEW `cross_session_conflicts` AS
SELECT 
    t1.session_type as session1,
    t2.session_type as session2,
    t1.day,
    t1.semester,
    t1.academic_year,
    ts.start_time,
    ts.end_time,
    'class_conflict' as conflict_type,
    c1.name as class_name,
    c1.level as class_level
FROM timetable t1
JOIN timetable t2 ON t1.class_id = t2.class_id 
    AND t1.day = t2.day 
    AND t1.time_slot_id = t2.time_slot_id
    AND t1.session_type != t2.session_type
    AND t1.semester = t2.semester
    AND t1.academic_year = t2.academic_year
JOIN classes c1 ON t1.class_id = c1.id
JOIN time_slots ts ON t1.time_slot_id = ts.id
WHERE t1.id < t2.id

UNION ALL

SELECT 
    t1.session_type as session1,
    t2.session_type as session2,
    t1.day,
    t1.semester,
    t1.academic_year,
    ts.start_time,
    ts.end_time,
    'room_conflict' as conflict_type,
    r.name as room_name,
    r.room_type
FROM timetable t1
JOIN timetable t2 ON t1.room_id = t2.room_id 
    AND t1.day = t2.day 
    AND t1.time_slot_id = t2.time_slot_id
    AND t1.session_type != t2.session_type
    AND t1.semester = t2.semester
    AND t1.academic_year = t2.academic_year
JOIN rooms r ON t1.room_id = r.id
JOIN time_slots ts ON t1.time_slot_id = ts.id
WHERE t1.id < t2.id

UNION ALL

SELECT 
    t1.session_type as session1,
    t2.session_type as session2,
    t1.day,
    t1.semester,
    t1.academic_year,
    ts.start_time,
    ts.end_time,
    'lecturer_conflict' as conflict_type,
    l.name as lecturer_name,
    d.name as department_name
FROM timetable t1
JOIN timetable t2 ON t1.lecturer_id = t2.lecturer_id 
    AND t1.day = t2.day 
    AND t1.time_slot_id = t2.time_slot_id
    AND t1.session_type != t2.session_type
    AND t1.semester = t2.semester
    AND t1.academic_year = t2.academic_year
JOIN lecturers l ON t1.lecturer_id = l.id
JOIN departments d ON l.department_id = d.id
JOIN time_slots ts ON t1.time_slot_id = ts.id
WHERE t1.id < t2.id;

-- View 4: Daily schedule for easy viewing
CREATE VIEW `daily_schedule` AS
SELECT 
    t.day,
    t.semester,
    t.academic_year,
    t.session_type,
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
    t.is_confirmed
FROM timetable t
JOIN classes c ON t.class_id = c.id
JOIN courses co ON t.course_id = co.id
JOIN lecturers l ON t.lecturer_id = l.id
JOIN rooms r ON t.room_id = r.id
JOIN time_slots ts ON t.time_slot_id = ts.id
JOIN departments d ON c.department_id = d.id
ORDER BY t.day, ts.start_time;

-- View 5: Room utilization
CREATE VIEW `room_utilization` AS
SELECT 
    r.id as room_id,
    r.name as room_name,
    r.building,
    r.room_type,
    r.capacity,
    t.session_type,
    t.semester,
    t.academic_year,
    COUNT(t.id) as total_bookings,
    COUNT(DISTINCT t.day) as days_used,
    COUNT(DISTINCT t.class_id) as classes_served
FROM rooms r
LEFT JOIN timetable t ON r.id = t.room_id
GROUP BY r.id, t.session_type, t.semester, t.academic_year
ORDER BY r.building, r.name;