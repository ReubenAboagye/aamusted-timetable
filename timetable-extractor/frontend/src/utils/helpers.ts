import { TimetableEntry } from '../types/timetable';

// Format time from HH:MM:SS to HH:MM
export const formatTime = (time: string): string => {
  return time.substring(0, 5);
};

// Format date for display
export const formatDate = (dateString: string): string => {
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
};

// Group timetables by day of week
export const groupTimetablesByDay = (timetables: TimetableEntry[]): Record<string, TimetableEntry[]> => {
  const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
  const grouped: Record<string, TimetableEntry[]> = {};
  
  // Initialize all days
  days.forEach(day => {
    grouped[day] = [];
  });
  
  // Group timetables by day
  timetables.forEach(timetable => {
    if (grouped[timetable.day_of_week]) {
      grouped[timetable.day_of_week].push(timetable);
    }
  });
  
  // Sort each day's timetables by start time
  Object.keys(grouped).forEach(day => {
    grouped[day].sort((a, b) => a.start_time.localeCompare(b.start_time));
  });
  
  return grouped;
};

// Get day abbreviation
export const getDayAbbreviation = (day: string): string => {
  return day.substring(0, 3).toUpperCase();
};

// Get time period (AM/PM)
export const getTimePeriod = (time: string): string => {
  const hour = parseInt(time.substring(0, 2));
  return hour >= 12 ? 'PM' : 'AM';
};

// Validate filters
export const validateFilters = (filters: any): boolean => {
  return Object.values(filters).some(value => value && value !== '');
};

// Generate timetable summary
export const generateTimetableSummary = (timetables: TimetableEntry[]): string => {
  if (timetables.length === 0) return 'No timetables found';
  
  const uniqueSubjects = new Set(timetables.map(t => t.subject));
  const uniqueTeachers = new Set(timetables.map(t => t.teacher));
  const days = new Set(timetables.map(t => t.day_of_week));
  
  return `${timetables.length} classes, ${uniqueSubjects.size} subjects, ${uniqueTeachers.size} teachers, ${days.size} days`;
};