-- =========================================
-- CONSOLIDATED SESSIONS TABLE (combines sessions + semesters)
-- =========================================
CREATE TABLE academic_sessions (
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
-- SAMPLE DATA
-- =========================================
INSERT INTO academic_sessions (academic_year, semester_number, semester_name, start_date, end_date) VALUES
('2024/2025', '1', 'First Semester 2024/2025', '2024-09-01', '2024-12-20'),
('2024/2025', '2', 'Second Semester 2024/2025', '2025-01-15', '2025-05-15'),
('2024/2025', '3', 'Third Semester 2024/2025', '2025-06-01', '2025-08-31'),
('2025/2026', '1', 'First Semester 2025/2026', '2025-09-01', '2025-12-20'),
('2025/2026', '2', 'Second Semester 2025/2026', '2026-01-15', '2026-05-15'),
('2025/2026', '3', 'Third Semester 2025/2026', '2026-06-01', '2026-08-31');
