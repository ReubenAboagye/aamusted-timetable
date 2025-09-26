import React from 'react';
import { Calendar, Clock, Users, BookOpen } from 'lucide-react';
import { TimetableEntry } from '../types/timetable';
import { groupTimetablesByDay, formatTime, getDayAbbreviation, generateTimetableSummary } from '../utils/helpers';
import TimetableCard from './TimetableCard';

interface TimetableGridProps {
  timetables: TimetableEntry[];
  loading: boolean;
}

const TimetableGrid: React.FC<TimetableGridProps> = ({ timetables, loading }) => {
  const groupedTimetables = groupTimetablesByDay(timetables);
  const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

  if (loading) {
    return (
      <div className="space-y-6">
        <div className="animate-pulse">
          <div className="h-6 bg-gray-200 rounded w-1/3 mb-4"></div>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            {[...Array(8)].map((_, i) => (
              <div key={i} className="card p-4">
                <div className="h-4 bg-gray-200 rounded w-3/4 mb-3"></div>
                <div className="space-y-2">
                  <div className="h-3 bg-gray-200 rounded w-1/2"></div>
                  <div className="h-3 bg-gray-200 rounded w-2/3"></div>
                  <div className="h-3 bg-gray-200 rounded w-1/3"></div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    );
  }

  if (timetables.length === 0) {
    return (
      <div className="text-center py-12">
        <Calendar className="h-16 w-16 text-gray-300 mx-auto mb-4" />
        <h3 className="text-lg font-medium text-gray-900 mb-2">No timetables found</h3>
        <p className="text-gray-500">Try adjusting your filters to see more results.</p>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Summary */}
      <div className="card p-4 bg-primary-50 border-primary-200">
        <div className="flex items-center justify-between">
          <div className="flex items-center space-x-4">
            <div className="flex items-center space-x-2">
              <BookOpen className="h-5 w-5 text-primary-600" />
              <span className="text-sm font-medium text-primary-800">
                {generateTimetableSummary(timetables)}
              </span>
            </div>
          </div>
          <div className="text-sm text-primary-600">
            {timetables.length} total classes
          </div>
        </div>
      </div>

      {/* Timetable Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
        {days.map((day) => {
          const dayTimetables = groupedTimetables[day];
          if (dayTimetables.length === 0) return null;

          return (
            <div key={day} className="space-y-4">
              <div className="flex items-center space-x-3">
                <div className="flex items-center justify-center w-10 h-10 bg-primary-100 rounded-lg">
                  <span className="text-sm font-bold text-primary-600">
                    {getDayAbbreviation(day)}
                  </span>
                </div>
                <div>
                  <h3 className="font-semibold text-gray-900">{day}</h3>
                  <p className="text-sm text-gray-500">
                    {dayTimetables.length} class{dayTimetables.length !== 1 ? 'es' : ''}
                  </p>
                </div>
              </div>

              <div className="space-y-3">
                {dayTimetables.map((timetable) => (
                  <TimetableCard key={timetable.id} timetable={timetable} />
                ))}
              </div>
            </div>
          );
        })}
      </div>

      {/* Alternative: List View for Mobile */}
      <div className="lg:hidden">
        <div className="space-y-4">
          {timetables.map((timetable) => (
            <TimetableCard key={timetable.id} timetable={timetable} />
          ))}
        </div>
      </div>
    </div>
  );
};

export default TimetableGrid;