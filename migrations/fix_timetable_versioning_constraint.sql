-- Fix timetable versioning constraint to allow same class-course-time across different versions
-- This migration updates the unique constraint to include version and semester

-- First, check if there are any existing conflicts
SELECT 
    class_course_id,
    day_id,
    time_slot_id,
    division_label,
    COUNT(*) as conflict_count
FROM timetable 
GROUP BY class_course_id, day_id, time_slot_id, division_label
HAVING COUNT(*) > 1;

-- Drop the old constraint
ALTER TABLE timetable DROP INDEX `uq_timetable_class_course_time`;

-- Add the new constraint that includes version and semester
ALTER TABLE timetable 
ADD CONSTRAINT `uq_timetable_class_course_time` 
UNIQUE KEY (`class_course_id`, `day_id`, `time_slot_id`, `division_label`, `semester`, `version`);

-- Add index for better performance on version-specific queries
ALTER TABLE timetable 
ADD INDEX `idx_timetable_version_lookup` (`class_course_id`, `day_id`, `time_slot_id`, `semester`, `version`);

