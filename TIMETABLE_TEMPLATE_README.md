# Timetable Template

A modern, interactive timetable management interface inspired by React-based designs, adapted for PHP and the existing AAMUSTED Timetable System.

## Features

### 🎯 Interactive Timetable Interface
- **Click-to-Edit**: Click on any cell to add or edit courses
- **Multi-hour Courses**: Support for courses spanning multiple time slots
- **Visual Feedback**: Color-coded courses with hover effects
- **Responsive Design**: Works on desktop and mobile devices

### 🔧 Advanced Functionality
- **Session Filtering**: Filter by academic session and semester
- **Timetable Types**: Support for both lecture and exam timetables
- **Time Exclusions**: Reserve time slots for special courses (African Studies, Liberal Courses, etc.)
- **Real-time Updates**: AJAX-powered data loading and saving

### 🎨 Modern UI/UX
- **Bootstrap 5**: Modern styling with consistent design language
- **Font Awesome Icons**: Intuitive iconography throughout
- **Modal Dialogs**: Clean, focused editing experience
- **Color Coding**: Visual distinction between different course types

## File Structure

```
├── timetable_template.php          # Main timetable template interface
├── api_timetable_template.php      # API endpoint for AJAX operations
├── includes/
│   ├── header.php                  # Updated with new styling
│   └── sidebar.php                 # Updated with navigation link
└── index.php                       # Updated with dashboard link
```

## Usage

### Accessing the Template
1. Navigate to the main dashboard
2. Click on "Timetable Template" in the grid or sidebar
3. Or directly visit `/timetable_template.php`

### Adding Courses
1. Click on any empty cell in the timetable
2. Fill in the course details:
   - Course Code (e.g., MATH101)
   - Start Time
   - Classes (can add multiple)
3. Click "Save" to add the course

### Editing Courses
1. Click on any existing course cell
2. Modify the details as needed
3. Click "Save" to update or "Delete" to remove

### Setting Time Exclusions
1. Click "Generate Timetable" button
2. In the modal, click "Add Exclusion"
3. Set the day, time range, and reason
4. Excluded time slots will be marked as "RESERVED"

### Filtering Data
- Use the filter controls at the top to select:
  - Academic Session
  - Semester (1 or 2)
  - Timetable Type (Lecture or Exam)

## API Endpoints

The `api_timetable_template.php` provides the following endpoints:

### GET Endpoints
- `get_timetable_data` - Retrieve timetable data with filters
- `get_sessions` - Get all academic sessions
- `get_rooms` - Get all rooms
- `get_time_slots` - Get all time slots
- `get_days` - Get all days
- `get_courses` - Get all active courses
- `get_classes` - Get classes (optionally filtered by session)
- `get_lecturers` - Get all active lecturers

### POST Endpoints
- `save_course` - Save a new course to the timetable
- `delete_course` - Delete a course from the timetable

## Database Integration

The template integrates with the existing database schema:

### Tables Used
- `sessions` - Academic sessions
- `days` - Days of the week
- `time_slots` - Available time slots
- `rooms` - Physical rooms
- `classes` - Student classes
- `courses` - Available courses
- `lecturers` - Teaching staff
- `timetable` - Main timetable data

### Schema Compatibility
The template supports both old and new schema variants:
- **New Schema**: Uses `class_course_id` and `lecturer_course_id`
- **Old Schema**: Uses `class_id`, `course_id`, and `lecturer_id` directly

## Styling

### CSS Classes
- `.timetable-container` - Main timetable wrapper
- `.timetable-header` - Header with gradient background
- `.timetable-table` - Main table styling
- `.course-cell` - Individual course cell styling
- `.empty-cell` - Empty cell styling
- `.reserved-cell` - Excluded time slot styling
- `.multi-hour-course` - Multi-hour course styling

### Color Scheme
- **Primary**: Maroon (#800020) - Brand color
- **Secondary**: Blue (#0d6efd) - Action buttons
- **Success**: Green (#198754) - Success states
- **Danger**: Red (#dc3545) - Error states
- **Course Colors**: Various pastel colors for different course types

## Browser Compatibility

- **Modern Browsers**: Chrome, Firefox, Safari, Edge
- **Mobile**: Responsive design for tablets and phones
- **JavaScript**: ES6+ features with fallbacks

## Future Enhancements

### Planned Features
- **Drag & Drop**: Move courses between time slots
- **Bulk Operations**: Add multiple courses at once
- **Conflict Detection**: Visual indicators for scheduling conflicts
- **Export Options**: PDF, Excel, and image exports
- **Advanced Filters**: Filter by lecturer, course type, etc.
- **Real-time Collaboration**: Multiple users editing simultaneously

### Technical Improvements
- **WebSocket Integration**: Real-time updates
- **Progressive Web App**: Offline capability
- **Advanced Caching**: Improved performance
- **API Rate Limiting**: Better security
- **Audit Logging**: Track all changes

## Troubleshooting

### Common Issues

1. **Courses not saving**
   - Check database connection
   - Verify course codes exist in database
   - Ensure time slots are not conflicting

2. **Page not loading**
   - Check PHP error logs
   - Verify all required files exist
   - Check database permissions

3. **Styling issues**
   - Clear browser cache
   - Check CSS file paths
   - Verify Bootstrap is loading

### Debug Mode
Enable debug mode by adding `?debug=1` to the URL to see detailed error messages.

## Contributing

When contributing to the timetable template:

1. Follow the existing code style
2. Test on multiple browsers
3. Ensure mobile responsiveness
4. Update documentation
5. Add appropriate error handling

## License

This timetable template is part of the AAMUSTED Timetable System and follows the same licensing terms.
