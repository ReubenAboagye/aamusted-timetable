# Timetable Generation Validation - Stream-Specific

## Overview

The timetable generation system now validates that the **current stream** has all required data before allowing timetable generation. This prevents errors and provides clear guidance on what needs to be completed.

---

## ✅ What Was Implemented

### 1. Stream-Specific Validation

**Before**: Generation would fail with generic errors after clicking "Generate"

**After**: System checks requirements **before generation** and shows clear status

### 2. Visual Readiness Indicator

A new **"Stream Readiness"** panel displays on the generate timetable page showing:
- ✅ Green checkmarks for completed requirements
- ❌ Red X marks for missing requirements
- Direct links to add missing data
- Total count of available items per category

### 3. Disabled Generate Button

The "Generate New Timetable" button is:
- **Disabled** when requirements aren't met (with tooltip)
- **Enabled** only when all required items are present
- Shows helpful message about what's missing

### 4. Enhanced Error Messages

All validation errors now include stream context:
- "No courses found **for the 'Sandwich' stream**"
- "No class-course assignments found **for stream ID 5**"
- Clear, actionable error messages

---

## 📋 Requirements Checked

### Required Items (Must Have)

| Requirement | Description | Link to Fix |
|------------|-------------|-------------|
| ✅ Courses | Courses defined for the stream | courses.php |
| ✅ Classes | Classes defined for the stream | classes.php |
| ✅ Course-Class Assignments | Courses mapped to classes | class_courses.php |
| ✅ Rooms | Rooms available for scheduling | rooms.php |
| ✅ Time Slots | Time slots configured | time_slots.php |
| ✅ Days | Active days configured | streams.php |

### Optional Items (Nice to Have)

| Requirement | Description | Impact if Missing |
|------------|-------------|-------------------|
| ⚠️ Lecturer-Course Assignments | Lecturers assigned to courses | Courses scheduled without lecturers |

---

## 🎨 User Interface

### Readiness Panel (Green - All Ready)

```
┌─────────────────────────────────────────────────┐
│ ✓ Stream Readiness: Sandwich                   │
├─────────────────────────────────────────────────┤
│ ✓ All requirements are met!                    │
│   You can generate a timetable for this stream.│
│                                                 │
│ ✓ Courses                    5 available       │
│ ✓ Classes                    3 available       │
│ ✓ Course-Class Assignments   15 available      │
│ ✓ Lecturer Assignments       12 available      │
│ ✓ Rooms                      20 available      │
│ ✓ Time Slots                 8 available       │
│ ✓ Days                       5 available       │
└─────────────────────────────────────────────────┘
```

### Readiness Panel (Warning - Missing Items)

```
┌─────────────────────────────────────────────────┐
│ ⚠ Stream Readiness: Sandwich                   │
├─────────────────────────────────────────────────┤
│ ⚠ Not Ready: The current stream does not meet  │
│   all requirements. Please complete missing:   │
│                                                 │
│ ✗ Courses               Missing - Add now →    │
│ ✗ Classes               Missing - Add now →    │
│ ✗ Course-Class Assignments  Missing - Add now →│
│ ✓ Rooms                     20 available       │
│ ✓ Time Slots                8 available        │
│ ✓ Days                      5 available        │
└─────────────────────────────────────────────────┘

[Generate Button is DISABLED with tooltip]
```

---

## 🔧 Technical Implementation

### Files Modified

1. **`ga/DBLoader.php`**
   - Enhanced `validateDataForGeneration()` method
   - Added stream-specific error messages
   - Added detailed validation for all requirements
   - Checks for courses without lecturers

2. **`generate_timetable.php`**
   - Added pre-generation readiness check
   - Created visual requirements status panel
   - Disabled generate button when requirements not met
   - Pass stream ID and name to validation
   - Enhanced error display with HTML formatting

### Validation Logic

```php
// In ga/DBLoader.php
public function validateDataForGeneration(array $data, $streamId = null, $streamName = null): array {
    $errors = [];
    $warnings = [];
    
    // Get stream context
    $streamContext = $streamName ? " for the '{$streamName}' stream" : '';
    
    // Check each requirement
    if (empty($data['class_courses'])) {
        $errors[] = "No class-course assignments found{$streamContext}";
    }
    
    if (empty($data['courses'])) {
        $errors[] = "No courses found{$streamContext}";
    }
    
    // ... more checks
    
    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'warnings' => $warnings
    ];
}
```

### Pre-Generation Check

```php
// In generate_timetable.php (before form)
$loader = new DBLoader($conn);
$precheck_data = $loader->loadAll(['stream_id' => $current_stream_id]);
$precheck_stats = $loader->getDataStatistics($precheck_data);

// Build requirements array
$requirements = [
    'courses' => ['count' => $precheck_stats['courses'], 'required' => true],
    'classes' => ['count' => $precheck_stats['classes'], 'required' => true],
    // ... etc
];

// Check if all requirements met
$all_requirements_met = true;
foreach ($requirements as $req) {
    if ($req['required'] && $req['count'] == 0) {
        $all_requirements_met = false;
    }
}
```

### Button Disabling

```php
<button type="submit" class="btn btn-primary btn-lg" 
        <?php echo !$all_requirements_met ? 'disabled' : ''; ?>>
    Generate New Timetable
</button>
```

---

## 🎯 Benefits

### For Users

✅ **Clear Feedback**: Know exactly what's missing before clicking generate  
✅ **No Wasted Time**: Don't wait for generation only to get an error  
✅ **Direct Links**: Click to add missing items immediately  
✅ **Stream-Aware**: See requirements specific to current stream  
✅ **Visual Indicators**: Color-coded status (green/red/yellow)

