-- Migration: Normalize buildings data and remove redundancy
-- Date: 2025-01-30  
-- Description: Populate buildings table from rooms.building data and remove redundant column
-- Based on: Dump20250831.sql structure

-- Step 1: Check current state
SELECT 'Current buildings:' as status;
SELECT id, name, code FROM buildings ORDER BY name;

SELECT 'Current rooms with building info:' as status;
SELECT id, name, building, building_id FROM rooms ORDER BY building, name LIMIT 10;

-- Step 2: Insert unique building names into buildings table (if they don't exist)
INSERT INTO buildings (name, code, description, is_active) 
SELECT DISTINCT 
    building as name,
    CASE 
        -- Generate codes from building names based on current data
        WHEN building = 'ROB Building' THEN 'ROB'
        WHEN building = 'CBT Building' THEN 'CBT'
        WHEN building = 'New Library' THEN 'NLIB'
        WHEN building = 'Teaching Lab' THEN 'TL'
        WHEN building = 'General Lecture Room' THEN 'GLR'
        WHEN building = 'New Auditorium' THEN 'NAUD'
        WHEN building = 'New Building' THEN 'NB'
        WHEN building = 'UBS Building' THEN 'UBS'
        WHEN building = 'NFB Building' THEN 'NFB'
        WHEN building = 'Autonomy Hall' THEN 'AH'
        WHEN building = 'New Lecture Block' THEN 'NLB'
        WHEN building = 'Construction Building' THEN 'CONST'
        ELSE UPPER(SUBSTRING_INDEX(building, ' ', 1))
    END as code,
    CONCAT('Building: ', building) as description,
    1 as is_active
FROM rooms 
WHERE building IS NOT NULL 
  AND building != ''
  AND building NOT IN (SELECT name FROM buildings WHERE name IS NOT NULL)
ORDER BY building;

-- Step 2: Update rooms.building_id to reference correct building IDs
UPDATE rooms r
INNER JOIN buildings b ON r.building = b.name
SET r.building_id = b.id;

-- Step 3: Verify all rooms have proper building_id references
SELECT 
    r.id,
    r.name as room_name,
    r.building as old_building_name,
    r.building_id,
    b.name as new_building_name,
    b.code as building_code
FROM rooms r
LEFT JOIN buildings b ON r.building_id = b.id
ORDER BY r.building, r.name;

-- Step 4: Remove the redundant building column (uncomment when ready)
-- WARNING: This will permanently remove the building text column
-- ALTER TABLE rooms DROP COLUMN building;

-- Step 5: Update the unique constraint to use building_id instead of building
-- WARNING: This changes the constraint structure
-- ALTER TABLE rooms DROP INDEX uq_room_name_building;
-- ALTER TABLE rooms ADD UNIQUE KEY uq_room_name_building_id (name, building_id);

-- Verification query - check all rooms have valid building references
SELECT 
    COUNT(*) as total_rooms,
    COUNT(CASE WHEN building_id IS NULL THEN 1 END) as rooms_without_building,
    COUNT(DISTINCT building_id) as unique_buildings_referenced
FROM rooms;

SELECT 
    b.name as building_name,
    b.code as building_code,
    COUNT(r.id) as room_count
FROM buildings b
LEFT JOIN rooms r ON b.id = r.building_id
GROUP BY b.id, b.name, b.code
ORDER BY b.name;
