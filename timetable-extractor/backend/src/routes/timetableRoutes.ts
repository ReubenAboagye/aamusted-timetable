import { Router } from 'express';
import { TimetableController } from '../controllers/timetableController';

const router = Router();

// GET /api/timetables - Get all timetables with optional filters
router.get('/', TimetableController.getTimetables);

// GET /api/timetables/filters - Get available filter options
router.get('/filters', TimetableController.getFilterOptions);

// GET /api/timetables/:id - Get specific timetable by ID
router.get('/:id', TimetableController.getTimetableById);

export default router;