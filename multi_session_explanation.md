# Multi-Session Timetabling System Enhancement

## 🎯 **Overview**
The enhanced system now supports multiple session types beyond just "Regular" semester, including:
- **Regular** (Full-time, 8 AM - 6 PM, Mon-Fri)
- **Evening** (6 PM - 10 PM, Mon-Fri)
- **Weekend** (9 AM - 5 PM, Sat-Sun)
- **Sandwich** (Intensive, practical-focused, Mon-Fri)
- **Distance** (Flexible, online components, extended hours)
- **Summer/Winter** (Intensive sessions)

## 🗄️ **New Database Fields Added**

### **1. Sessions Table - Enhanced Session Management**
```sql
ALTER TABLE `sessions` ADD COLUMN:
- `session_type` ENUM('regular','evening','weekend','sandwich','distance','summer','winter')
- `session_hours` JSON - Operating hours for each session type
- `max_courses_per_session` INT - Maximum courses per session
- `session_constraints` JSON - Session-specific scheduling rules
- `is_active` BOOLEAN - Whether session is currently active
```

### **2. Classes Table - Session Preferences**
```sql
ALTER TABLE `classes` ADD COLUMN:
- `session_preferences` JSON - Which sessions this class can participate in
- `max_weekly_hours` INT - Maximum hours per week for this class
```

### **3. Courses Table - Session Availability**
```sql
ALTER TABLE `courses` ADD COLUMN:
- `session_availability` JSON - Which sessions this course can be offered in
- `session_specific_constraints` JSON - Session-specific requirements
```

### **4. Lecturers Table - Multi-Session Teaching**
```sql
ALTER TABLE `lecturers` ADD COLUMN:
- `session_availability` JSON - Which sessions this lecturer can teach in
- `session_preferences` JSON - Session preferences and constraints
- `max_sessions_per_week` INT - Maximum different sessions per week
```

### **5. Rooms Table - Session-Specific Availability**
```sql
ALTER TABLE `rooms` ADD COLUMN:
- `session_availability` JSON - Which sessions this room can be used in
- `session_specific_hours` JSON - Session-specific operating hours
```

### **6. Time Slots Table - Session Restrictions**
```sql
ALTER TABLE `time_slots` ADD COLUMN:
- `session_specific` BOOLEAN - Whether slot is specific to certain sessions
- `session_restrictions` JSON - Which sessions can use this time slot
```

### **7. Working Days Table - Session Hours**
```sql
ALTER TABLE `working_days` ADD COLUMN:
- `session_specific_hours` JSON - Different hours for different session types
- `session_availability` JSON - Which sessions can use this working day
```

### **8. Timetable Entries Table - Session Tracking**
```sql
ALTER TABLE `timetable_entries` ADD COLUMN:
- `session_id` INT - Which session this entry belongs to
- `session_type` VARCHAR(50) - Session type for easier querying
- `cross_session_conflicts` JSON - Conflicts with other sessions
```

## 🔧 **Session-Specific Constraint Definitions**

### **Regular Session**
```json
{
  "hours": {"start": "08:00", "end": "18:00"},
  "days": ["monday", "tuesday", "wednesday", "thursday", "friday"],
  "max_daily_courses": 4,
  "max_weekly_hours": 30,
  "preferred_room_types": ["lecture_hall", "classroom", "laboratory"],
  "break_patterns": ["12:00-13:00", "15:00-15:15"]
}
```

### **Evening Session**
```json
{
  "hours": {"start": "18:00", "end": "22:00"},
  "days": ["monday", "tuesday", "wednesday", "thursday", "friday"],
  "max_daily_courses": 2,
  "max_weekly_hours": 15,
  "preferred_room_types": ["lecture_hall", "classroom"],
  "break_patterns": ["19:30-19:45"],
  "intensive_courses": true
}
```

### **Weekend Session**
```json
{
  "hours": {"start": "09:00", "end": "17:00"},
  "days": ["saturday", "sunday"],
  "max_daily_courses": 3,
  "max_weekly_hours": 12,
  "preferred_room_types": ["lecture_hall", "classroom", "seminar_room"],
  "break_patterns": ["12:00-13:00"],
  "intensive_courses": true
}
```

### **Sandwich Session**
```json
{
  "hours": {"start": "08:00", "end": "18:00"},
  "days": ["monday", "tuesday", "wednesday", "thursday", "friday"],
  "max_daily_courses": 2,
  "max_weekly_hours": 20,
  "preferred_room_types": ["laboratory", "computer_lab", "seminar_room"],
  "break_patterns": ["12:00-13:00"],
  "practical_focus": true,
  "industry_partnerships": true
}
```

## 🎯 **How Multi-Session Timetabling Works**

