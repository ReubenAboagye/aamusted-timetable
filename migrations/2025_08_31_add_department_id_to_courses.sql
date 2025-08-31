-- Migration: add department_id to courses
-- Adds a nullable `department_id` column to `courses` and an index.
-- This migration is non-destructive and does not attempt to guess/assign departments.
-- Run on a backup first.

START TRANSACTION;

-- 1) Add column if it doesn't already exist
ALTER TABLE `courses`
    ADD COLUMN IF NOT EXISTS `department_id` INT DEFAULT NULL AFTER `name`;

-- 2) Add an index to speed up lookups (safe even if column is NULL)
ALTER TABLE `courses`
    ADD INDEX IF NOT EXISTS `idx_courses_department_id` (`department_id`);

-- Note: We intentionally do NOT add a FOREIGN KEY here to avoid migration failures
-- on databases where departments may not yet exist or where values are NULL.
-- If you want a foreign key, run the following after you've populated department_id:
-- ALTER TABLE `courses` ADD CONSTRAINT `fk_courses_department` FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL;

COMMIT;

-- After running this migration you can populate `department_id` using a mapping strategy
-- appropriate for your dataset (manual mapping, code-based heuristics, or by importing
-- a CSV that links course id -> department id). If you want, I can prepare a helper
-- script to assist with backfilling using heuristics (e.g. match by course code prefixes).