### For System

✅ **Prevents Errors**: No generation attempts with incomplete data  
✅ **Better UX**: Users understand requirements upfront  
✅ **Resource Savings**: Don't waste processing on invalid attempts  
✅ **Data Integrity**: Ensures proper setup before generation  
✅ **Stream Isolation**: Each stream validated independently

---

## 📊 Validation Criteria

### What Makes a Stream "Ready"

A stream is ready for timetable generation when it has:

1. **At least 1 course** (stream-specific)
2. **At least 1 class** (stream-specific)
3. **At least 1 course-class assignment** (links courses to classes)
4. **At least 1 room** (available for scheduling)
5. **At least 1 time slot** (configured for the stream)
6. **At least 1 day** (active days for scheduling)

### Optional Items

- **Lecturer-course assignments**: Not required, but recommended
  - Without them: Courses scheduled without lecturers
  - With them: Full timetable with lecturer assignments

### Warnings

The system will warn (but not prevent generation) if:
- More assignments than available time slots (may not be feasible)
- Some courses don't have lecturers assigned
- Potential capacity issues

---

## 🔍 Example Scenarios

### Scenario 1: Fresh Sandwich Stream (Current State)

**Stream**: Sandwich (Active)  
**Status**: ⚠️ **Not Ready**

| Item | Count | Status |
|------|-------|--------|
| Courses | 1 | ✅ Met |
| Classes | 0 | ❌ **Missing** |
| Course-Class Assignments | 0 | ❌ **Missing** |
| Rooms | 20 | ✅ Met |
| Time Slots | 8 | ✅ Met |
| Days | 5 | ✅ Met |

**Action Required**: Add classes and assign courses to them

### Scenario 2: Fully Configured Stream

**Stream**: Regular  
**Status**: ✅ **Ready**

| Item | Count | Status |
|------|-------|--------|
| Courses | 97 | ✅ Met |
| Classes | 45 | ✅ Met |
| Course-Class Assignments | 320 | ✅ Met |
| Lecturer-Course Assignments | 85 | ✅ Met (Optional) |
| Rooms | 20 | ✅ Met |
| Time Slots | 8 | ✅ Met |
| Days | 5 | ✅ Met |

**Action Available**: Generate button enabled!

---

## 🚀 How It Works

### Step 1: Page Load

When the generate timetable page loads:
1. Gets current stream from session
2. Loads all stream-specific data
3. Counts items per category
4. Displays readiness panel
5. Enables/disables generate button

### Step 2: User Clicks Generate

If requirements not met:
- Button is disabled (can't click)
- Tooltip shows "Please complete all required items"
- Readiness panel shows what's missing

If requirements met:
- Button is enabled
- Form submits
- Validation runs again server-side
- Generation proceeds or shows detailed errors

### Step 3: Server-Side Validation

Even if button was enabled:
1. Data is loaded for current stream
2. `validateDataForGeneration()` runs
3. If validation fails: Show detailed error with stream context
4. If validation passes: Proceed with generation

**Double validation** ensures data integrity!

---

## 💡 Tips for Users

### Setting Up a New Stream

Follow this order:

1. ✅ **Add Courses** (courses.php)
   - Add stream-specific courses with unique codes
   - Example: SACC125, SMTH135 for Sandwich stream

2. ✅ **Add Classes** (classes.php)
   - Create classes for the stream
   - Assign them to the stream
   - Set capacity and divisions

3. ✅ **Assign Courses to Classes** (class_courses.php)
   - Map which courses each class takes
   - Ensures proper course delivery

4. ✅ **Assign Lecturers to Courses** (lecturer_courses.php) - Optional but recommended
   - Map lecturers to courses they teach
   - Improves timetable quality

5. ✅ **Configure Rooms** (rooms.php)
   - Ensure rooms are available
   - Set capacities and types

6. ✅ **Configure Time Slots** (time_slots.php)
   - Set up time periods
   - Map to stream if needed

7. ✅ **Generate Timetable**
   - All requirements met
   - Button enabled
   - Click to generate!

---

## 🐛 Error Messages

### Example Error Messages

**Missing Classes:**
```
Cannot generate timetable:

No classes found for the 'Sandwich' stream. Please add classes before generating the timetable.
```

**Multiple Issues:**
```
Cannot generate timetable:

No courses found for the 'Sandwich' stream. Please add courses before generating the timetable.
No classes found for the 'Sandwich' stream. Please add classes before generating the timetable.
No class-course assignments found for the 'Sandwich' stream. Please assign courses to classes before generating the timetable.

Warnings:
No lecturer-course assignments found for the 'Sandwich' stream. Some courses may not have lecturers assigned.
```

---

## 🔄 Stream Switching Behavior

When you switch streams:
1. Readiness panel updates to show new stream's status
2. Requirements re-validated for new stream
3. Generate button enabled/disabled based on new stream
4. Each stream tracked independently

**Example:**
- Switch to Regular stream → Shows 97 courses, ready to generate
- Switch to Sandwich stream → Shows 1 course, not ready (missing classes)
- Switch to Evening stream → Shows 0 courses, not ready (empty)

---

## ✨ Summary

### Before This Update
- ❌ Could click generate without proper data
- ❌ Would fail with cryptic errors
- ❌ No indication of what was missing
- ❌ Wasted time attempting generation

### After This Update
- ✅ Clear visual indication of readiness
- ✅ Can't generate without required data
- ✅ Exact list of what's missing
- ✅ Direct links to fix issues
- ✅ Stream-specific validation messages
- ✅ Better user experience

---

**Last Updated**: October 8, 2025  
**Status**: ✅ Production Ready




