export interface TimetableEntry {
  id: number;
  class_name: string;
  level: string;
  academic_year: string;
  subject: string;
  teacher: string;
  day_of_week: string;
  start_time: string;
  end_time: string;
  room: string;
  created_at: string;
  updated_at: string;
}

export interface TimetableFilters {
  class_name?: string;
  level?: string;
  academic_year?: string;
  day_of_week?: string;
}

export interface FilterOptions {
  classes: string[];
  levels: string[];
  academic_years: string[];
  days: string[];
}

export interface ApiResponse<T> {
  success: boolean;
  data: T;
  count?: number;
  message?: string;
  error?: any;
}