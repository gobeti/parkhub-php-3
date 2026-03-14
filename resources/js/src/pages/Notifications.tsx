import { useEffect, useState, useMemo } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import {
  Bell, Warning, Info, CheckCircle, Funnel, Check, SpinnerGap,
  ArrowClockwise, Eye, EyeSlash,
} from '@phosphor-icons/react';
import { api, ApiNotification } from '../api/client';
import { useTranslation } from 'react-i18next';
import toast from 'react-hot-toast';
import { formatDistanceToNow, isToday, isYesterday } from 'date-fns';
import { de, enUS } from 'date-fns/locale';

const notifIcon = { warning: Warning, info: Info, success: CheckCircle };
const notifColor = {
  warning: 'text-amber-500',
  info: 'text-primary-500',
  success: 'text-emerald-500',
};
const notifBg = {
  warning: 'bg-amber-100 dark:bg-amber-900/30',
  info: 'bg-primary-100 dark:bg-primary-900/30',
  success: 'bg-emerald-100 dark:bg-emerald-900/30',
};

type FilterType = 'all' | 'warning' | 'info' | 'success';
type FilterRead = 'all' | 'unread' | 'read';

function resolveType(raw: string): keyof typeof notifIcon {
  if (raw === 'warning') return 'warning';
  if (raw === 'success') return 'success';
  return 'info';
}

function groupByDate(
  notifications: ApiNotification[],
  t: ReturnType<typeof useTranslation>['t'],
): { label: string; items: ApiNotification[] }[] {
  const groups: Record<string, ApiNotification[]> = {};
  const order: string[] = [];

  for (const n of notifications) {
    const d = new Date(n.created_at);
    let label: string;
    if (isToday(d)) label = t('notifications.today', 'Heute');
    else if (isYesterday(d)) label = t('notifications.yesterday', 'Gestern');
    else label = t('notifications.older', 'Älter');

    if (!groups[label]) {
      groups[label] = [];
      order.push(label);
    }
    groups[label].push(n);
  }

  return order.map(label => ({ label, items: groups[label] }));
}

