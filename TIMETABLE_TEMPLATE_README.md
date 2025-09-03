# Option 2 Timetable Implementation

A modern, interactive timetable generation and editing workflow implemented in the existing `generate_timetable.php` page, providing a seamless experience where generated timetables can be viewed, edited, and saved on the same page.

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

### 🚀 **Option 2 Workflow Implementation**
- **Generate on Same Page**: Timetable generation happens on the generate_timetable.php page
- **Immediate Preview**: Generated timetables appear on the same page for immediate review
- **Save to Saved Timetables**: One-click save to the saved timetables section
- **Context Preservation**: All generation parameters remain visible during the process
- **Loading States**: Visual feedback during generation and saving processes
- **Edit Integration**: Seamless integration with existing view_timetable.php for editing

### 🎨 Modern UI/UX
- **Bootstrap 5**: Modern styling with consistent design language
- **Font Awesome Icons**: Intuitive iconography throughout
- **Modal Dialogs**: Clean, focused editing experience
- **Color Coding**: Visual distinction between different course types

## File Structure

```
├── generate_timetable.php          # Main interface for generation, editing, and saving
├── api_timetable_template.php      # API endpoint for AJAX operations
├── saved_timetable.php             # View saved timetables
├── includes/
│   ├── header.php                  # Updated with new styling
│   └── sidebar.php                 # Updated with navigation link
└── index.php                       # Updated with dashboard link
```

## Usage

### **Option 2 Workflow (Recommended)**

1. **Navigate to Generate Timetable**: Click on "Generate Timetable" in the sidebar
2. **Set Generation Parameters**: Enter timetable name and select semester
3. **Generate Timetable**: Click "Generate Lecture Timetable" to create a new timetable
   - Loading state shows generation progress
   - Generated timetable preview appears on the same page
   - "Save Timetable" button becomes available
4. **Review Generated Timetable**: View the timetable preview on the same page
5. **Edit Timetable**: Click "Edit" button to edit directly on the same page
6. **Save Changes**: Save edits to the database
7. **Save to Saved Timetables**: Click "Save Timetable" to store the final version
8. **View Saved Timetables**: Navigate to "Saved Timetables" to view your saved timetables

### **Key Pages in Option 2**
- **Generate Timetable**: Complete interface for generation, editing, and saving
- **Saved Timetables**: View saved timetable versions

### **Key Benefits of Option 2**
- **Seamless Workflow**: No page navigation between generation and editing
- **Context Preservation**: All parameters remain visible during editing
- **Quick Iteration**: Easy to regenerate if needed
- **Visual Feedback**: Clear indication of generation and save status

### **Integration with Existing System**
1. **Generation**: Uses existing generation logic in generate_timetable.php
2. **Preview**: Shows generated timetable preview on the same page
3. **Inline Editing**: Edit timetable entries directly on the generate page
4. **Database Integration**: Saves changes directly to the timetable table
5. **Saving**: Creates entries in the saved_timetables table

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
- `get_generated_timetable` - Retrieve generated timetable data
- `save_generated_timetable` - Save generated timetable to saved timetables
- `generate_timetable` - Generate a new timetable with exclusions
- `save_timetable` - Save the current timetable to saved timetables

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