### **Step 1: Session Grouping**
- Courses are grouped by their preferred session types
- Classes are checked for session participation eligibility
- Rooms are filtered by session availability

### **Step 2: Session-Specific Scheduling**
- Each session gets its own timetable generation
- Session-specific constraints are applied
- Time slots are filtered by session restrictions

### **Step 3: Cross-Session Conflict Detection**
- Prevents same class being scheduled in multiple sessions at same time
- Prevents same room being used across sessions simultaneously
- Prevents same lecturer teaching in multiple sessions simultaneously

### **Step 4: Session-Aware Fitness Calculation**
- Hard constraints: Must be satisfied within each session
- Soft constraints: Preferred but not required
- Cross-session penalties: Additional penalties for conflicts between sessions

## 📊 **New Database Views Created**

### **1. Session Overview**
```sql
CREATE VIEW `session_overview` AS
SELECT 
    s.id as session_id,
    s.name as session_name,
    s.session_type,
    s.is_active,
    COUNT(DISTINCT te.id) as total_courses,
    COUNT(DISTINCT te.class_id) as total_classes,
    COUNT(DISTINCT te.room_id) as total_rooms_used
FROM sessions s
LEFT JOIN timetable_entries te ON s.id = te.session_id
GROUP BY s.id;
```

### **2. Cross-Session Conflicts**
```sql
CREATE VIEW `cross_session_conflicts` AS
-- Shows conflicts between different sessions
-- Helps identify scheduling issues across session types
```

## 🚀 **New Database Functions**

### **1. CheckCrossSessionConflicts**
```sql
CREATE FUNCTION CheckCrossSessionConflicts(session1_id INT, session2_id INT) 
RETURNS INT
-- Counts conflicts between two different sessions
-- Returns number of conflicts found
```

## 💡 **Benefits of Multi-Session System**

### **1. Flexible Scheduling**
- **Regular students** get traditional 8 AM - 6 PM schedule
- **Working professionals** can attend evening classes
- **Part-time students** can choose weekend sessions
- **Industry partners** can participate in sandwich programs

### **2. Resource Optimization**
- **Rooms** can be used across different sessions
- **Lecturers** can teach in multiple session types
- **Equipment** can be shared between sessions
- **Facilities** can operate extended hours

### **3. Student Choice**
- **Full-time students** can mix regular and evening courses
- **Part-time students** can choose flexible schedules
- **Working students** can balance work and study
- **International students** can choose intensive sessions

### **4. Institutional Benefits**
- **Higher enrollment** through flexible scheduling
- **Better resource utilization** across time periods
- **Increased revenue** from multiple session offerings
- **Competitive advantage** in education market

## 🔍 **Example Use Cases**

### **Case 1: Computer Science Program**
```
Regular Session (8 AM - 6 PM):
- CS101: Programming Fundamentals (Lecture Hall A)
- CS102: Data Structures (Computer Lab B)
- CS103: Algorithms (Classroom 101)

Evening Session (6 PM - 10 PM):
- CS201: Database Systems (Computer Lab B)
- CS202: Web Development (Computer Lab A)

Weekend Session (9 AM - 5 PM):
- CS301: Software Engineering (Seminar Room)
- CS302: Project Management (Classroom 102)
```

### **Case 2: Business Administration Program**
```
Regular Session:
- BUS101: Introduction to Business (Lecture Hall)
- BUS102: Accounting Principles (Classroom)

Evening Session:
- BUS201: Marketing Management (Classroom)
- BUS202: Financial Management (Classroom)

Sandwich Session:
- BUS301: Industry Internship (Industry Partner)
- BUS302: Capstone Project (Seminar Room)
```

## 🛠️ **Implementation Steps**

### **1. Database Schema Update**
```bash
mysql -u root -p timetable < update_schema_v2.sql
```

### **2. Data Population**
- Update existing sessions with session types
- Set session-specific constraints for courses
- Configure lecturer session availability
- Set room session restrictions

### **3. System Integration**
- Use `GeneticAlgorithmV3` class for multi-session timetabling
- Configure session-specific constraint weights
- Set up cross-session conflict detection
- Implement session-aware fitness functions

### **4. Testing and Validation**
- Test individual session timetabling
- Verify cross-session conflict detection
- Validate session-specific constraints
- Check resource utilization across sessions

## 🎯 **Next Steps**

1. **Run the schema update** to add multi-session fields
2. **Configure session types** for your institution
3. **Set session constraints** for courses and classes
4. **Test the enhanced system** with multiple sessions
5. **Monitor cross-session conflicts** and resolve issues
6. **Optimize session scheduling** based on usage patterns

This multi-session system transforms your timetable generator into a comprehensive, flexible scheduling solution that can handle the complex needs of modern educational institutions!

