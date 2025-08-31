-- Safe migration: add department_id to courses
-- Compatible with older MySQL versions

-- Check if column exists and add if not
SET @column_exists = 0;
SELECT COUNT(*) INTO @column_exists 
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = 'timetable_system' 
  AND TABLE_NAME = 'courses' 
  AND COLUMN_NAME = 'department_id';

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE courses ADD COLUMN department_id INT DEFAULT NULL AFTER name', 
    'SELECT "Column department_id already exists" as message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index if it doesn't exist
SET @index_exists = 0;
SELECT COUNT(*) INTO @index_exists 
FROM information_schema.STATISTICS 
WHERE TABLE_SCHEMA = 'timetable_system' 
  AND TABLE_NAME = 'courses' 
  AND INDEX_NAME = 'idx_courses_department_id';

SET @sql = IF(@index_exists = 0, 
    'ALTER TABLE courses ADD INDEX idx_courses_department_id (department_id)', 
    'SELECT "Index idx_courses_department_id already exists" as message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Show current structure
DESCRIBE courses;
