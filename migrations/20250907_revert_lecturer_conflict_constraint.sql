-- Revert lecturer conflict constraint migration
-- This migration removes the unique constraint that was causing duplicate entry errors

-- Remove the unique constraint for lecturer conflicts
ALTER TABLE timetable 
DROP CONSTRAINT `uq_timetable_lecturer_time`;

-- Remove the index for lecturer conflict checks
ALTER TABLE timetable 
DROP INDEX `idx_timetable_lecturer_conflict_check`;

-- Verify the constraint has been removed
SHOW CREATE TABLE timetable;



