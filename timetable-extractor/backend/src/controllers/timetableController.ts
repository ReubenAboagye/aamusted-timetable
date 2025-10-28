import { Request, Response } from 'express';
import { TimetableModel, TimetableFilters } from '../models/Timetable';

export class TimetableController {
  static async getTimetables(req: Request, res: Response): Promise<void> {
    try {
      const filters: TimetableFilters = {
        class_name: req.query.class_name as string,
        level: req.query.level as string,
        academic_year: req.query.academic_year as string,
        day_of_week: req.query.day_of_week as string
      };

      // Remove undefined values
      Object.keys(filters).forEach(key => {
        if (filters[key as keyof TimetableFilters] === undefined) {
          delete filters[key as keyof TimetableFilters];
        }
      });

      const timetables = await TimetableModel.getAllTimetables(filters);
      
      res.json({
        success: true,
        data: timetables,
        count: timetables.length
      });
    } catch (error) {
      console.error('Error in getTimetables:', error);
      res.status(500).json({
        success: false,
        message: 'Failed to fetch timetables',
        error: process.env.NODE_ENV === 'development' ? error : undefined
      });
    }
  }

  static async getFilterOptions(req: Request, res: Response): Promise<void> {
    try {
      const options = await TimetableModel.getDistinctValues();
      
      res.json({
        success: true,
        data: options
      });
    } catch (error) {
      console.error('Error in getFilterOptions:', error);
      res.status(500).json({
        success: false,
        message: 'Failed to fetch filter options',
        error: process.env.NODE_ENV === 'development' ? error : undefined
      });
    }
  }

  static async getTimetableById(req: Request, res: Response): Promise<void> {
    try {
      const id = parseInt(req.params.id);
      
      if (isNaN(id)) {
        res.status(400).json({
          success: false,
          message: 'Invalid timetable ID'
        });
        return;
      }

      const timetable = await TimetableModel.getTimetableById(id);
      
      if (!timetable) {
        res.status(404).json({
          success: false,
          message: 'Timetable not found'
        });
        return;
      }

      res.json({
        success: true,
        data: timetable
      });
    } catch (error) {
      console.error('Error in getTimetableById:', error);
      res.status(500).json({
        success: false,
        message: 'Failed to fetch timetable',
        error: process.env.NODE_ENV === 'development' ? error : undefined
      });
    }
  }
}