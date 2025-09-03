# Timetable System Comprehensive Review

## Executive Summary

This document provides a thorough review of the timetable generation system, focusing on database schema normalization, stream-based architecture, application logic, and recommendations for improvement.

## 1. Database Schema Analysis

### Current Schema Structure

The system uses the following main tables:
- **streams** - Defines different session types (Regular, Weekend, Evening)
- **classes** - Academic classes with stream_id foreign key
- **courses** - Course definitions (currently global, not stream-specific)
- **lecturers** - Teaching staff information
- **rooms** - Physical venues for classes
- **departments** - Academic departments
- **programs** - Academic programs
- **class_courses** - Junction table linking classes to courses
- **lecturer_courses** - Junction table linking lecturers to courses
- **timetable** - Generated timetable entries
- **days** - Days of the week
- **time_slots** - Time periods for scheduling

### Normalization Issues

1. **Partial Stream Implementation**
   - Only `classes` table has `stream_id` implemented
   - `courses`, `lecturers`, and `rooms` tables lack stream support
   - Migration file (`stream-migration.sql`) suggests adding stream_id to multiple tables but not applied

2. **Data Redundancy**
   - `rooms` table has both `building` (varchar) and `building_id` fields
   - `stream_availability` is stored as JSON in rooms table instead of normalized junction table
   - Course hours stored in multiple places (`courses.hours_per_week` and potentially in timetable)

3. **Inconsistent Foreign Keys**
   - Some tables use CASCADE on delete, others don't
   - Missing constraints between related entities

4. **Missing Relationships**
   - No direct relationship between programs and courses
   - No relationship between departments and rooms
   - No semester/academic year tracking in main schema

## 2. Stream-Based Architecture Issues

### Current Implementation
- Stream filtering only applies to `classes` table
- `StreamManager` class correctly filters only classes-related queries
- Other entities (courses, lecturers, rooms) are treated as global resources

### Problems Identified

1. **Incomplete Multi-Stream Support**
   - Courses cannot be stream-specific (e.g., evening-only courses)
   - Lecturers cannot have stream preferences
   - Rooms cannot have stream-specific availability

2. **Resource Conflicts**
   - No mechanism to prevent lecturer teaching in multiple streams simultaneously
   - Rooms can be double-booked across streams
   - No stream-specific time slot management

3. **Missing Stream Features**
   - No stream-specific constraints (max hours, preferred times)
   - No cross-stream conflict detection
   - Stream days/times not properly integrated

## 3. Timetable Generation Logic Flaws

### Algorithm Issues

1. **Basic Scheduling Approach**
   - Uses simple random placement with retry mechanism
   - No optimization algorithm (genetic algorithm mentioned but not implemented)
   - Limited conflict resolution capabilities

2. **Constraint Handling**
   - Only checks basic conflicts (room, lecturer, class)
   - No soft constraints (preferences, balanced distribution)
   - No consideration for course sequencing or prerequisites

3. **Performance Concerns**
   - Multiple database queries in loops
   - No caching of frequently accessed data
   - Inefficient conflict checking

### Specific Bugs Found

1. **Lecturer Assignment**
   ```php
   // Line ~470 in generate_timetable.php
   $lecturer_rs = $conn->query("SELECT lecturer_id FROM lecturer_courses WHERE course_id = " . $course_id);
   ```
   - No validation if lecturer exists
   - Random selection without considering workload

2. **Room Selection**
   - Rooms selected randomly without considering capacity match
   - No room type matching with course requirements

3. **Time Slot Selection**
   - Random selection without considering course duration
   - No support for multi-hour sessions

## 4. Application Logic Issues

### Security Vulnerabilities

1. **SQL Injection Risks**
   - Direct string concatenation in queries
   - Inconsistent use of prepared statements
   - Example: `WHERE c.stream_id = " . intval($current_stream_id)`

2. **Missing Input Validation**
   - No validation on POST parameters
   - Academic year/semester inputs not properly sanitized

### Error Handling

