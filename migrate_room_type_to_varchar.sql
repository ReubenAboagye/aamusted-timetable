-- =========================================
-- MIGRATION: Change room_type from ENUM to VARCHAR
-- This allows more flexibility in room type management
-- =========================================

-- Change room_type column from ENUM to VARCHAR(50)
ALTER TABLE rooms MODIFY COLUMN room_type VARCHAR(50) NOT NULL;

-- Add a comment to document the expected values
ALTER TABLE rooms MODIFY COLUMN room_type VARCHAR(50) NOT NULL COMMENT 'Expected values: classroom, lecture_hall, laboratory, computer_lab, seminar_room, auditorium';

-- Verify the change
DESCRIBE rooms;
