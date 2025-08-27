-- =========================================
-- MIGRATION SCRIPT: Consolidate sessions + semesters into academic_sessions
-- =========================================

-- Disable foreign key checks temporarily
SET FOREIGN_KEY_CHECKS=0;

-- Step 1: Create the new consolidated table
CREATE TABLE academic_sessions (
  id INT PRIMARY KEY AUTO_INCREMENT,
  academic_year VARCHAR(20) NOT NULL,        -- e.g., "2024/2025"
  semester_number ENUM('1','2','3') NOT NULL, -- 1, 2, or 3
  semester_name VARCHAR(100) NOT NULL,        -- e.g., "First Semester 2024/2025"
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  -- Ensure unique academic year + semester combinations
  UNIQUE KEY uq_academic_semester (academic_year, semester_number)
);

-- Step 2: Migrate existing sessions data
INSERT INTO academic_sessions (academic_year, semester_number, semester_name, start_date, end_date, is_active)
SELECT 
  s.academic_year,
  s.semester,
  CONCAT(
    CASE s.semester
      WHEN '1' THEN 'First Semester'
      WHEN '2' THEN 'Second Semester'
    END,
    ' ',
    s.academic_year
  ) as semester_name,
  COALESCE(s.start_date, '1900-01-01') as start_date,
  COALESCE(s.end_date, '1900-01-01') as end_date,
  s.is_active
FROM sessions s;

-- Step 3: Migrate existing semesters data (if any conflicts, skip)
INSERT IGNORE INTO academic_sessions (academic_year, semester_number, semester_name, start_date, end_date, is_active)
SELECT 
  'Unknown' as academic_year,
  CASE 
    WHEN s.name LIKE '%First%' THEN '1'
    WHEN s.name LIKE '%Second%' THEN '2'
    WHEN s.name LIKE '%Third%' THEN '3'
    ELSE '1'
  END as semester_number,
  s.name as semester_name,
  s.start_date,
  s.end_date,
  s.is_active
FROM semesters s
WHERE s.id NOT IN (
  SELECT id FROM academic_sessions
);

-- Step 4: Update foreign key references in other tables
-- Update class_courses table
ALTER TABLE class_courses 
CHANGE COLUMN semester_id session_id INT NOT NULL,
ADD CONSTRAINT fk_class_courses_session 
FOREIGN KEY (session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE;

-- Step 5: Drop old tables
DROP TABLE IF EXISTS sessions;
DROP TABLE IF EXISTS semesters;

-- Step 6: Rename the new table to 'sessions' for backward compatibility
RENAME TABLE academic_sessions TO sessions;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS=1;

-- Step 7: Verify the migration
SELECT 
  'Migration completed successfully!' as status,
  COUNT(*) as total_sessions
FROM sessions;
