-- Safe migration: disable FK checks, drop timetable-related tables, then create schema
SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS timetable_lecturers;
DROP TABLE IF EXISTS timetable;
DROP TABLE IF EXISTS course_session_availability;
DROP TABLE IF EXISTS lecturer_session_availability;
DROP TABLE IF EXISTS lecturer_courses;
DROP TABLE IF EXISTS class_courses;
DROP TABLE IF EXISTS time_slots;
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
  is_active BOOLEAN DEFAULT TRUE
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
-- 3) SEMESTERS (minimal calendar)
-- =========================================
CREATE TABLE semesters (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(50) NOT NULL,          -- e.g. "First Semester 2025"
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  is_active BOOLEAN DEFAULT TRUE
);

-- =========================================
-- 4) CLASSES (student groups) – each class belongs to a session
-- =========================================
CREATE TABLE classes (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,         -- e.g. "L100 A"
  department_id INT NOT NULL,
  level VARCHAR(50) NOT NULL,         -- e.g. "Year 1"
  session_id INT NOT NULL,
  capacity INT NOT NULL DEFAULT 30,
  current_enrollment INT DEFAULT 0,
  max_daily_courses INT DEFAULT 3,
  max_weekly_hours INT DEFAULT 25,
  preferred_start_time TIME DEFAULT '08:00:00',
  preferred_end_time TIME DEFAULT '17:00:00',
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
  FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
  UNIQUE KEY uq_class_session (id, session_id)           -- enables composite FKs from timetable
);

-- =========================================
-- 5) COURSES
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
-- 6) LECTURER_COURSES (who is allowed to teach what)
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
-- 7) ROOMS
-- =========================================
CREATE TABLE rooms (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(50) NOT NULL,
  building VARCHAR(100) NOT NULL,
  room_type ENUM('classroom','lecture_hall','laboratory','computer_lab','seminar_room','auditorium') NOT NULL,
  capacity INT NOT NULL,
  session_availability JSON NOT NULL, -- e.g. ["regular","evening","weekend"]
  facilities JSON,                    -- e.g. ["projector","whiteboard"]
  accessibility_features JSON,        -- e.g. ["wheelchair_access"]
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_room_name_building (name, building)
);

-- =========================================
-- 8) TIME_SLOTS (reusable across all days)
--    No day-of-week here -> reuse the same slots Mon..Sun
-- =========================================
CREATE TABLE time_slots (
  id INT PRIMARY KEY AUTO_INCREMENT,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  duration INT NOT NULL,              -- minutes
  is_break BOOLEAN DEFAULT FALSE,
  is_mandatory BOOLEAN DEFAULT FALSE,
  UNIQUE KEY uq_times (start_time, end_time)
);

-- =========================================
-- 9) CLASS_COURSES (which courses a class takes per semester)
-- =========================================
CREATE TABLE class_courses (
  id INT PRIMARY KEY AUTO_INCREMENT,
  class_id INT NOT NULL,
  course_id INT NOT NULL,
  semester_id INT NOT NULL,
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE CASCADE,
  UNIQUE KEY uq_class_course_sem (class_id, course_id, semester_id)
);

-- =========================================
-- 10) AVAILABILITY GUARDS (hard validation for sessions)
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
-- 11) SESSIONS and SESSION TYPES and DAYS and TIMETABLE
-- =========================================
CREATE TABLE sessions (
  id INT PRIMARY KEY AUTO_INCREMENT,
  academic_year VARCHAR(20) NOT NULL,
  semester ENUM('1','2','3') NOT NULL,
  start_date DATE,
  end_date DATE,
  is_active BOOLEAN DEFAULT TRUE
);

CREATE TABLE session_types (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE days (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(20) NOT NULL UNIQUE
);

-- =========================================
-- 12) TIMETABLE (core schedule)
-- =========================================
CREATE TABLE timetable (
  id INT PRIMARY KEY AUTO_INCREMENT,
  session_id INT NOT NULL,
  class_course_id INT NOT NULL,
  lecturer_course_id INT NOT NULL,
  day_id INT NOT NULL,
  time_slot_id INT NOT NULL,
  room_id INT NOT NULL,
  session_type_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
  FOREIGN KEY (class_course_id) REFERENCES class_courses(id) ON DELETE CASCADE,
  FOREIGN KEY (lecturer_course_id) REFERENCES lecturer_courses(id) ON DELETE CASCADE,
  FOREIGN KEY (day_id) REFERENCES days(id),
  FOREIGN KEY (time_slot_id) REFERENCES time_slots(id),
  FOREIGN KEY (room_id) REFERENCES rooms(id),
  FOREIGN KEY (session_type_id) REFERENCES session_types(id),
  UNIQUE KEY uq_tt_slot (session_id, day_id, time_slot_id, room_id)
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

SET FOREIGN_KEY_CHECKS=1;


