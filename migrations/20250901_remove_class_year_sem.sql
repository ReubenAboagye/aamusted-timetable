-- Migration: remove academic_year and semester from classes
-- Run this on the database after ensuring timetable has academic_year and semester columns

-- Safety: preview current schema
SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'classes' AND COLUMN_NAME IN ('academic_year','semester');

-- 1) If you need to preserve existing data, copy it to timetable or a backup table first.
-- Example: copy to timetable (if timetable has the columns):
-- INSERT INTO timetable (class_course_id, academic_year, semester)
-- SELECT NULL, academic_year, semester FROM classes WHERE academic_year IS NOT NULL OR semester IS NOT NULL;

-- 2) Drop the columns from classes
ALTER TABLE classes
  DROP COLUMN academic_year,
  DROP COLUMN semester;

-- 3) Verify
SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'classes';


