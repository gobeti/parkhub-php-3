import { useTranslation } from 'react-i18next';

export const CALENDAR_COLORS = {
  // Booking statuses
  confirmed: { bg: 'bg-green-100 dark:bg-green-900/30', text: 'text-green-700 dark:text-green-400', dot: 'bg-green-500', label: 'Confirmed' },
  active: { bg: 'bg-blue-100 dark:bg-blue-900/30', text: 'text-blue-700 dark:text-blue-400', dot: 'bg-blue-500', label: 'Active' },
  pending: { bg: 'bg-yellow-100 dark:bg-yellow-900/30', text: 'text-yellow-700 dark:text-yellow-400', dot: 'bg-yellow-500', label: 'Pending' },
  cancelled: { bg: 'bg-red-100 dark:bg-red-900/30', text: 'text-red-700 dark:text-red-400', dot: 'bg-red-500', label: 'Cancelled' },
  completed: { bg: 'bg-gray-100 dark:bg-gray-800/50', text: 'text-gray-600 dark:text-gray-400', dot: 'bg-gray-400', label: 'Completed' },

  // Absence types
  vacation: { bg: 'bg-sky-100 dark:bg-sky-900/30', text: 'text-sky-700 dark:text-sky-400', dot: 'bg-sky-500', label: 'Vacation' },
  sick: { bg: 'bg-orange-100 dark:bg-orange-900/30', text: 'text-orange-700 dark:text-orange-400', dot: 'bg-orange-500', label: 'Sick' },
  homeoffice: { bg: 'bg-purple-100 dark:bg-purple-900/30', text: 'text-purple-700 dark:text-purple-400', dot: 'bg-purple-500', label: 'Home Office' },
  other: { bg: 'bg-teal-100 dark:bg-teal-900/30', text: 'text-teal-700 dark:text-teal-400', dot: 'bg-teal-500', label: 'Other' },
} as const;

export type CalendarColorKey = keyof typeof CALENDAR_COLORS;

export function getCalendarColor(status: string, type?: string): (typeof CALENDAR_COLORS)[CalendarColorKey] {
  if (type === 'absence') {
    const absenceKey = status as CalendarColorKey;
    return CALENDAR_COLORS[absenceKey] || CALENDAR_COLORS.other;
  }
  const bookingKey = status as CalendarColorKey;
  return CALENDAR_COLORS[bookingKey] || CALENDAR_COLORS.completed;
}

interface Props {
  showBookings?: boolean;
  showAbsences?: boolean;
}

export function CalendarLegend({ showBookings = true, showAbsences = true }: Props) {
  const { t } = useTranslation();

  const bookingItems: CalendarColorKey[] = ['confirmed', 'active', 'pending', 'cancelled', 'completed'];
  const absenceItems: CalendarColorKey[] = ['vacation', 'sick', 'homeoffice', 'other'];

  return (
    <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs">
      {showBookings && bookingItems.map(key => {
        const color = CALENDAR_COLORS[key];
        return (
          <div key={key} className="flex items-center gap-1.5">
            <span className={`w-2.5 h-2.5 rounded-full ${color.dot}`} />
            <span className="text-gray-600 dark:text-gray-400">
              {t(`calendar.${key}`, color.label)}
            </span>
          </div>
        );
      })}
      {showAbsences && absenceItems.map(key => {
        const color = CALENDAR_COLORS[key];
        return (
          <div key={key} className="flex items-center gap-1.5">
            <span className={`w-2.5 h-2.5 rounded-full ${color.dot}`} />
            <span className="text-gray-600 dark:text-gray-400">
              {t(`calendar.${key}`, color.label)}
            </span>
          </div>
        );
      })}
    </div>
  );
}
