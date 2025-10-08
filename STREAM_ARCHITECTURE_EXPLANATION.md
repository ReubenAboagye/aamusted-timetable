# Stream Architecture Explanation

## Purpose of Multiple Streams

The timetable system uses **separate streams** to manage different academic delivery modes, each with **distinct courses and course codes**.

### Stream Types

1. **Regular Stream** (REG)
   - Traditional weekday classes
   - Has its own set of course codes
   - Currently: 97 courses (intentionally inactive)

2. **Sandwich Stream** (SAND)
   - Sandwich program delivery mode
   - Has **different course codes** from Regular
   - Currently: 1 course (active, needs more courses)
   - **This is working as designed!**

3. **Evening Stream** (EVE)
   - Evening/weekend classes
   - Will have its own course codes when populated

---

## Why Streams Are Separate

### ✅ Correct Implementation

**Streams are kept separate to prevent:**
- Course code conflicts between delivery modes
- Confusion in timetable generation
- Cross-stream assignment errors
- Mixed data in reports

**Example:**
- Regular stream might have: `ACC 121 - Financial Accounting I`
- Sandwich stream might have: `ACC 125 - Financial Accounting (Sandwich)`
- **Different codes, different schedules, different students**

---

## Current System Status

### ✅ What's Working Correctly

1. ✅ **Stream Isolation**
   - Regular stream courses stay in Regular (inactive, preserved)
   - Sandwich stream courses stay in Sandwich (active)
   - No cross-contamination

2. ✅ **Foreign Key Constraints**
   - `courses.stream_id → streams.id` enforced
   - `classes.stream_id → streams.id` enforced
   - Database prevents invalid stream assignments

3. ✅ **Stream-Specific Filtering**
   - Assignment pages show only current stream's data
   - Switching streams updates all filters automatically
   - No cross-stream course visibility

### 📋 What Needs to Be Done

**To fully populate the Sandwich stream**, you need to:

1. **Add Sandwich-specific courses** with unique course codes
2. **Add Sandwich-specific classes** for the sandwich program
3. **Assign lecturers to Sandwich courses**
4. **Map courses to Sandwich classes**

---

## How to Populate Sandwich Stream

### Method 1: Manual Entry (Small Number of Courses)

1. Go to **Courses Management** page
2. Click **"Add Course"**
3. Enter course details (code, name, department, hours)
4. Course will automatically be assigned to **Sandwich stream** (active stream)
5. Repeat for all Sandwich courses

### Method 2: CSV Bulk Import (Many Courses)

1. Prepare a CSV file with your Sandwich courses:
   ```csv
   name,code,department_id,hours_per_week,is_active
   Financial Accounting (Sandwich),ACC 125,1,3,1
   Business Mathematics (Sandwich),MTH 135,2,3,1
   Management Principles (Sandwich),MGT 145,3,3,1
   ```

2. Go to **Courses Management** page
3. Click **"Import"** button
4. Upload your CSV file
5. All courses will be automatically assigned to the **active stream** (Sandwich)

### Method 3: Programmatic Creation

If you want to create similar courses with different codes:

```php
// Example: Create Sandwich versions of core courses
// This is just a template - customize as needed

include 'connect.php';
include 'includes/stream_manager.php';

$streamManager = getStreamManager();
$sandwich_stream_id = 5; // Sandwich stream

// Define your Sandwich courses
$sandwich_courses = [
    ['code' => 'ACC 125', 'name' => 'Financial Accounting (Sandwich)', 'dept_id' => 1],
    ['code' => 'MTH 135', 'name' => 'Business Mathematics (Sandwich)', 'dept_id' => 2],
    // ... add more courses
];

foreach ($sandwich_courses as $course) {
    $stmt = $conn->prepare(
        "INSERT INTO courses (code, name, department_id, stream_id, hours_per_week, is_active) 
         VALUES (?, ?, ?, ?, 3, 1)"
    );
    $stmt->bind_param("ssii", 
        $course['code'], 
        $course['name'], 
        $course['dept_id'], 
        $sandwich_stream_id
    );
    $stmt->execute();
}
```

---

## Stream Switching Best Practices

### When to Use Each Stream

**Use Regular Stream When:**
- Working with traditional weekday programs
- Planning regular semester timetables
- Managing full-time on-campus courses

**Use Sandwich Stream When:**
- Working with sandwich program delivery
- Different course codes for sandwich mode
- Separate scheduling requirements

**Use Evening Stream When:**
- Managing evening/weekend programs
- Part-time student schedules
- After-hours course delivery

### Important Notes

1. **Always check which stream is active** before adding data
2. **Course codes should be unique within a stream** but can differ across streams
3. **When you switch streams**, all assignment pages automatically filter to show only that stream's data
4. **Deactivated streams are preserved** - their data remains intact but hidden

---

## Database Integrity

### Foreign Key Protections

The system now enforces referential integrity:

1. **Cannot delete a stream** if it has courses assigned to it
2. **Cannot assign courses to non-existent streams**
3. **Orphaned records are prevented** by database constraints

### Data Safety

- Deactivating a stream **does NOT delete its data**
- Regular stream's 97 courses are **safely preserved**
- You can reactivate Regular stream anytime without data loss
- Each stream maintains its own independent dataset

---

## Recommended Next Steps

For your Sandwich stream setup:

1. ✅ **Stream filtering is working** - Already done
2. ✅ **Foreign keys are in place** - Already done
3. ✅ **Database structure is correct** - Already done
4. 📋 **TODO: Add Sandwich courses** - Use import or manual entry
5. 📋 **TODO: Add Sandwich classes** - After courses are added
6. 📋 **TODO: Assign lecturers** - Map lecturers to Sandwich courses
7. 📋 **TODO: Map courses to classes** - Complete the setup

---

## Summary

✅ **Your current setup is architecturally correct**
- Regular stream inactive with 97 courses: **Correct**
- Sandwich stream active with 1 course: **Correct** (just needs more courses)
- Separate course codes per stream: **Correct**
- Stream-specific filtering working: **Correct**

🎯 **Next Action**: Add your Sandwich-specific courses using the import feature or manual entry on the Courses Management page. They will automatically be assigned to the active Sandwich stream.

---

*Last Updated: October 8, 2025*




