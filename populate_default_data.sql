-- Populate default data for new schema tables
-- Run this after applying the new schema

-- Populate days table
INSERT INTO days (name) VALUES 
('Monday'),
('Tuesday'), 
('Wednesday'),
('Thursday'),
('Friday'),
('Saturday'),
('Sunday');

-- Populate session_types table
INSERT INTO session_types (name) VALUES 
('Lecture'),
('Tutorial'),
('Laboratory'),
('Seminar'),
('Workshop'),
('Examination'),
('Project Work');

-- Populate levels table
INSERT INTO levels (name, year_number) VALUES 
('Level 100', 1),
('Level 200', 2),
('Level 300', 3),
('Level 400', 4);

-- Populate programs table (example data - adjust as needed)
INSERT INTO programs (department_id, name, code, duration_years, is_active) VALUES 
(1, 'Bachelor of Science in Computer Science', 'BSc CS', 4, 1),
(1, 'Bachelor of Science in Information Technology', 'BSc IT', 4, 1);

-- Note: You may need to adjust department_id values based on your existing departments
