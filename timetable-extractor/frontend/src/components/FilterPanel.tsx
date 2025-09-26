import React, { useState, useEffect } from 'react';
import { Filter, X, Search } from 'lucide-react';
import { TimetableFilters, FilterOptions } from '../types/timetable';
import { timetableService } from '../services/api';

interface FilterPanelProps {
  onFiltersChange: (filters: TimetableFilters) => void;
  currentFilters: TimetableFilters;
}

const FilterPanel: React.FC<FilterPanelProps> = ({ onFiltersChange, currentFilters }) => {
  const [filterOptions, setFilterOptions] = useState<FilterOptions>({
    classes: [],
    levels: [],
    academic_years: [],
    days: []
  });
  const [loading, setLoading] = useState(true);
  const [isExpanded, setIsExpanded] = useState(false);

  useEffect(() => {
    loadFilterOptions();
  }, []);

  const loadFilterOptions = async () => {
    try {
      setLoading(true);
      const response = await timetableService.getFilterOptions();
      if (response.success) {
        setFilterOptions(response.data);
      }
    } catch (error) {
      console.error('Failed to load filter options:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleFilterChange = (key: keyof TimetableFilters, value: string) => {
    const newFilters = { ...currentFilters };
    if (value === '') {
      delete newFilters[key];
    } else {
      newFilters[key] = value;
    }
    onFiltersChange(newFilters);
  };

  const clearAllFilters = () => {
    onFiltersChange({});
  };

  const hasActiveFilters = Object.values(currentFilters).some(value => value && value !== '');

  if (loading) {
    return (
      <div className="card p-6">
        <div className="animate-pulse">
          <div className="h-4 bg-gray-200 rounded w-1/4 mb-4"></div>
          <div className="space-y-3">
            <div className="h-10 bg-gray-200 rounded"></div>
            <div className="h-10 bg-gray-200 rounded"></div>
            <div className="h-10 bg-gray-200 rounded"></div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="card p-6">
      <div className="flex items-center justify-between mb-4">
        <div className="flex items-center space-x-2">
          <Filter className="h-5 w-5 text-primary-600" />
          <h3 className="text-lg font-semibold text-gray-900">Filters</h3>
        </div>
        <div className="flex items-center space-x-2">
          {hasActiveFilters && (
            <button
              onClick={clearAllFilters}
              className="text-sm text-gray-500 hover:text-gray-700 flex items-center space-x-1"
            >
              <X className="h-4 w-4" />
              <span>Clear All</span>
            </button>
          )}
          <button
            onClick={() => setIsExpanded(!isExpanded)}
            className="text-sm text-primary-600 hover:text-primary-700"
          >
            {isExpanded ? 'Collapse' : 'Expand'}
          </button>
        </div>
      </div>

      <div className={`space-y-4 ${isExpanded ? 'block' : 'hidden md:block'}`}>
        {/* Class Filter */}
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Class
          </label>
          <select
            value={currentFilters.class_name || ''}
            onChange={(e) => handleFilterChange('class_name', e.target.value)}
            className="input-field"
          >
            <option value="">All Classes</option>
            {filterOptions.classes.map((className) => (
              <option key={className} value={className}>
                {className}
              </option>
            ))}
          </select>
        </div>

        {/* Level Filter */}
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Level
          </label>
          <select
            value={currentFilters.level || ''}
            onChange={(e) => handleFilterChange('level', e.target.value)}
            className="input-field"
          >
            <option value="">All Levels</option>
            {filterOptions.levels.map((level) => (
              <option key={level} value={level}>
                {level}
              </option>
            ))}
          </select>
        </div>

        {/* Academic Year Filter */}
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Academic Year
          </label>
          <select
            value={currentFilters.academic_year || ''}
            onChange={(e) => handleFilterChange('academic_year', e.target.value)}
            className="input-field"
          >
            <option value="">All Years</option>
            {filterOptions.academic_years.map((year) => (
              <option key={year} value={year}>
                {year}
              </option>
            ))}
          </select>
        </div>

        {/* Day of Week Filter */}
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Day of Week
          </label>
          <select
            value={currentFilters.day_of_week || ''}
            onChange={(e) => handleFilterChange('day_of_week', e.target.value)}
            className="input-field"
          >
            <option value="">All Days</option>
            {filterOptions.days.map((day) => (
              <option key={day} value={day}>
                {day}
              </option>
            ))}
          </select>
        </div>
      </div>

      {hasActiveFilters && (
        <div className="mt-4 pt-4 border-t border-gray-200">
          <div className="flex flex-wrap gap-2">
            {Object.entries(currentFilters).map(([key, value]) => {
              if (!value) return null;
              return (
                <span
                  key={key}
                  className="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-primary-100 text-primary-800"
                >
                  {key.replace('_', ' ')}: {value}
                  <button
                    onClick={() => handleFilterChange(key as keyof TimetableFilters, '')}
                    className="ml-2 hover:text-primary-600"
                  >
                    <X className="h-3 w-3" />
                  </button>
                </span>
              );
            })}
          </div>
        </div>
      )}
    </div>
  );
};

export default FilterPanel;