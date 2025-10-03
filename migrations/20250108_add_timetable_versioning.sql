-- Migration to add versioning support to timetable
-- This allows multiple timetable versions for the same stream/semester

-- Add version field to timetable table
ALTER TABLE timetable 
ADD COLUMN version VARCHAR(50) DEFAULT 'regular' AFTER semester;

-- Add index for better performance on version queries
ALTER TABLE timetable 
ADD INDEX idx_version (version);

-- Add composite index for stream/semester/version queries
ALTER TABLE timetable 
ADD INDEX idx_stream_semester_version (semester, version);

-- Update existing records to have 'regular' version
UPDATE timetable SET version = 'regular' WHERE version IS NULL;

-- Make version field NOT NULL after setting default values
ALTER TABLE timetable 
MODIFY COLUMN version VARCHAR(50) NOT NULL DEFAULT 'regular';
