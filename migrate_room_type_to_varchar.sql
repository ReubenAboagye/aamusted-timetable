-- Migration script to fix "Data truncated for column 'room_type'" error
-- This changes the room_type column from ENUM to VARCHAR to match the application logic

-- Step 1: Backup the current table structure (optional but recommended)
-- CREATE TABLE rooms_backup AS SELECT * FROM rooms;

-- Step 2: Modify the room_type column from ENUM to VARCHAR
ALTER TABLE rooms MODIFY COLUMN room_type VARCHAR(50) NOT NULL COMMENT 'Expected values: classroom, lecture_hall, laboratory, computer_lab, seminar_room, auditorium';

-- Step 3: Verify the change
-- SHOW COLUMNS FROM rooms LIKE 'room_type';

-- Step 4: Test with a sample insert to ensure it works
-- INSERT INTO rooms (name, building, room_type, capacity, stream_availability, facilities, accessibility_features, is_active) 
-- VALUES ('Test Room', 'Test Building', 'computer_lab', 30, '["regular"]', '[]', '[]', 1);

-- Step 5: Clean up test data (if you added it)
-- DELETE FROM rooms WHERE name = 'Test Room' AND building = 'Test Building';

-- Expected result:
-- The room_type column should now be VARCHAR(50) instead of ENUM
-- This will allow the application to insert values like 'computer_lab', 'lecture_hall', etc.
-- without getting "Data truncated" errors
