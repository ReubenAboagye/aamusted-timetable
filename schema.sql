-- =========================================
-- UNIVERSITY TIMETABLE SYSTEM SCHEMA
-- Consolidated Sessions Table Structure
-- =========================================

-- Safe migration: disable FK checks, drop timetable-related tables, then create schema
SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS timetable_lecturers;
DROP TABLE IF EXISTS timetable;
DROP TABLE IF EXISTS course_session_availability;
DROP TABLE IF EXISTS lecturer_session_availability;
DROP TABLE IF EXISTS lecturer_courses;
DROP TABLE IF EXISTS class_courses;

DROP TABLE IF EXISTS rooms;
DROP TABLE IF EXISTS buildings;
DROP TABLE IF EXISTS days;
DROP TABLE IF EXISTS session_types;
DROP TABLE IF EXISTS sessions;
DROP TABLE IF EXISTS classes;
DROP TABLE IF EXISTS courses;
DROP TABLE IF EXISTS lecturers;
DROP TABLE IF EXISTS semesters;
DROP TABLE IF EXISTS levels;
DROP TABLE IF EXISTS programs;
DROP TABLE IF EXISTS departments;
DROP TABLE IF EXISTS streams;
SET FOREIGN_KEY_CHECKS=0;

-- =========================================
-- 1) DEPARTMENTS
--    Each academic department in the institution
-- =========================================
CREATE TABLE departments (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  code VARCHAR(20) NOT NULL UNIQUE,
  short_name VARCHAR(10),
  head_of_department VARCHAR(100),
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =========================================
-- 2) PROGRAMS
--    Programs belong to departments (e.g. BSc Computer Science)
-- =========================================
CREATE TABLE programs (
  id INT PRIMARY KEY AUTO_INCREMENT,
  department_id INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  code VARCHAR(20) NOT NULL UNIQUE,
  duration_years INT NOT NULL,
  is_active BOOLEAN DEFAULT TRUE,
  FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
);

-- =========================================
-- 3) LEVELS
--    Standard academic levels (e.g. 100, 200, etc.)
-- =========================================
CREATE TABLE levels (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(50) NOT NULL, -- e.g. "Level 100"
  year_number INT NOT NULL   -- numeric representation, e.g. 1, 2, 3, 4
);

-- =========================================
-- 4) LECTURERS
-- =========================================
CREATE TABLE lecturers (
  id INT PRIMARY KEY AUTO_INCREMENT,
  department_id INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(100) UNIQUE,
  phone VARCHAR(20),
  `rank` VARCHAR(50), -- e.g. Lecturer, Senior Lecturer
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
);

-- =========================================
-- 5) STREAMS
--    Time-based streams for classes (regular, weekend, evening, etc.)
-- =========================================
CREATE TABLE streams (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(50) NOT NULL UNIQUE, -- e.g., "Regular", "Weekend", "Evening"
  code VARCHAR(20) NOT NULL UNIQUE, -- e.g., "REG", "WKD", "EVE"
  description TEXT,
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =========================================
-- 6) SESSIONS (Academic periods)
--    Each academic session with semester information and dates
-- =========================================
CREATE TABLE sessions (
  id INT PRIMARY KEY AUTO_INCREMENT,
  academic_year VARCHAR(20) NOT NULL,        -- e.g., "2024/2025"
  semester_number ENUM('1','2','3') NOT NULL, -- 1, 2, or 3
  semester_name VARCHAR(100) NOT NULL,        -- e.g., "First Semester 2024/2025"
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  -- Ensure unique academic year + semester combinations
  UNIQUE KEY uq_academic_semester (academic_year, semester_number)
);

-- =========================================
-- 7) CLASSES (student groups) – each class belongs to a stream
-- =========================================
CREATE TABLE classes (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,         -- e.g. "ITE 100A"
  department_id INT NOT NULL,
  level VARCHAR(50) NOT NULL,         -- e.g. "Level 100"
  stream_id INT NOT NULL,             -- Reference to streams table
  capacity INT NOT NULL DEFAULT 30,
  current_enrollment INT DEFAULT 0,
  max_daily_courses INT NOT NULL DEFAULT 3,
  max_weekly_hours INT NOT NULL DEFAULT 25,
  preferred_start_time TIME DEFAULT '08:00:00',
  preferred_end_time TIME DEFAULT '17:00:00',
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
  FOREIGN KEY (stream_id) REFERENCES streams(id) ON DELETE CASCADE,
  UNIQUE KEY uq_class_stream (name, stream_id)
);

-- =========================================
-- 8) COURSES
--   (No fixed "semester" column; use mappings below per semester/session)
-- =========================================
CREATE TABLE courses (
  id INT PRIMARY KEY AUTO_INCREMENT,
  code VARCHAR(20) NOT NULL UNIQUE,
  name VARCHAR(200) NOT NULL,
  department_id INT NOT NULL,
  credits INT NOT NULL DEFAULT 3,
  hours_per_week INT NOT NULL DEFAULT 3,
  level INT NOT NULL,                                 -- 1..4
  preferred_room_type ENUM('classroom','lecture_hall','laboratory','computer_lab','seminar_room','auditorium') DEFAULT 'classroom',
  is_active BOOLEAN DEFAULT TRUE,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
);

-- =========================================
-- 9) LECTURER_COURSES (who is allowed to teach what)
-- =========================================
CREATE TABLE lecturer_courses (
  id INT PRIMARY KEY AUTO_INCREMENT,
  lecturer_id INT NOT NULL,
  course_id INT NOT NULL,
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (lecturer_id) REFERENCES lecturers(id) ON DELETE CASCADE,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  UNIQUE KEY uq_lect_course (lecturer_id, course_id)
);

