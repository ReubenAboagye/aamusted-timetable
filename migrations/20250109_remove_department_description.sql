-- Migration: Remove description column from departments table
-- Date: 2025-01-09
-- Description: Remove the description field from departments table as it's not functionally necessary

USE `timetable_system`;

-- Remove the description column from departments table
ALTER TABLE `departments` DROP COLUMN `description`;

-- Verify the change
DESCRIBE `departments`;
