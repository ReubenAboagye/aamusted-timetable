-- Migration script to update streams table for new stream management features
-- Run this script to add the required columns for period times, break times, and active days

USE aamusted_timetable;

-- Add new columns to streams table
ALTER TABLE streams 
ADD COLUMN period_start TIME NULL AFTER description,
ADD COLUMN period_end TIME NULL AFTER period_start,
ADD COLUMN break_start TIME NULL AFTER period_end,
ADD COLUMN break_end TIME NULL AFTER break_start,
ADD COLUMN active_days TEXT NULL AFTER break_end;

-- Update existing streams to have default values
UPDATE streams SET 
    period_start = '08:00:00',
    period_end = '17:00:00',
    active_days = 'Monday,Tuesday,Wednesday,Thursday,Friday'
WHERE active_days IS NULL;

-- Insert sample stream data with all fields populated
INSERT INTO streams (name, code, description, period_start, period_end, break_start, break_end, active_days, is_active) VALUES
('Regular Day Stream', 'REG', 'Standard weekday stream for regular students', '08:00:00', '17:00:00', '12:00:00', '13:00:00', 'Monday,Tuesday,Wednesday,Thursday,Friday', 1),
('Weekend Stream', 'WKD', 'Weekend classes for working professionals', '09:00:00', '18:00:00', '13:00:00', '14:00:00', 'Saturday,Sunday', 1),
('Evening Stream', 'EVE', 'Evening classes for part-time students', '18:00:00', '22:00:00', '20:00:00', '20:30:00', 'Monday,Tuesday,Wednesday,Thursday,Friday', 1),
('Holiday Intensive', 'HOL', 'Intensive holiday program', '09:00:00', '16:00:00', '12:30:00', '13:30:00', 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday', 1),
('Summer Session', 'SUM', 'Summer semester classes', '07:00:00', '15:00:00', '11:00:00', '12:00:00', 'Monday,Tuesday,Wednesday,Thursday,Friday', 1);

-- Show the updated table structure
DESCRIBE streams;

-- Show the sample data
SELECT * FROM streams;
