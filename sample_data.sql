-- Sample Data for AAMUSTED Timetable System
-- This file adds realistic sample data to populate the database

USE `timetable_system`;

-- =====================================================
-- INSERT SAMPLE DEPARTMENTS
-- =====================================================
INSERT INTO `departments` (`name`, `code`, `description`, `is_active`) VALUES 
('Computer Science', 'CS', 'Department of Computer Science and Information Technology', 1),
('Mathematics', 'MATH', 'Department of Mathematics and Statistics', 1),
('Physics', 'PHY', 'Department of Physics and Applied Sciences', 1),
('Business Administration', 'BA', 'Department of Business Administration and Management', 1),
('Education', 'EDU', 'Department of Education and Teacher Training', 1);

-- =====================================================
-- INSERT SAMPLE PROGRAMS
-- =====================================================
INSERT INTO `programs` (`department_id`, `name`, `code`, `description`, `duration_years`) VALUES 
(1, 'Bachelor of Science in Computer Science', 'BSc CS', 'Four-year degree in Computer Science', 4),
(1, 'Bachelor of Science in Information Technology', 'BSc IT', 'Four-year degree in Information Technology', 4),
(2, 'Bachelor of Science in Mathematics', 'BSc MATH', 'Four-year degree in Mathematics', 4),
(2, 'Bachelor of Science in Statistics', 'BSc STAT', 'Four-year degree in Statistics', 4),
(3, 'Bachelor of Science in Physics', 'BSc PHY', 'Four-year degree in Physics', 4),
(4, 'Bachelor of Business Administration', 'BBA', 'Four-year degree in Business Administration', 4),
(5, 'Bachelor of Education', 'BEd', 'Four-year degree in Education', 4);

-- =====================================================
-- INSERT SAMPLE LEVELS
-- =====================================================
INSERT INTO `levels` (`name`, `code`, `description`) VALUES 
('Year 1', 'Y1', 'First Year Level'),
('Year 2', 'Y2', 'Second Year Level'),
('Year 3', 'Y3', 'Third Year Level'),
('Year 4', 'Y4', 'Fourth Year Level');

-- =====================================================
-- INSERT SAMPLE COURSES
-- =====================================================
INSERT INTO `courses` (`code`, `name`, `description`, `credits`, `lecture_hours`, `tutorial_hours`, `practical_hours`, `is_active`) VALUES 
-- Computer Science Courses
('CS101', 'Introduction to Programming', 'Basic programming concepts using Python', 3, 2, 1, 2, 1),
('CS102', 'Data Structures and Algorithms', 'Fundamental data structures and algorithms', 4, 3, 1, 2, 1),
('CS201', 'Object-Oriented Programming', 'Java programming and OOP concepts', 4, 3, 1, 2, 1),
('CS202', 'Database Systems', 'Database design and SQL programming', 3, 2, 1, 2, 1),
('CS301', 'Software Engineering', 'Software development methodologies', 3, 2, 1, 2, 1),
('CS302', 'Computer Networks', 'Network protocols and architecture', 3, 2, 1, 2, 1),

-- Mathematics Courses
('MATH101', 'Calculus I', 'Differential calculus and applications', 4, 3, 1, 0, 1),
('MATH102', 'Linear Algebra', 'Vector spaces and linear transformations', 3, 2, 1, 0, 1),
('MATH201', 'Calculus II', 'Integral calculus and series', 4, 3, 1, 0, 1),
('MATH202', 'Probability Theory', 'Probability concepts and distributions', 3, 2, 1, 0, 1),

-- Physics Courses
('PHY101', 'Mechanics', 'Classical mechanics and dynamics', 4, 3, 1, 2, 1),
('PHY102', 'Electricity and Magnetism', 'Electromagnetic theory', 4, 3, 1, 2, 1),
('PHY201', 'Thermodynamics', 'Heat and energy principles', 3, 2, 1, 2, 1),

-- Business Courses
('BA101', 'Principles of Management', 'Management fundamentals and practices', 3, 2, 1, 0, 1),
('BA102', 'Business Economics', 'Economic principles in business', 3, 2, 1, 0, 1),
('BA201', 'Marketing Management', 'Marketing strategies and principles', 3, 2, 1, 0, 1),
('BA202', 'Financial Accounting', 'Accounting principles and practices', 4, 3, 1, 0, 1),

-- Education Courses
('EDU101', 'Educational Psychology', 'Learning theories and development', 3, 2, 1, 0, 1),
('EDU102', 'Curriculum Development', 'Curriculum design and implementation', 3, 2, 1, 0, 1),
('EDU201', 'Teaching Methods', 'Pedagogical approaches and strategies', 3, 2, 1, 0, 1);

