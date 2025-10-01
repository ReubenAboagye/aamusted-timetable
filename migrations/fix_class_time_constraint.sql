-- Fix the uq_timetable_class_time constraint to include version
-- This constraint is preventing versioning because it doesn't include version field

-- Drop the problematic constraint
ALTER TABLE timetable DROP INDEX `uq_timetable_class_time`;

-- Add the corrected constraint that includes version
ALTER TABLE timetable 
ADD CONSTRAINT `uq_timetable_class_time` 
UNIQUE KEY (`class_course_id`, `day_id`, `time_slot_id`, `semester`, `academic_year`, `division_label`, `version`);