1. **Inadequate Error Reporting**
   - Generic error messages don't help debugging
   - No logging mechanism for failed operations
   - Silent failures in constraint violations

### Session Management

1. **Inconsistent Session Keys**
   - Multiple session keys for stream (`current_stream_id`, `active_stream`, `stream_id`)
   - No session timeout handling
   - Session data not validated

## 5. Recommended Schema Improvements

### 1. Normalize Stream Support

```sql
-- Add stream support to courses (optional)
ALTER TABLE courses 
ADD COLUMN stream_specific BOOLEAN DEFAULT FALSE,
ADD COLUMN allowed_streams JSON;

-- Create stream-specific time slots
CREATE TABLE stream_time_slots (
  stream_id INT NOT NULL,
  time_slot_id INT NOT NULL,
  is_active BOOLEAN DEFAULT TRUE,
  PRIMARY KEY (stream_id, time_slot_id),
  FOREIGN KEY (stream_id) REFERENCES streams(id),
  FOREIGN KEY (time_slot_id) REFERENCES time_slots(id)
);

-- Create stream-specific room availability
CREATE TABLE stream_room_availability (
  stream_id INT NOT NULL,
  room_id INT NOT NULL,
  available_days JSON,
  available_times JSON,
  PRIMARY KEY (stream_id, room_id),
  FOREIGN KEY (stream_id) REFERENCES streams(id),
  FOREIGN KEY (room_id) REFERENCES rooms(id)
);

-- Add academic period tracking
CREATE TABLE academic_periods (
  id INT PRIMARY KEY AUTO_INCREMENT,
  academic_year VARCHAR(9) NOT NULL,
  semester ENUM('first', 'second', 'summer') NOT NULL,
  start_date DATE,
  end_date DATE,
  is_current BOOLEAN DEFAULT FALSE,
  UNIQUE KEY (academic_year, semester)
);

-- Link timetable to academic periods
ALTER TABLE timetable 
ADD COLUMN academic_period_id INT,
ADD FOREIGN KEY (academic_period_id) REFERENCES academic_periods(id);
```

### 2. Improve Relationships

```sql
-- Program-Course mapping
CREATE TABLE program_courses (
  program_id INT NOT NULL,
  course_id INT NOT NULL,
  level INT NOT NULL,
  is_mandatory BOOLEAN DEFAULT TRUE,
  PRIMARY KEY (program_id, course_id),
  FOREIGN KEY (program_id) REFERENCES programs(id),
  FOREIGN KEY (course_id) REFERENCES courses(id)
);

-- Course prerequisites
CREATE TABLE course_prerequisites (
  course_id INT NOT NULL,
  prerequisite_id INT NOT NULL,
  PRIMARY KEY (course_id, prerequisite_id),
  FOREIGN KEY (course_id) REFERENCES courses(id),
  FOREIGN KEY (prerequisite_id) REFERENCES courses(id)
);
```

### 3. Add Constraint Tables

```sql
-- Lecturer preferences and constraints
CREATE TABLE lecturer_preferences (
  lecturer_id INT PRIMARY KEY,
  preferred_days JSON,
  preferred_times JSON,
  max_daily_hours INT DEFAULT 6,
  max_weekly_hours INT DEFAULT 20,
  FOREIGN KEY (lecturer_id) REFERENCES lecturers(id)
);

-- Class constraints
CREATE TABLE class_constraints (
  class_id INT PRIMARY KEY,
  preferred_start_time TIME,
  preferred_end_time TIME,
  max_daily_courses INT DEFAULT 3,
  blocked_days JSON,
  FOREIGN KEY (class_id) REFERENCES classes(id)
);
```

## 6. Recommended Application Improvements

### 1. Implement Proper Optimization Algorithm

```php
// Implement genetic algorithm or simulated annealing
class TimetableOptimizer {
    private $population = [];
    private $fitness_weights = [
        'hard_constraints' => 1000,
        'soft_constraints' => 1,
        'distribution' => 10
    ];
    
    public function optimize($assignments, $constraints) {
        // Initialize population
        // Evaluate fitness
        // Apply genetic operations
        // Return best solution
    }
}
```