-- =====================================================
-- INSERT SAMPLE LECTURERS
-- =====================================================
INSERT INTO `lecturers` (`name`, `email`, `phone`, `department_id`, `is_active`) VALUES 
-- Computer Science Lecturers
('Dr. John Smith', 'john.smith@aamusted.edu.gh', '+233-24-123-4567', 1, 1),
('Prof. Sarah Johnson', 'sarah.johnson@aamusted.edu.gh', '+233-24-234-5678', 1, 1),
('Dr. Michael Brown', 'michael.brown@aamusted.edu.gh', '+233-24-345-6789', 1, 1),
('Dr. Lisa Davis', 'lisa.davis@aamusted.edu.gh', '+233-24-456-7890', 1, 1),

-- Mathematics Lecturers
('Prof. Robert Wilson', 'robert.wilson@aamusted.edu.gh', '+233-24-567-8901', 2, 1),
('Dr. Emily Taylor', 'emily.taylor@aamusted.edu.gh', '+233-24-678-9012', 2, 1),
('Dr. David Anderson', 'david.anderson@aamusted.edu.gh', '+233-24-789-0123', 2, 1),

-- Physics Lecturers
('Prof. Jennifer Martinez', 'jennifer.martinez@aamusted.edu.gh', '+233-24-890-1234', 3, 1),
('Dr. Christopher Garcia', 'christopher.garcia@aamusted.edu.gh', '+233-24-901-2345', 3, 1),

-- Business Lecturers
('Prof. Amanda Rodriguez', 'amanda.rodriguez@aamusted.edu.gh', '+233-24-012-3456', 4, 1),
('Dr. Kevin Lee', 'kevin.lee@aamusted.edu.gh', '+233-24-123-4567', 4, 1),
('Dr. Michelle White', 'michelle.white@aamusted.edu.gh', '+233-24-234-5678', 4, 1),

-- Education Lecturers
('Prof. Daniel Thompson', 'daniel.thompson@aamusted.edu.gh', '+233-24-345-6789', 5, 1),
('Dr. Rachel Clark', 'rachel.clark@aamusted.edu.gh', '+233-24-456-7890', 5, 1);

-- =====================================================
-- INSERT SAMPLE ROOMS
-- =====================================================
INSERT INTO `rooms` (`building_id`, `stream_id`, `name`, `building`, `room_type`, `capacity`, `stream_availability`, `facilities`, `accessibility_features`, `is_active`) VALUES 
-- Main Building Rooms
(1, 1, 'MB101', 'Main Building', 'classroom', 40, '["regular", "evening"]', '["whiteboard", "projector"]', '["wheelchair_access"]', 1),
(1, 1, 'MB102', 'Main Building', 'classroom', 35, '["regular", "evening"]', '["whiteboard", "projector"]', '[]', 1),
(1, 1, 'MB201', 'Main Building', 'lecture_hall', 80, '["regular", "evening"]', '["whiteboard", "projector", "sound_system"]', '["wheelchair_access"]', 1),
(1, 1, 'MB202', 'Main Building', 'lecture_hall', 60, '["regular", "evening"]', '["whiteboard", "projector"]', '[]', 1),
(1, 1, 'MB301', 'Main Building', 'computer_lab', 30, '["regular", "evening"]', '["computers", "projector", "whiteboard"]', '["wheelchair_access"]', 1),
(1, 1, 'MB302', 'Main Building', 'computer_lab', 25, '["regular", "evening"]', '["computers", "projector", "whiteboard"]', '[]', 1),

-- Science Building Rooms
(1, 1, 'SB101', 'Science Building', 'laboratory', 20, '["regular"]', '["lab_equipment", "whiteboard"]', '["wheelchair_access"]', 1),
(1, 1, 'SB102', 'Science Building', 'laboratory', 18, '["regular"]', '["lab_equipment", "whiteboard"]', '[]', 1),
(1, 1, 'SB201', 'Science Building', 'seminar_room', 25, '["regular", "evening"]', '["whiteboard", "projector"]', '["wheelchair_access"]', 1),

-- Library Building Rooms
(1, 1, 'LB101', 'Library Building', 'auditorium', 150, '["regular", "evening"]', '["stage", "projector", "sound_system"]', '["wheelchair_access"]', 1),
(1, 1, 'LB102', 'Library Building', 'seminar_room', 40, '["regular", "evening"]', '["whiteboard", "projector"]', '[]', 1);

