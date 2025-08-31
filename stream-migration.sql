-- class_courses
ALTER TABLE `class_courses`
  ADD COLUMN `stream_id` INT NOT NULL DEFAULT 1,
  ADD KEY `idx_class_courses_stream_id` (`stream_id`),
  ADD CONSTRAINT `fk_class_courses_stream` FOREIGN KEY (`stream_id`) REFERENCES `streams` (`id`) ON DELETE CASCADE;

-- course_room_types
ALTER TABLE `course_room_types`
  ADD COLUMN `stream_id` INT NOT NULL DEFAULT 1,
  ADD KEY `idx_course_room_types_stream_id` (`stream_id`),
  ADD CONSTRAINT `fk_course_room_types_stream` FOREIGN KEY (`stream_id`) REFERENCES `streams` (`id`) ON DELETE CASCADE;

-- courses
ALTER TABLE `courses`
  ADD COLUMN `stream_id` INT NOT NULL DEFAULT 1,
  ADD KEY `idx_courses_stream_id` (`stream_id`),
  ADD CONSTRAINT `fk_courses_stream` FOREIGN KEY (`stream_id`) REFERENCES `streams` (`id`) ON DELETE CASCADE;

-- lecturer_courses
ALTER TABLE `lecturer_courses`
  ADD COLUMN `stream_id` INT NOT NULL DEFAULT 1,
  ADD KEY `idx_lecturer_courses_stream_id` (`stream_id`),
  ADD CONSTRAINT `fk_lecturer_courses_stream` FOREIGN KEY (`stream_id`) REFERENCES `streams` (`id`) ON DELETE CASCADE;

-- lecturers (nullable)
ALTER TABLE `lecturers`
  ADD COLUMN `stream_id` INT DEFAULT NULL,
  ADD KEY `idx_lecturers_stream_id` (`stream_id`),
  ADD CONSTRAINT `fk_lecturers_stream` FOREIGN KEY (`stream_id`) REFERENCES `streams` (`id`) ON DELETE SET NULL;

-- programs (nullable)
ALTER TABLE `programs`
  ADD COLUMN `stream_id` INT DEFAULT NULL,
  ADD KEY `idx_programs_stream_id` (`stream_id`),
  ADD CONSTRAINT `fk_programs_stream` FOREIGN KEY (`stream_id`) REFERENCES `streams` (`id`) ON DELETE SET NULL;

-- timetable
ALTER TABLE `timetable`
  ADD COLUMN `stream_id` INT NOT NULL DEFAULT 1,
  ADD KEY `idx_timetable_stream_id` (`stream_id`),
  ADD CONSTRAINT `fk_timetable_stream` FOREIGN KEY (`stream_id`) REFERENCES `streams` (`id`) ON DELETE CASCADE;

-- timetable_lecturers
ALTER TABLE `timetable_lecturers`
  ADD COLUMN `stream_id` INT NOT NULL DEFAULT 1,
  ADD KEY `idx_timetable_lecturers_stream_id` (`stream_id`),
  ADD CONSTRAINT `fk_timetable_lecturers_stream` FOREIGN KEY (`stream_id`) REFERENCES `streams` (`id`) ON DELETE CASCADE;