-- Create database
CREATE DATABASE IF NOT EXISTS timetable_db;
USE timetable_db;

-- Create timetables table
CREATE TABLE IF NOT EXISTS timetables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_name VARCHAR(50) NOT NULL,
    level VARCHAR(20) NOT NULL,
    academic_year VARCHAR(10) NOT NULL,
    subject VARCHAR(100) NOT NULL,
    teacher VARCHAR(100) NOT NULL,
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    room VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_class_level_year (class_name, level, academic_year),
    INDEX idx_day_time (day_of_week, start_time),
    INDEX idx_academic_year (academic_year)
);

-- Insert sample data
INSERT INTO timetables (class_name, level, academic_year, subject, teacher, day_of_week, start_time, end_time, room) VALUES
-- Grade 10A - 2024
('10A', 'Grade 10', '2024', 'Mathematics', 'Dr. Sarah Johnson', 'Monday', '08:00:00', '09:00:00', 'Room 101'),
('10A', 'Grade 10', '2024', 'English Literature', 'Prof. Michael Brown', 'Monday', '09:15:00', '10:15:00', 'Room 102'),
('10A', 'Grade 10', '2024', 'Physics', 'Dr. Emily Davis', 'Monday', '10:30:00', '11:30:00', 'Lab 1'),
('10A', 'Grade 10', '2024', 'Chemistry', 'Dr. Robert Wilson', 'Monday', '11:45:00', '12:45:00', 'Lab 2'),
('10A', 'Grade 10', '2024', 'History', 'Ms. Jennifer Lee', 'Monday', '14:00:00', '15:00:00', 'Room 103'),

('10A', 'Grade 10', '2024', 'Biology', 'Dr. David Miller', 'Tuesday', '08:00:00', '09:00:00', 'Lab 3'),
('10A', 'Grade 10', '2024', 'Mathematics', 'Dr. Sarah Johnson', 'Tuesday', '09:15:00', '10:15:00', 'Room 101'),
('10A', 'Grade 10', '2024', 'Geography', 'Mr. James Taylor', 'Tuesday', '10:30:00', '11:30:00', 'Room 104'),
('10A', 'Grade 10', '2024', 'Physical Education', 'Coach Mark Anderson', 'Tuesday', '11:45:00', '12:45:00', 'Gym'),
('10A', 'Grade 10', '2024', 'Computer Science', 'Ms. Lisa Garcia', 'Tuesday', '14:00:00', '15:00:00', 'Computer Lab'),

-- Grade 10B - 2024
('10B', 'Grade 10', '2024', 'Mathematics', 'Dr. Sarah Johnson', 'Monday', '08:00:00', '09:00:00', 'Room 105'),
('10B', 'Grade 10', '2024', 'English Literature', 'Prof. Michael Brown', 'Monday', '09:15:00', '10:15:00', 'Room 106'),
('10B', 'Grade 10', '2024', 'Physics', 'Dr. Emily Davis', 'Monday', '10:30:00', '11:30:00', 'Lab 1'),
('10B', 'Grade 10', '2024', 'Chemistry', 'Dr. Robert Wilson', 'Monday', '11:45:00', '12:45:00', 'Lab 2'),
('10B', 'Grade 10', '2024', 'Art', 'Ms. Maria Rodriguez', 'Monday', '14:00:00', '15:00:00', 'Art Studio'),

-- Grade 11A - 2024
('11A', 'Grade 11', '2024', 'Advanced Mathematics', 'Dr. Sarah Johnson', 'Monday', '08:00:00', '09:00:00', 'Room 201'),
('11A', 'Grade 11', '2024', 'English Literature', 'Prof. Michael Brown', 'Monday', '09:15:00', '10:15:00', 'Room 202'),
('11A', 'Grade 11', '2024', 'Advanced Physics', 'Dr. Emily Davis', 'Monday', '10:30:00', '11:30:00', 'Lab 1'),
('11A', 'Grade 11', '2024', 'Advanced Chemistry', 'Dr. Robert Wilson', 'Monday', '11:45:00', '12:45:00', 'Lab 2'),
('11A', 'Grade 11', '2024', 'Economics', 'Dr. Thomas Clark', 'Monday', '14:00:00', '15:00:00', 'Room 203'),

-- Grade 12A - 2024
('12A', 'Grade 12', '2024', 'Calculus', 'Dr. Sarah Johnson', 'Monday', '08:00:00', '09:00:00', 'Room 301'),
('12A', 'Grade 12', '2024', 'Advanced English', 'Prof. Michael Brown', 'Monday', '09:15:00', '10:15:00', 'Room 302'),
('12A', 'Grade 12', '2024', 'Advanced Physics', 'Dr. Emily Davis', 'Monday', '10:30:00', '11:30:00', 'Lab 1'),
('12A', 'Grade 12', '2024', 'Advanced Chemistry', 'Dr. Robert Wilson', 'Monday', '11:45:00', '12:45:00', 'Lab 2'),
('12A', 'Grade 12', '2024', 'Psychology', 'Dr. Amanda White', 'Monday', '14:00:00', '15:00:00', 'Room 303'),

-- Grade 10A - 2023 (Previous year)
('10A', 'Grade 10', '2023', 'Mathematics', 'Dr. Sarah Johnson', 'Monday', '08:00:00', '09:00:00', 'Room 101'),
('10A', 'Grade 10', '2023', 'English Literature', 'Prof. Michael Brown', 'Monday', '09:15:00', '10:15:00', 'Room 102'),
('10A', 'Grade 10', '2023', 'Physics', 'Dr. Emily Davis', 'Monday', '10:30:00', '11:30:00', 'Lab 1'),

-- Grade 9A - 2024 (Lower level)
('9A', 'Grade 9', '2024', 'Basic Mathematics', 'Ms. Rachel Green', 'Monday', '08:00:00', '09:00:00', 'Room 001'),
('9A', 'Grade 9', '2024', 'English Language', 'Mr. John Smith', 'Monday', '09:15:00', '10:15:00', 'Room 002'),
('9A', 'Grade 9', '2024', 'General Science', 'Dr. Patricia Moore', 'Monday', '10:30:00', '11:30:00', 'Lab 4'),
('9A', 'Grade 9', '2024', 'Social Studies', 'Ms. Jennifer Lee', 'Monday', '11:45:00', '12:45:00', 'Room 003'),
('9A', 'Grade 9', '2024', 'Physical Education', 'Coach Mark Anderson', 'Monday', '14:00:00', '15:00:00', 'Gym');

-- Create indexes for better performance
CREATE INDEX idx_class_name ON timetables(class_name);
CREATE INDEX idx_level ON timetables(level);
CREATE INDEX idx_academic_year ON timetables(academic_year);
CREATE INDEX idx_day_of_week ON timetables(day_of_week);
CREATE INDEX idx_teacher ON timetables(teacher);
CREATE INDEX idx_subject ON timetables(subject);