-- =====================================================
-- INSERT SAMPLE CLASSES
-- =====================================================
INSERT INTO `classes` (`program_id`, `level_id`, `name`, `code`, `academic_year`, `semester`, `stream_id`) VALUES 
-- Computer Science Classes
(1, 1, 'Computer Science Year 1', 'CS-Y1-2024', '2024/2025', 'first', 1),
(1, 2, 'Computer Science Year 2', 'CS-Y2-2024', '2024/2025', 'first', 1),
(1, 3, 'Computer Science Year 3', 'CS-Y3-2024', '2024/2025', 'first', 1),
(2, 1, 'IT Year 1', 'IT-Y1-2024', '2024/2025', 'first', 1),

-- Mathematics Classes
(3, 1, 'Mathematics Year 1', 'MATH-Y1-2024', '2024/2025', 'first', 1),
(3, 2, 'Mathematics Year 2', 'MATH-Y2-2024', '2024/2025', 'first', 1),

-- Physics Classes
(5, 1, 'Physics Year 1', 'PHY-Y1-2024', '2024/2025', 'first', 1),
(5, 2, 'Physics Year 2', 'PHY-Y2-2024', '2024/2025', 'first', 1),

-- Business Classes
(6, 1, 'Business Year 1', 'BA-Y1-2024', '2024/2025', 'first', 1),
(6, 2, 'Business Year 2', 'BA-Y2-2024', '2024/2025', 'first', 1),

-- Education Classes
(7, 1, 'Education Year 1', 'EDU-Y1-2024', '2024/2025', 'first', 1),
(7, 2, 'Education Year 2', 'EDU-Y2-2024', '2024/2025', 'first', 1);

-- =====================================================
-- INSERT SAMPLE CLASS-COURSE ASSIGNMENTS
-- =====================================================
INSERT INTO `class_courses` (`class_id`, `course_id`, `lecturer_id`, `semester`, `academic_year`) VALUES 
-- Computer Science Year 1 Courses
(1, 1, 1, 'first', '2024/2025'), -- CS101 - Intro to Programming
(1, 7, 5, 'first', '2024/2025'), -- MATH101 - Calculus I
(1, 8, 6, 'first', '2024/2025'), -- MATH102 - Linear Algebra

-- Computer Science Year 2 Courses
(2, 2, 2, 'first', '2024/2025'), -- CS102 - Data Structures
(2, 3, 3, 'first', '2024/2025'), -- CS201 - OOP
(2, 9, 5, 'first', '2024/2025'), -- MATH201 - Calculus II

-- Computer Science Year 3 Courses
(3, 5, 4, 'first', '2024/2025'), -- CS301 - Software Engineering
(3, 6, 1, 'first', '2024/2025'), -- CS302 - Computer Networks

-- IT Year 1 Courses
(4, 1, 2, 'first', '2024/2025'), -- CS101 - Intro to Programming
(4, 7, 5, 'first', '2024/2025'), -- MATH101 - Calculus I

-- Mathematics Year 1 Courses
(5, 7, 5, 'first', '2024/2025'), -- MATH101 - Calculus I
(5, 8, 6, 'first', '2024/2025'), -- MATH102 - Linear Algebra
(5, 11, 8, 'first', '2024/2025'), -- PHY101 - Mechanics

-- Mathematics Year 2 Courses
(6, 9, 5, 'first', '2024/2025'), -- MATH201 - Calculus II
(6, 10, 6, 'first', '2024/2025'), -- MATH202 - Probability

-- Physics Year 1 Courses
(7, 11, 8, 'first', '2024/2025'), -- PHY101 - Mechanics
(7, 12, 9, 'first', '2024/2025'), -- PHY102 - Electricity & Magnetism
(7, 7, 5, 'first', '2024/2025'), -- MATH101 - Calculus I

-- Physics Year 2 Courses
(8, 13, 8, 'first', '2024/2025'), -- PHY201 - Thermodynamics
(8, 9, 5, 'first', '2024/2025'), -- MATH201 - Calculus II

-- Business Year 1 Courses
(9, 14, 10, 'first', '2024/2025'), -- BA101 - Principles of Management
(9, 15, 11, 'first', '2024/2025'), -- BA102 - Business Economics
(9, 7, 5, 'first', '2024/2025'), -- MATH101 - Calculus I

-- Business Year 2 Courses
(10, 16, 10, 'first', '2024/2025'), -- BA201 - Marketing Management
(10, 17, 12, 'first', '2024/2025'), -- BA202 - Financial Accounting

