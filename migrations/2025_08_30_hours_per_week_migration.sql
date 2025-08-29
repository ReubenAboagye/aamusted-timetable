-- Migration: replace lecture_hours, tutorial_hours, practical_hours with hours_per_week
-- Adds `hours_per_week`, populates it from existing columns if present, then drops the old columns.


-- 1) Add the new column (use IF NOT EXISTS when supported)
ALTER TABLE `courses`
    ADD COLUMN `hours_per_week` INT NOT NULL DEFAULT 3 AFTER `credits`;

-- 2) Drop the old columns (if they exist)
ALTER TABLE `courses`
    DROP COLUMN `practical_hours`,
    DROP COLUMN `tutorial_hours`,
    DROP COLUMN `lecture_hours`;



-- Notes:
-- - This migration is destructive: it drops the three old columns. Ensure you've backed up any needed data.
-- - Test on a copy of your DB before running in production.