-- =========================================
-- 10) ROOMS
-- =========================================
CREATE TABLE rooms (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(50) NOT NULL,
  building VARCHAR(100) NOT NULL,
  room_type VARCHAR(50) NOT NULL COMMENT 'Expected values: classroom, lecture_hall, laboratory, computer_lab, seminar_room, auditorium',
  capacity INT NOT NULL,
  stream_availability JSON NOT NULL, -- e.g. ["regular","evening","weekend"]
  facilities JSON,                    -- e.g. ["projector","whiteboard"]
  accessibility_features JSON,        -- e.g. ["wheelchair_access"]
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_room_name_building (name, building)
);



-- =========================================
-- 12) CLASS_COURSES (which courses a class takes per semester)
--    Updated to reference sessions table instead of semesters
-- =========================================
CREATE TABLE class_courses (
  id INT PRIMARY KEY AUTO_INCREMENT,
  class_id INT NOT NULL,
  course_id INT NOT NULL,
  session_id INT NOT NULL,
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
  UNIQUE KEY uq_class_course_sem (class_id, course_id, session_id)
);

-- =========================================
-- 13) AVAILABILITY GUARDS (hard validation for sessions)
--     a) Lecturer available in session?
--     b) Course offered in session?
-- =========================================
CREATE TABLE lecturer_session_availability (
  lecturer_id INT NOT NULL,
  session_id INT NOT NULL,
  PRIMARY KEY (lecturer_id, session_id),
  FOREIGN KEY (lecturer_id) REFERENCES lecturers(id) ON DELETE CASCADE,
  FOREIGN KEY (session_id)  REFERENCES sessions(id)  ON DELETE CASCADE
);

CREATE TABLE course_session_availability (
  course_id INT NOT NULL,
  session_id INT NOT NULL,
  PRIMARY KEY (course_id, session_id),
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE
);

-- =========================================
-- 14) SESSION TYPES and DAYS
-- =========================================
CREATE TABLE session_types (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE days (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(20) NOT NULL UNIQUE
);

-- =========================================
-- 15) TIMETABLE (core schedule)
-- =========================================
CREATE TABLE timetable (
  id INT PRIMARY KEY AUTO_INCREMENT,
  session_id INT NOT NULL,
  class_course_id INT NOT NULL,
  lecturer_course_id INT NOT NULL,
  day_id INT NOT NULL,
  room_id INT NOT NULL,
  session_type_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
  FOREIGN KEY (class_course_id) REFERENCES class_courses(id) ON DELETE CASCADE,
  FOREIGN KEY (lecturer_course_id) REFERENCES lecturer_courses(id) ON DELETE CASCADE,
  FOREIGN KEY (day_id) REFERENCES days(id),
  FOREIGN KEY (room_id) REFERENCES rooms(id),
  FOREIGN KEY (session_type_id) REFERENCES session_types(id),
  UNIQUE KEY uq_tt_slot (session_id, day_id, room_id)
);

CREATE TABLE timetable_lecturers (
  id INT PRIMARY KEY AUTO_INCREMENT,
  timetable_id INT NOT NULL,
  lecturer_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (timetable_id) REFERENCES timetable(id) ON DELETE CASCADE,
  FOREIGN KEY (lecturer_id)  REFERENCES lecturers(id) ON DELETE CASCADE,
  UNIQUE KEY uq_tt_lect (timetable_id, lecturer_id)
);

-- =========================================
-- 16) SAMPLE DATA FOR STREAMS
-- =========================================
INSERT INTO streams (name, code, description, is_active) VALUES
('Regular', 'REG', 'Standard weekday classes (Monday to Friday)', 1),
('Weekend', 'WKD', 'Saturday and Sunday classes', 1),
('Evening', 'EVE', 'After-hours classes (6 PM onwards)', 1),
('Online', 'ONL', 'Virtual/remote classes', 1),
('Hybrid', 'HYB', 'Combination of in-person and online classes', 1);

-- =========================================
-- 17) SAMPLE DATA FOR SESSIONS
-- =========================================
INSERT INTO sessions (academic_year, semester_number, semester_name, start_date, end_date, is_active) VALUES
('2024/2025', '1', 'First Semester 2024/2025', '2024-09-01', '2024-12-20', 1),
('2024/2025', '2', 'Second Semester 2024/2025', '2025-01-15', '2025-05-15', 1),
('2024/2025', '3', 'Third Semester 2024/2025', '2025-06-01', '2025-08-31', 1),
('2025/2026', '1', 'First Semester 2025/2026', '2025-09-01', '2025-12-20', 1),
('2025/2026', '2', 'Second Semester 2025/2026', '2026-01-15', '2026-05-15', 1),
('2025/2026', '3', 'Third Semester 2025/2026', '2026-06-01', '2026-08-31', 1);

-- =========================================
-- 18) SAMPLE DATA FOR DAYS
-- =========================================
INSERT INTO days (name) VALUES 
('Monday'), ('Tuesday'), ('Wednesday'), ('Thursday'), ('Friday'), ('Saturday'), ('Sunday');

-- =========================================
-- 19) SAMPLE DATA FOR SESSION TYPES
-- =========================================
INSERT INTO session_types (name) VALUES 
('Lecture'), ('Tutorial'), ('Laboratory'), ('Seminar'), ('Exam'), ('Break');



SET FOREIGN_KEY_CHECKS=1;
