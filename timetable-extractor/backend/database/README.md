# Database Setup

## Prerequisites
- MySQL 8.0 or higher
- MySQL client or phpMyAdmin

## Setup Instructions

1. **Create Database and Tables**
   ```bash
   mysql -u root -p < schema.sql
   ```

2. **Alternative: Manual Setup**
   - Open MySQL client
   - Run the contents of `schema.sql` file
   - Verify tables are created with sample data

3. **Environment Configuration**
   - Copy `.env.example` to `.env`
   - Update database credentials in `.env` file:
     ```
     DB_HOST=localhost
     DB_PORT=3306
     DB_USER=your_username
     DB_PASSWORD=your_password
     DB_NAME=timetable_db
     ```

## Database Schema

### Tables
- **timetables**: Main table storing timetable entries

### Key Fields
- `class_name`: Class identifier (e.g., "10A", "11B")
- `level`: Academic level (e.g., "Grade 10", "Grade 11")
- `academic_year`: Academic year (e.g., "2024", "2023")
- `subject`: Subject name
- `teacher`: Teacher name
- `day_of_week`: Day of the week
- `start_time`/`end_time`: Class timing
- `room`: Classroom location

### Sample Data
The schema includes sample data for:
- Multiple classes (9A, 10A, 10B, 11A, 12A)
- Different academic levels (Grade 9-12)
- Multiple academic years (2023, 2024)
- Various subjects and teachers

## Performance Optimization
- Indexes created on frequently queried fields
- Composite indexes for common filter combinations
- Optimized for filtering by class, level, and academic year