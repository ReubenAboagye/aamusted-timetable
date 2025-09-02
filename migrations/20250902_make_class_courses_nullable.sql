-- Migration: allow class_courses.lecturer_id to be NULL and give sensible defaults
-- Run this on the database (backup first):

ALTER TABLE class_courses
  MODIFY COLUMN lecturer_id INT DEFAULT NULL,
  MODIFY COLUMN semester ENUM('first','second','summer') NOT NULL DEFAULT 'first',
  MODIFY COLUMN academic_year VARCHAR(9) DEFAULT NULL;

-- Note: This makes it possible to create class_course mappings without assigning a lecturer
-- and ensures semester defaults to 'first' when not provided. academic_year will be NULL
-- unless supplied by the application.


