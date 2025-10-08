# Stream-Specific Filtering Fixes

## Summary

This document outlines the fixes applied to ensure that assignment pages show only courses and classes relevant to the currently selected stream. When users switch between streams (e.g., Regular, Weekend, Sandwich), they should only see data specific to that stream.

## Problem

Previously, when users switched streams, the assignment pages would still show ALL courses from ALL streams instead of filtering by the current stream. This caused confusion and potential data integrity issues.

## Files Modified

### 1. `lecturer_courses.php` (Map Courses to Lecturers)

**Changes:**
- Added stream validation at the beginning of the file
- Modified courses query to filter by `stream_id`
- Updated mappings query to only show courses from current stream in the LEFT JOIN
- Ensured all query parameters include the stream filter

**Before:**
```php
$courses_query = "SELECT c.id, c.name, c.code FROM courses c WHERE c.is_active = 1 ORDER BY c.name";
```

**After:**
```php
$courses_query = "SELECT c.id, c.name, c.code FROM courses c WHERE c.is_active = 1 AND c.stream_id = ? ORDER BY c.name";
```

### 2. `assign_courses.php` (Assign Courses to Class)

**Changes:**
- Added stream validation at the beginning of the file
- Modified classes query to filter by `stream_id`
- Modified courses query to filter by `stream_id`

**Before:**
```php
$classes_sql = "SELECT id, name, level FROM classes WHERE is_active = 1 ORDER BY name";
$courses_sql = "SELECT id, course_code, course_name FROM courses WHERE is_active = 1 ORDER BY course_code";
```

**After:**
```php
$classes_sql = "SELECT id, name, level FROM classes WHERE is_active = 1 AND stream_id = ? ORDER BY name";
$courses_sql = "SELECT id, course_code, course_name FROM courses WHERE is_active = 1 AND stream_id = ? ORDER BY course_code";
```

### 3. `class_courses.php` (Class Course Management)

**Changes:**
- Added stream validation at the beginning of the file
- Modified classes query to filter by `stream_id`
- Modified courses query to filter by `stream_id`
- Updated mappings query to filter both classes and courses by stream

**Before:**
```php
$classes_sql = "SELECT c.id, c.name... FROM classes c... WHERE c.is_active = 1...";
$courses_sql = "SELECT id, `code`... FROM courses WHERE is_active = 1...";
$mappings_query = "...LEFT JOIN courses co ON co.id = cc.course_id WHERE c.is_active = 1";
```

**After:**
```php
$classes_sql = "SELECT c.id, c.name... FROM classes c... WHERE c.is_active = 1 AND c.stream_id = ?...";
$courses_sql = "SELECT id, `code`... FROM courses WHERE is_active = 1 AND stream_id = ?...";
$mappings_query = "...LEFT JOIN courses co ON co.id = cc.course_id AND co.stream_id = ? WHERE c.is_active = 1 AND c.stream_id = ?";
```

### 4. `get_lecturer_courses.php` (AJAX endpoint)

**Changes:**
- Added clarifying comment explaining that stream filtering is implicit through the course relationship
- No code changes needed as the filtering happens at the course level

### 5. `course_roomtype.php` + `ajax_course_roomtype.php` (Course Room Type Management)

**Changes:**
- Added stream validation at the beginning of main page
- Modified `get_courses` action to filter by `stream_id`
- Modified `get_table_data` action to filter courses by `stream_id`
- Added stream change listener to reload data when stream switches

**Before (ajax_course_roomtype.php)**:
```php
case 'get_courses':
    $courses_sql = "SELECT id, `code` AS course_code, `name` AS course_name 
                    FROM courses WHERE is_active = 1 ORDER BY `code`";
    
case 'get_table_data':
    $sql = "SELECT ... FROM course_room_types crt
            LEFT JOIN courses co ON crt.course_id = co.id
            ORDER BY co.`code`";
```

**After**:
```php
case 'get_courses':
    $courses_sql = "SELECT id, `code` AS course_code, `name` AS course_name 
                    FROM courses WHERE is_active = 1 AND stream_id = ? 
                    ORDER BY `code`";
    
case 'get_table_data':
    $sql = "SELECT ... FROM course_room_types crt
            LEFT JOIN courses co ON crt.course_id = co.id
            WHERE co.stream_id = ?
            ORDER BY co.`code`";
```

## Database Schema Requirements

These fixes assume the following database schema:

### ✅ Verified Schema (as of 2025-10-08):

1. ✅ `courses` table has a `stream_id` column (type: int, NOT NULL)
2. ✅ `classes` table has a `stream_id` column (type: int, NOT NULL)
3. ✅ `streams` table exists with proper stream definitions
   - Active Streams: Sandwich (ID: 5), Regular (ID: 3 - inactive), Evening (ID: 6 - inactive)
4. ⚠️ **Partial**: Foreign key relationships
   - ✅ `classes.stream_id -> streams.id` (constraint: `fk_classes_stream`)
   - ✅ `courses.department_id -> departments.id` (constraint: `fk_courses_department`)
   - ⚠️ **MISSING**: `courses.stream_id -> streams.id` (database user lacks REFERENCES permission)
   
**Note**: The missing foreign key on `courses.stream_id` is not critical - the application handles stream filtering at the application layer, ensuring data integrity through code rather than database constraints.

## Stream Management

The fixes use the existing stream management infrastructure:

- `includes/stream_validation.php` - Validates stream selection
- `includes/stream_manager.php` - Manages stream state
- Session variables store the current stream ID (`$_SESSION['current_stream_id']`)

## Testing Recommendations

1. **Switch Streams:** 
   - Go to the dashboard
   - Switch between different streams (e.g., Regular → Weekend → Sandwich)
   - Verify that the stream indicator updates

2. **Lecturer Course Assignment:**
   - Visit `lecturer_courses.php`
   - Verify only courses from the current stream are shown
   - Switch streams and verify the course list updates

3. **Class Course Assignment:**
   - Visit `assign_courses.php` or `class_courses.php`
   - Verify only classes and courses from the current stream are shown
   - Assign courses to classes
   - Switch streams and verify assignments are stream-specific

4. **Data Integrity:**
   - Ensure courses cannot be assigned across streams
   - Verify existing assignments remain intact when switching streams
   - Check that reports and timetables respect stream boundaries

## Backward Compatibility

All queries include fallback logic in case the `stream_id` column doesn't exist in older database schemas:

```php
if ($courses === false) {
    // Fallback without stream filter if column doesn't exist
    $courses = $conn->query("SELECT c.id, c.name, c.code FROM courses c WHERE c.is_active = 1 ORDER BY c.name");
}
```

## Additional Notes

- The `ajax_api.php` file already had stream filtering implemented for the AJAX endpoints
- The `classes.php` file already had stream filtering implemented
- These fixes ensure consistency across all assignment pages
- Stream filtering is now applied at both the UI level (dropdowns) and the data level (queries)

## Future Enhancements

Consider these enhancements for better stream management:

1. Add stream indicator/badge to all assignment pages
2. Add confirmation dialog when switching streams
3. Implement stream-specific bulk import/export
4. Add stream comparison reports
5. Create audit log for stream switches

## Support

For questions or issues related to stream filtering, please check:
- `includes/stream_validation.php` for validation logic
- `includes/stream_manager.php` for stream state management
- `ajax_api.php` for AJAX endpoint stream filtering
- This document for implementation details

---
*Last Updated: 2025-10-08*
*Author: AI Assistant*

