-- Add lecturer conflict constraint to prevent same lecturer in same time slot
-- This migration adds a unique constraint to prevent lecturer conflicts

-- First, check if there are any existing lecturer conflicts
SELECT 
    lc1.lecturer_id,
    t1.day_id,
    t1.time_slot_id,
    COUNT(*) as conflict_count
FROM timetable t1
JOIN lecturer_courses lc1 ON t1.lecturer_course_id = lc1.id
JOIN lecturer_courses lc2 ON t1.lecturer_course_id = lc2.id
WHERE t1.semester = t1.semester 
  AND t1.academic_year = t1.academic_year
  AND t1.timetable_type = t1.timetable_type
GROUP BY lc1.lecturer_id, t1.day_id, t1.time_slot_id, t1.semester, t1.academic_year, t1.timetable_type
HAVING COUNT(*) > 1;

-- If conflicts exist, remove duplicates (keep the first one)
-- This is a one-time cleanup before adding the constraint
DELETE t1 FROM timetable t1
INNER JOIN timetable t2 
WHERE t1.id > t2.id
  AND t1.lecturer_course_id = t2.lecturer_course_id
  AND t1.day_id = t2.day_id
  AND t1.time_slot_id = t2.time_slot_id
  AND t1.semester = t2.semester
  AND t1.academic_year = t2.academic_year
  AND t1.timetable_type = t2.timetable_type;

-- Add unique constraint for lecturer conflicts
-- This will prevent the same lecturer from being assigned to multiple classes at the same time
ALTER TABLE timetable 
ADD CONSTRAINT `uq_timetable_lecturer_time` 
UNIQUE KEY (`lecturer_course_id`, `day_id`, `time_slot_id`, `semester`, `academic_year`, `timetable_type`);

-- Add index for better performance on lecturer conflict checks
ALTER TABLE timetable 
ADD INDEX `idx_timetable_lecturer_conflict_check` (`lecturer_course_id`, `day_id`, `time_slot_id`, `semester`, `academic_year`, `timetable_type`);