### 2. Add Constraint Validation Framework

```php
class ConstraintValidator {
    private $constraints = [];
    
    public function addConstraint(Constraint $constraint) {
        $this->constraints[] = $constraint;
    }
    
    public function validate(TimetableEntry $entry) {
        foreach ($this->constraints as $constraint) {
            if (!$constraint->isSatisfied($entry)) {
                return false;
            }
        }
        return true;
    }
}
```

### 3. Implement Caching Layer

```php
class TimetableCache {
    private $cache = [];
    
    public function getRoomAvailability($room_id, $day_id, $time_slot_id) {
        $key = "room_{$room_id}_{$day_id}_{$time_slot_id}";
        if (!isset($this->cache[$key])) {
            $this->cache[$key] = $this->loadFromDatabase($room_id, $day_id, $time_slot_id);
        }
        return $this->cache[$key];
    }
}
```

## 7. Security Recommendations

1. **Use Prepared Statements Everywhere**
```php
$stmt = $conn->prepare("SELECT * FROM classes WHERE stream_id = ?");
$stmt->bind_param("i", $stream_id);
```

2. **Implement Input Validation**
```php
class InputValidator {
    public static function validateAcademicYear($year) {
        return preg_match('/^\d{4}\/\d{4}$/', $year);
    }
    
    public static function validateSemester($semester) {
        return in_array($semester, ['first', 'second', 'summer']);
    }
}
```

3. **Add CSRF Protection**
```php
// Generate token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Verify token
if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    die('CSRF token validation failed');
}
```

## 8. Performance Optimizations

1. **Batch Database Operations**
```php
// Instead of individual inserts
$stmt = $conn->prepare("INSERT INTO timetable (class_id, course_id, ...) VALUES (?, ?, ...)");
foreach ($entries as $entry) {
    $stmt->bind_param("ii...", $entry['class_id'], $entry['course_id'], ...);
    $stmt->execute();
}

// Use batch insert
$values = [];
foreach ($entries as $entry) {
    $values[] = "({$entry['class_id']}, {$entry['course_id']}, ...)";
}
$sql = "INSERT INTO timetable (class_id, course_id, ...) VALUES " . implode(',', $values);
```

2. **Add Database Indexes**
```sql
-- Add composite indexes for common queries
CREATE INDEX idx_timetable_lookup ON timetable(day_id, time_slot_id, room_id);
CREATE INDEX idx_class_stream ON classes(stream_id, is_active);
CREATE INDEX idx_lecturer_course ON lecturer_courses(lecturer_id, course_id);
```

## 9. Feature Enhancements

1. **Multi-Stream Scheduling**
   - Allow courses to be offered in multiple streams
   - Implement cross-stream conflict detection
   - Add stream-specific capacity management

2. **Advanced Constraints**
   - Course sequencing (Course A before Course B on same day)
   - Lecturer travel time between buildings
   - Student group conflicts

3. **Reporting and Analytics**
   - Room utilization reports
   - Lecturer workload analysis
   - Conflict statistics

4. **User Experience**
   - Drag-and-drop timetable editing
   - Real-time conflict detection
   - Bulk operations support

## 10. Implementation Priority

### High Priority
1. Fix SQL injection vulnerabilities
2. Complete stream implementation for all entities
3. Add proper constraint validation
4. Implement academic period tracking

### Medium Priority
1. Optimize database schema (normalization)
2. Implement caching layer
3. Add comprehensive error handling
4. Create constraint framework

### Low Priority
1. Implement advanced optimization algorithms
2. Add analytics and reporting
3. Enhance user interface
4. Add API for external integrations

## Conclusion

The current timetable system has a solid foundation but requires significant improvements in:
- Database normalization and relationships
- Complete multi-stream support
- Security and input validation
- Optimization algorithms
- Error handling and logging

Implementing these recommendations will result in a more robust, scalable, and maintainable timetable generation system.