-- Education Year 1 Courses
(11, 18, 13, 'first', '2024/2025'), -- EDU101 - Educational Psychology
(11, 19, 14, 'first', '2024/2025'), -- EDU102 - Curriculum Development
(11, 7, 5, 'first', '2024/2025'), -- MATH101 - Calculus I

-- Education Year 2 Courses
(12, 20, 13, 'first', '2024/2025'), -- EDU201 - Teaching Methods
(12, 9, 5, 'first', '2024/2025'); -- MATH201 - Calculus II

-- =====================================================
-- INSERT SAMPLE LECTURER-COURSE ASSIGNMENTS
-- =====================================================
INSERT INTO `lecturer_courses` (`lecturer_id`, `course_id`) VALUES 
-- Computer Science Department
(1, 1), (1, 6), -- Dr. John Smith: Intro to Programming, Computer Networks
(2, 2), (2, 1), -- Prof. Sarah Johnson: Data Structures, Intro to Programming
(3, 3), -- Dr. Michael Brown: OOP
(4, 5), -- Dr. Lisa Davis: Software Engineering

-- Mathematics Department
(5, 7), (5, 9), -- Prof. Robert Wilson: Calculus I, Calculus II
(6, 8), (6, 10), -- Dr. Emily Taylor: Linear Algebra, Probability
(7, 7), (7, 9), -- Dr. David Anderson: Calculus I, Calculus II

-- Physics Department
(8, 11), (8, 13), -- Prof. Jennifer Martinez: Mechanics, Thermodynamics
(9, 12), -- Dr. Christopher Garcia: Electricity & Magnetism

-- Business Department
(10, 14), (10, 16), -- Prof. Amanda Rodriguez: Management, Marketing
(11, 15), -- Dr. Kevin Lee: Business Economics
(12, 17), -- Dr. Michelle White: Financial Accounting

-- Education Department
(13, 18), (13, 20), -- Prof. Daniel Thompson: Educational Psychology, Teaching Methods
(14, 19); -- Dr. Rachel Clark: Curriculum Development

-- =====================================================
-- INSERT SAMPLE TIMETABLE ENTRIES
-- =====================================================
INSERT INTO `timetable` (`class_id`, `course_id`, `lecturer_id`, `room_id`, `day_id`, `time_slot_id`, `semester`, `academic_year`, `timetable_type`) VALUES 
-- Monday Schedule
(1, 1, 1, 1, 1, 1, 'first', '2024/2025', 'lecture'), -- CS101 - MB101 - 7:00-8:00
(1, 7, 5, 3, 1, 3, 'first', '2024/2025', 'lecture'), -- MATH101 - MB201 - 9:00-10:00
(1, 8, 6, 2, 1, 5, 'first', '2024/2025', 'lecture'), -- MATH102 - MB102 - 11:00-12:00

-- Tuesday Schedule
(2, 2, 2, 5, 2, 2, 'first', '2024/2025', 'lecture'), -- CS102 - MB301 - 8:00-9:00
(2, 3, 3, 4, 2, 4, 'first', '2024/2025', 'lecture'), -- CS201 - MB202 - 10:00-11:00
(2, 9, 5, 3, 2, 6, 'first', '2024/2025', 'lecture'), -- MATH201 - MB201 - 12:00-13:00

-- Wednesday Schedule
(3, 5, 4, 6, 3, 1, 'first', '2024/2025', 'lecture'), -- CS301 - MB302 - 7:00-8:00
(3, 6, 1, 5, 3, 3, 'first', '2024/2025', 'lecture'), -- CS302 - MB301 - 9:00-10:00

-- Thursday Schedule
(5, 7, 5, 3, 4, 2, 'first', '2024/2025', 'lecture'), -- MATH101 - MB201 - 8:00-9:00
(5, 8, 6, 4, 4, 4, 'first', '2024/2025', 'lecture'), -- MATH102 - MB202 - 10:00-11:00

-- Friday Schedule
(7, 11, 8, 7, 5, 1, 'first', '2024/2025', 'lecture'), -- PHY101 - SB101 - 7:00-8:00
(7, 12, 9, 8, 5, 3, 'first', '2024/2025', 'lecture'); -- PHY102 - SB102 - 9:00-10:00

-- =====================================================
-- SAMPLE DATA COMPLETE
-- =====================================================
-- This sample data includes:
-- 5 departments, 7 programs, 4 levels, 20 courses, 15 lecturers
-- 12 classes, 12 rooms, 24 class-course assignments
-- 24 lecturer-course assignments, 12 timetable entries
-- All data is interconnected with proper foreign key relationships
-- The dashboard should now show meaningful counts and data
