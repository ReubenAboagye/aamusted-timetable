-- Migration: Make Courses and Programs Stream-Specific
-- This migration removes the global unique constraint on course codes and programs
-- and replaces it with stream-specific unique constraints

-- =====================================================
-- PART 1: COURSES TABLE CHANGES
-- =====================================================

-- Step 1: Add stream_id column to courses table
ALTER TABLE `courses` 
ADD COLUMN `stream_id` INT NOT NULL DEFAULT 1 AFTER `department_id`;

-- Step 2: Remove the global unique constraint on code
ALTER TABLE `courses` DROP INDEX `uq_course_code`;

-- Step 3: Add a composite unique constraint for code + stream_id
-- This allows the same course code to exist in different streams
ALTER TABLE `courses` 
ADD CONSTRAINT `uq_course_code_stream` 
UNIQUE KEY (`code`, `stream_id`);

-- Step 4: Add index for better performance on stream-specific queries
ALTER TABLE `courses` 
ADD INDEX `idx_courses_stream_code` (`stream_id`, `code`);

-- =====================================================
-- PART 2: PROGRAMS TABLE CHANGES
-- =====================================================

-- Step 1: Add stream_id column to programs table
ALTER TABLE `programs` 
ADD COLUMN `stream_id` INT NOT NULL DEFAULT 1 AFTER `department_id`;

-- Step 2: Remove the global unique constraint on code
ALTER TABLE `programs` DROP INDEX `uq_program_code`;

-- Step 3: Add a composite unique constraint for code + stream_id
-- This allows the same program code to exist in different streams
ALTER TABLE `programs` 
ADD CONSTRAINT `uq_program_code_stream` 
UNIQUE KEY (`code`, `stream_id`);

-- Step 4: Add index for better performance on stream-specific queries
ALTER TABLE `programs` 
ADD INDEX `idx_programs_stream_code` (`stream_id`, `code`);

-- =====================================================
-- PART 3: UPDATE EXISTING DATA
-- =====================================================

-- Update existing courses to use stream_id = 1 (default stream)
UPDATE `courses` SET `stream_id` = 1 WHERE `stream_id` IS NULL;

-- Update existing programs to use stream_id = 1 (default stream)
UPDATE `programs` SET `stream_id` = 1 WHERE `stream_id` IS NULL;

-- =====================================================
-- PART 4: ADD FOREIGN KEY CONSTRAINTS
-- =====================================================

-- Add foreign key constraint for courses.stream_id
ALTER TABLE `courses` 
ADD CONSTRAINT `fk_courses_stream` 
FOREIGN KEY (`stream_id`) REFERENCES `streams`(`id`) 
ON DELETE RESTRICT ON UPDATE CASCADE;

-- Add foreign key constraint for programs.stream_id
ALTER TABLE `programs` 
ADD CONSTRAINT `fk_programs_stream` 
FOREIGN KEY (`stream_id`) REFERENCES `streams`(`id`) 
ON DELETE RESTRICT ON UPDATE CASCADE;