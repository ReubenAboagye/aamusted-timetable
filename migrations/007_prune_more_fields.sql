START TRANSACTION;

-- Remove optional/unused columns identified by code scan
ALTER TABLE `buildings`
  DROP COLUMN IF EXISTS `floors_count`;

ALTER TABLE `departments`
  DROP COLUMN IF EXISTS `short_name`;

ALTER TABLE `levels`
  DROP COLUMN IF EXISTS `min_credits`,
  DROP COLUMN IF EXISTS `max_credits`;

ALTER TABLE `programs`
  DROP COLUMN IF EXISTS `degree_type`,
  DROP COLUMN IF EXISTS `entry_requirements`;

ALTER TABLE `courses`
  DROP COLUMN IF EXISTS `prerequisites`,
  DROP COLUMN IF EXISTS `corequisites`,
  DROP COLUMN IF EXISTS `preferred_room_type`;

ALTER TABLE `room_types`
  DROP COLUMN IF EXISTS `setup_time_minutes`,
  DROP COLUMN IF EXISTS `cleanup_time_minutes`;

ALTER TABLE `rooms`
  DROP COLUMN IF EXISTS `floor_number`,
  DROP COLUMN IF EXISTS `room_number`,
  DROP COLUMN IF EXISTS `equipment`,
  DROP COLUMN IF EXISTS `notes`;

ALTER TABLE `classes`
  DROP COLUMN IF EXISTS `class_coordinator`,
  DROP COLUMN IF EXISTS `preferred_start_time`,
  DROP COLUMN IF EXISTS `preferred_end_time`,
  DROP COLUMN IF EXISTS `special_requirements`;

ALTER TABLE `lecturer_courses`
  DROP COLUMN IF EXISTS `is_primary`,
  DROP COLUMN IF EXISTS `competency_level`;

ALTER TABLE `course_room_types`
  DROP COLUMN IF EXISTS `setup_requirements`;

COMMIT;

-- NOTE: Run this migration on a test DB first. This script uses DROP COLUMN IF EXISTS for safety.


