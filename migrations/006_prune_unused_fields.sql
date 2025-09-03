-- Migration: Remove unused schema columns introduced in restructured schema
-- Safe: Drops only columns that are not referenced by PHP (scanned)
-- Backup recommended before running on production

START TRANSACTION;

-- DROP unused columns from `buildings`
ALTER TABLE `buildings` 
  DROP COLUMN IF EXISTS `accessibility_features`;

-- DROP unused columns from `room_types`
ALTER TABLE `room_types`
  DROP COLUMN IF EXISTS `equipment_required`;

-- DROP unused columns from `courses`
ALTER TABLE `courses`
  DROP COLUMN IF EXISTS `learning_outcomes`,
  DROP COLUMN IF EXISTS `assessment_methods`,
  DROP COLUMN IF EXISTS `max_class_size`;

-- DROP unused columns from `lecturers` (keep preferred_time_slots)
ALTER TABLE `lecturers`
  DROP COLUMN IF EXISTS `qualifications`;

-- DROP unused columns from `rooms`
ALTER TABLE `rooms`
  DROP COLUMN IF EXISTS `accessibility_features`;

-- DROP unused columns from `classes` (division_capacity kept only for triggers if needed)
-- We'll not drop division_capacity because triggers/migrations reference it; keep as-is

COMMIT;

-- End migration