export function NotificationsPage() {
  const { t, i18n } = useTranslation();
  const dateFnsLocale = i18n.language?.startsWith('en') ? enUS : de;
  const [notifications, setNotifications] = useState<ApiNotification[]>([]);
  const [loading, setLoading] = useState(true);
  const [markingAll, setMarkingAll] = useState(false);
  const [filterType, setFilterType] = useState<FilterType>('all');
  const [filterRead, setFilterRead] = useState<FilterRead>('all');

  useEffect(() => { loadNotifications(); }, []);

  async function loadNotifications() {
    setLoading(true);
    try {
      const res = await api.getNotifications();
      if (res.success && res.data) {
        setNotifications(res.data);
      }
    } catch { /* ignore */ }
    finally { setLoading(false); }
  }

  async function markAsRead(id: string) {
    setNotifications(prev => prev.map(n => n.id === id ? { ...n, read: true } : n));
    try {
      await api.markNotificationRead(id);
    } catch {
      // revert on error
      setNotifications(prev => prev.map(n => n.id === id ? { ...n, read: false } : n));
    }
  }

  async function markAllAsRead() {
    setMarkingAll(true);
    try {
      const res = await api.markAllNotificationsRead();
      if (res.success) {
        setNotifications(prev => prev.map(n => ({ ...n, read: true })));
        toast.success(t('notifications.allMarkedRead', 'Alle als gelesen markiert'));
      } else {
        toast.error(t('notifications.markAllFailed', 'Fehler beim Markieren'));
      }
    } catch {
      toast.error(t('notifications.markAllFailed', 'Fehler beim Markieren'));
    } finally {
      setMarkingAll(false);
    }
  }

  const filtered = useMemo(() => {
    return notifications.filter(n => {
      if (filterType !== 'all' && resolveType(n.notification_type) !== filterType) return false;
      if (filterRead === 'unread' && n.read) return false;
      if (filterRead === 'read' && !n.read) return false;
      return true;
    });
  }, [notifications, filterType, filterRead]);

  const grouped = useMemo(() => groupByDate(filtered, t), [filtered, t]);
  const unreadCount = notifications.filter(n => !n.read).length;

  if (loading) {
    return (
      <div className="space-y-6" role="status" aria-busy="true">
        <div className="h-8 w-64 skeleton" />
        {[1, 2, 3, 4, 5].map(i => (
          <div key={i} className="h-20 skeleton rounded-2xl" />
        ))}
      </div>
    );
  }

  return (
    <div className="space-y-8">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
            {t('notifications.title', 'Benachrichtigungen')}
          </h1>
          <p className="text-gray-500 dark:text-gray-400 mt-1">
            {unreadCount > 0
              ? t('notifications.unreadCount', '{{count}} ungelesene Benachrichtigungen', { count: unreadCount })
              : t('notifications.allRead', 'Alle Benachrichtigungen gelesen')}
          </p>
        </div>
        <div className="flex items-center gap-2">
          <button onClick={loadNotifications} className="btn btn-secondary">
            <ArrowClockwise weight="bold" className="w-4 h-4" />
            {t('common.refresh', 'Aktualisieren')}
          </button>
          {unreadCount > 0 && (
            <button
              onClick={markAllAsRead}
              disabled={markingAll}
              className="btn btn-primary"
            >
              {markingAll ? (
                <SpinnerGap weight="bold" className="w-4 h-4 animate-spin" />
              ) : (
                <Check weight="bold" className="w-4 h-4" />
              )}
              {t('notifications.markAllRead', 'Alle als gelesen markieren')}
            </button>
          )}
        </div>
      </div>

      {/* Filters */}
      <div className="card p-4">
        <div className="flex items-center gap-2 mb-3">
          <Funnel weight="bold" className="w-4 h-4 text-gray-500" />
          <span className="text-sm font-medium text-gray-700 dark:text-gray-300">
            {t('common.filter', 'Filter')}
          </span>
          <span className="ml-auto text-xs text-gray-400">
            {t('notifications.filteredCount', '{{count}} Benachrichtigungen', { count: filtered.length })}
          </span>
        </div>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <select
            value={filterType}
            onChange={(e) => setFilterType(e.target.value as FilterType)}
            className="input"
          >
            <option value="all">{t('notifications.filterTypeAll', 'Alle Typen')}</option>
            <option value="info">{t('notifications.filterTypeInfo', 'Information')}</option>
            <option value="warning">{t('notifications.filterTypeWarning', 'Warnung')}</option>
            <option value="success">{t('notifications.filterTypeSuccess', 'Erfolg')}</option>
          </select>
          <select
            value={filterRead}
            onChange={(e) => setFilterRead(e.target.value as FilterRead)}
            className="input"
          >
            <option value="all">{t('notifications.filterReadAll', 'Alle')}</option>
            <option value="unread">{t('notifications.filterUnread', 'Ungelesen')}</option>
            <option value="read">{t('notifications.filterRead', 'Gelesen')}</option>
          </select>
        </div>
      </div>

      {/* Notification List */}
      {filtered.length === 0 ? (
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          className="card p-12 text-center"
        >
          <Bell weight="light" className="w-20 h-20 text-gray-200 dark:text-gray-700 mx-auto mb-4" />
          <p className="text-gray-500 dark:text-gray-400">
            {t('notifications.empty', 'Keine Benachrichtigungen')}
          </p>
        </motion.div>
      ) : (
        <div className="space-y-6">
          {grouped.map(group => (
            <section key={group.label}>
              <h2 className="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3 px-1">
                {group.label}
              </h2>
              <div className="space-y-2">
                <AnimatePresence>
                  {group.items.map(n => {
                    const nType = resolveType(n.notification_type);
                    const NIcon = notifIcon[nType];
                    return (
                      <motion.button
                        key={n.id}
                        initial={{ opacity: 0, y: 10 }}
                        animate={{ opacity: 1, y: 0 }}
                        exit={{ opacity: 0, x: -50 }}
                        onClick={() => { if (!n.read) markAsRead(n.id); }}
                        className={`w-full text-left card p-4 flex items-start gap-4 transition-all hover:shadow-md hover:-translate-y-0.5 ${
                          n.read
                            ? 'opacity-60'
                            : 'ring-1 ring-primary-200 dark:ring-primary-800 bg-primary-50/30 dark:bg-primary-900/10'
                        }`}
                      >
                        {/* Icon */}
                        <div className={`w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 ${notifBg[nType]}`}>
                          <NIcon weight="fill" className={`w-5 h-5 ${notifColor[nType]}`} />
                        </div>

                        {/* Content */}
                        <div className="flex-1 min-w-0">
                          <div className="flex items-start justify-between gap-2">
                            <p className={`text-sm font-semibold ${
                              n.read
                                ? 'text-gray-500 dark:text-gray-400'
                                : 'text-gray-900 dark:text-white'
                            }`}>
                              {n.title}
                            </p>
                            <div className="flex items-center gap-2 flex-shrink-0">
                              <span className="text-xs text-gray-400 dark:text-gray-500 whitespace-nowrap">
                                {formatDistanceToNow(new Date(n.created_at), {
                                  addSuffix: true,
                                  locale: dateFnsLocale,
                                })}
                              </span>
                              {!n.read && (
                                <span className="w-2.5 h-2.5 bg-primary-500 rounded-full flex-shrink-0" />
                              )}
                            </div>
                          </div>
                          <p className={`text-sm mt-0.5 ${
                            n.read
                              ? 'text-gray-400 dark:text-gray-500'
                              : 'text-gray-600 dark:text-gray-300'
                          }`}>
                            {n.message}
                          </p>
                        </div>

                        {/* Read state indicator */}
                        <div className="flex-shrink-0 mt-1">
                          {n.read ? (
                            <Eye weight="regular" className="w-4 h-4 text-gray-300 dark:text-gray-600" />
                          ) : (
                            <EyeSlash weight="regular" className="w-4 h-4 text-primary-400" />
                          )}
                        </div>
                      </motion.button>
                    );
                  })}
                </AnimatePresence>
              </div>
            </section>
          ))}
        </div>
      )}
    </div>
  );
}
