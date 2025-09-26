import React from 'react';
import { Clock, MapPin, User, BookOpen } from 'lucide-react';
import { TimetableEntry } from '../types/timetable';
import { formatTime } from '../utils/helpers';

interface TimetableCardProps {
  timetable: TimetableEntry;
}

const TimetableCard: React.FC<TimetableCardProps> = ({ timetable }) => {
  return (
    <div className="card p-4 hover:shadow-lg transition-shadow duration-200">
      <div className="flex items-start justify-between mb-3">
        <div className="flex items-center space-x-2">
          <BookOpen className="h-5 w-5 text-primary-600" />
          <h4 className="font-semibold text-gray-900">{timetable.subject}</h4>
        </div>
        <span className="text-sm font-medium text-primary-600 bg-primary-100 px-2 py-1 rounded-full">
          {timetable.day_of_week}
        </span>
      </div>

      <div className="space-y-2">
        <div className="flex items-center space-x-2 text-sm text-gray-600">
          <Clock className="h-4 w-4" />
          <span>{formatTime(timetable.start_time)} - {formatTime(timetable.end_time)}</span>
        </div>

        <div className="flex items-center space-x-2 text-sm text-gray-600">
          <User className="h-4 w-4" />
          <span>{timetable.teacher}</span>
        </div>

        <div className="flex items-center space-x-2 text-sm text-gray-600">
          <MapPin className="h-4 w-4" />
          <span>{timetable.room}</span>
        </div>
      </div>

      <div className="mt-3 pt-3 border-t border-gray-100">
        <div className="flex items-center justify-between text-xs text-gray-500">
          <span>{timetable.class_name}</span>
          <span>{timetable.level}</span>
          <span>{timetable.academic_year}</span>
        </div>
      </div>
    </div>
  );
};

export default TimetableCard;