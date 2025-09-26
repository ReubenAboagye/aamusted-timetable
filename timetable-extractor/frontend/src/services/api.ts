import axios from 'axios';
import { TimetableEntry, TimetableFilters, FilterOptions, ApiResponse } from '../types/timetable';

const API_BASE_URL = process.env.REACT_APP_API_URL || 'http://localhost:5000/api';

const api = axios.create({
  baseURL: API_BASE_URL,
  timeout: 10000,
  headers: {
    'Content-Type': 'application/json',
  },
});

// Request interceptor
api.interceptors.request.use(
  (config) => {
    console.log(`Making ${config.method?.toUpperCase()} request to ${config.url}`);
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Response interceptor
api.interceptors.response.use(
  (response) => {
    return response;
  },
  (error) => {
    console.error('API Error:', error.response?.data || error.message);
    return Promise.reject(error);
  }
);

export const timetableService = {
  // Get all timetables with optional filters
  getTimetables: async (filters: TimetableFilters = {}): Promise<ApiResponse<TimetableEntry[]>> => {
    const params = new URLSearchParams();
    
    Object.entries(filters).forEach(([key, value]) => {
      if (value) {
        params.append(key, value);
      }
    });

    const response = await api.get(`/timetables?${params.toString()}`);
    return response.data;
  },

  // Get available filter options
  getFilterOptions: async (): Promise<ApiResponse<FilterOptions>> => {
    const response = await api.get('/timetables/filters');
    return response.data;
  },

  // Get specific timetable by ID
  getTimetableById: async (id: number): Promise<ApiResponse<TimetableEntry>> => {
    const response = await api.get(`/timetables/${id}`);
    return response.data;
  },
};

export default api;