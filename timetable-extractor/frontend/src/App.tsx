import React, { useState, useEffect } from 'react';
import { TimetableEntry, TimetableFilters } from './types/timetable';
import { timetableService } from './services/api';
import Header from './components/Header';
import FilterPanel from './components/FilterPanel';
import TimetableGrid from './components/TimetableGrid';
import LoadingSpinner from './components/LoadingSpinner';

function App() {
  const [timetables, setTimetables] = useState<TimetableEntry[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [filters, setFilters] = useState<TimetableFilters>({});

  useEffect(() => {
    loadTimetables();
  }, [filters]);

  const loadTimetables = async () => {
    try {
      setLoading(true);
      setError(null);
      const response = await timetableService.getTimetables(filters);
      
      if (response.success) {
        setTimetables(response.data);
      } else {
        setError(response.message || 'Failed to load timetables');
      }
    } catch (error: any) {
      console.error('Error loading timetables:', error);
      setError(error.response?.data?.message || 'Failed to load timetables');
    } finally {
      setLoading(false);
    }
  };

  const handleFiltersChange = (newFilters: TimetableFilters) => {
    setFilters(newFilters);
  };

  return (
    <div className="min-h-screen bg-gray-50">
      <Header />
      
      <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div className="grid grid-cols-1 lg:grid-cols-4 gap-8">
          {/* Sidebar - Filters */}
          <div className="lg:col-span-1">
            <FilterPanel
              onFiltersChange={handleFiltersChange}
              currentFilters={filters}
            />
          </div>

          {/* Main Content */}
          <div className="lg:col-span-3">
            {error && (
              <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                <div className="flex items-center">
                  <div className="flex-shrink-0">
                    <svg className="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                      <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
                    </svg>
                  </div>
                  <div className="ml-3">
                    <h3 className="text-sm font-medium text-red-800">Error</h3>
                    <p className="text-sm text-red-700 mt-1">{error}</p>
                  </div>
                </div>
              </div>
            )}

            <TimetableGrid timetables={timetables} loading={loading} />
          </div>
        </div>
      </main>

      {/* Footer */}
      <footer className="bg-white border-t border-gray-200 mt-12">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div className="text-center text-sm text-gray-500">
            <p>&copy; 2024 Timetable Extractor. Built with React, TypeScript, and MySQL.</p>
          </div>
        </div>
      </footer>
    </div>
  );
}

export default App;