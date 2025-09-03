-- SIMPLIFIED LECTURERS TABLE (without rank field)
-- If you prefer to remove the rank field for simplicity

DROP TABLE IF EXISTS `lecturers`;
CREATE TABLE `lecturers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `staff_id` varchar(20) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `title` varchar(20) DEFAULT NULL,  -- Keep title (Dr., Prof., Mr., Ms.)
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `department_id` int NOT NULL,
  -- REMOVED: rank field
  `specialization` text DEFAULT NULL,
  `qualifications` json DEFAULT NULL,
  `max_hours_per_week` int DEFAULT '20',
  `max_classes_per_day` int DEFAULT '4',
  `preferred_time_slots` json DEFAULT NULL,
  `unavailable_days` json DEFAULT NULL,
  `office_location` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lecturer_staff_id` (`staff_id`),
  UNIQUE KEY `uq_lecturer_email` (`email`),
  KEY `department_id` (`department_id`),
  KEY `idx_lecturer_active` (`is_active`),
  CONSTRAINT `lecturers_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_lecturers_max_hours` CHECK ((`max_hours_per_week` > 0)),
  CONSTRAINT `chk_lecturers_max_classes` CHECK ((`max_classes_per_day` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;