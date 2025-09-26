import pool from '../config/database';

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
  created_at: Date;
  updated_at: Date;
}

export interface TimetableFilters {
  class_name?: string;
  level?: string;
  academic_year?: string;
  day_of_week?: string;
}

export class TimetableModel {
  static async getAllTimetables(filters: TimetableFilters = {}): Promise<TimetableEntry[]> {
    let query = `
      SELECT id, class_name, level, academic_year, subject, teacher, 
             day_of_week, start_time, end_time, room, created_at, updated_at
      FROM timetables 
      WHERE 1=1
    `;
    
    const params: any[] = [];
    
    if (filters.class_name) {
      query += ' AND class_name = ?';
      params.push(filters.class_name);
    }
    
    if (filters.level) {
      query += ' AND level = ?';
      params.push(filters.level);
    }
    
    if (filters.academic_year) {
      query += ' AND academic_year = ?';
      params.push(filters.academic_year);
    }
    
    if (filters.day_of_week) {
      query += ' AND day_of_week = ?';
      params.push(filters.day_of_week);
    }
    
    query += ' ORDER BY day_of_week, start_time';
    
    try {
      const [rows] = await pool.execute(query, params);
      return rows as TimetableEntry[];
    } catch (error) {
      console.error('Error fetching timetables:', error);
      throw new Error('Failed to fetch timetables');
    }
  }

  static async getDistinctValues(): Promise<{
    classes: string[];
    levels: string[];
    academic_years: string[];
    days: string[];
  }> {
    try {
      const [classes] = await pool.execute('SELECT DISTINCT class_name FROM timetables ORDER BY class_name');
      const [levels] = await pool.execute('SELECT DISTINCT level FROM timetables ORDER BY level');
      const [academic_years] = await pool.execute('SELECT DISTINCT academic_year FROM timetables ORDER BY academic_year DESC');
      const [days] = await pool.execute('SELECT DISTINCT day_of_week FROM timetables ORDER BY FIELD(day_of_week, "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday")');

      return {
        classes: (classes as any[]).map(row => row.class_name),
        levels: (levels as any[]).map(row => row.level),
        academic_years: (academic_years as any[]).map(row => row.academic_year),
        days: (days as any[]).map(row => row.day_of_week)
      };
    } catch (error) {
      console.error('Error fetching distinct values:', error);
      throw new Error('Failed to fetch filter options');
    }
  }

  static async getTimetableById(id: number): Promise<TimetableEntry | null> {
    try {
      const [rows] = await pool.execute(
        'SELECT * FROM timetables WHERE id = ?',
        [id]
      );
      const result = rows as TimetableEntry[];
      return result.length > 0 ? result[0] : null;
    } catch (error) {
      console.error('Error fetching timetable by ID:', error);
      throw new Error('Failed to fetch timetable');
    }
  }
}