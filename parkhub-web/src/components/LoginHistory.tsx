import { useState, useEffect } from 'react';
import { ClockCounterClockwise, Globe, Desktop, SpinnerGap, X } from '@phosphor-icons/react';
import { api, type LoginHistoryEntry, type Session } from '../api/client';
import { useTranslation } from 'react-i18next';
import toast from 'react-hot-toast';

function parseUserAgent(ua: string): string {
  if (!ua) return 'Unknown';
  if (ua.includes('Mobile')) return 'Mobile';
  if (ua.includes('Chrome')) return 'Chrome';
  if (ua.includes('Firefox')) return 'Firefox';
  if (ua.includes('Safari')) return 'Safari';
  if (ua.includes('Edge')) return 'Edge';
  return ua.slice(0, 40);
}

function timeAgo(dateStr: string): string {
  const diff = Date.now() - new Date(dateStr).getTime();
  const mins = Math.floor(diff / 60000);
  if (mins < 1) return 'just now';
  if (mins < 60) return `${mins}m ago`;
  const hours = Math.floor(mins / 60);
  if (hours < 24) return `${hours}h ago`;
  const days = Math.floor(hours / 24);
  return `${days}d ago`;
}

export function LoginHistoryPanel() {
  const { t } = useTranslation();
  const [history, setHistory] = useState<LoginHistoryEntry[]>([]);
  const [sessions, setSessions] = useState<Session[]>([]);
  const [loading, setLoading] = useState(true);
  const [tab, setTab] = useState<'history' | 'sessions'>('history');

  useEffect(() => {
    Promise.all([
      api.getLoginHistory(),
      api.getSessions(),
    ]).then(([histRes, sessRes]) => {
      if (histRes.success && histRes.data) setHistory(histRes.data);
      if (sessRes.success && sessRes.data) setSessions(sessRes.data);
    }).finally(() => setLoading(false));
  }, []);

  async function revokeSession(id: string) {
    const res = await api.revokeSession(id);
    if (res.success) {
      setSessions(s => s.filter(x => x.id !== id));
      toast.success(t('security.sessionRevoked', 'Session revoked'));
    } else {
      toast.error(res.error?.message || 'Failed');
    }
  }

  async function revokeAll() {
    const res = await api.revokeAllSessions();
    if (res.success) {
      setSessions(s => s.filter(x => x.is_current));
      toast.success(t('security.allSessionsRevoked', 'All other sessions revoked'));
    } else {
      toast.error(res.error?.message || 'Failed');
    }
  }

  if (loading) return <div className="flex justify-center p-8"><SpinnerGap size={24} className="animate-spin text-gray-400" /></div>;

  return (
    <div className="bg-white dark:bg-surface-900 rounded-xl border border-gray-200 dark:border-surface-700 p-6">
      <div className="flex items-center gap-3 mb-4">
        <ClockCounterClockwise size={24} weight="duotone" className="text-brand-600" />
        <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
          {t('security.securityActivity', 'Security & Activity')}
        </h3>
      </div>

      {/* Tabs */}
      <div className="flex gap-1 mb-4 bg-gray-100 dark:bg-surface-800 rounded-lg p-1">
        <button
          onClick={() => setTab('history')}
          className={`flex-1 px-3 py-1.5 text-sm rounded-md transition ${tab === 'history' ? 'bg-white dark:bg-surface-700 shadow text-gray-900 dark:text-white' : 'text-gray-500'}`}
        >
          {t('security.loginHistory', 'Login History')}
        </button>
        <button
          onClick={() => setTab('sessions')}
          className={`flex-1 px-3 py-1.5 text-sm rounded-md transition ${tab === 'sessions' ? 'bg-white dark:bg-surface-700 shadow text-gray-900 dark:text-white' : 'text-gray-500'}`}
        >
          {t('security.activeSessions', 'Active Sessions')} ({sessions.length})
        </button>
      </div>

      {tab === 'history' && (
        <div className="space-y-2 max-h-80 overflow-y-auto">
          {history.length === 0 && (
            <p className="text-sm text-gray-500 text-center py-4">{t('common.noData', 'No data')}</p>
          )}
          {history.map(entry => (
            <div key={entry.id} className="flex items-center gap-3 py-2 border-b border-gray-50 dark:border-surface-700 last:border-0">
              <Globe size={16} className="text-gray-400 shrink-0" />
              <div className="flex-1 min-w-0">
                <div className="text-sm text-gray-700 dark:text-gray-300">{entry.ip_address}</div>
                <div className="text-xs text-gray-500 truncate">{parseUserAgent(entry.user_agent)}</div>
              </div>
              <span className="text-xs text-gray-400 whitespace-nowrap">{timeAgo(entry.logged_in_at)}</span>
            </div>
          ))}
        </div>
      )}

      {tab === 'sessions' && (
        <div>
          {sessions.length > 1 && (
            <button
              onClick={revokeAll}
              className="mb-3 text-xs text-red-600 hover:text-red-700 font-medium"
            >
              {t('security.revokeAll', 'Revoke all other sessions')}
            </button>
          )}
          <div className="space-y-2 max-h-80 overflow-y-auto">
            {sessions.map(session => (
              <div key={session.id} className="flex items-center gap-3 py-2 border-b border-gray-50 dark:border-surface-700 last:border-0">
                <Desktop size={16} className={session.is_current ? 'text-green-500' : 'text-gray-400'} />
                <div className="flex-1 min-w-0">
                  <div className="text-sm text-gray-700 dark:text-gray-300">
                    {session.name}
                    {session.is_current && (
                      <span className="ml-2 px-1.5 py-0.5 text-[10px] font-medium bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 rounded">
                        {t('security.current', 'Current')}
                      </span>
                    )}
                  </div>
                  <div className="text-xs text-gray-500">
                    {session.last_used_at ? timeAgo(session.last_used_at) : t('common.never', 'Never used')}
                  </div>
                </div>
                {!session.is_current && (
                  <button
                    onClick={() => revokeSession(session.id)}
                    className="p-1 text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/20 rounded"
                    title={t('security.revoke', 'Revoke')}
                  >
                    <X size={14} />
                  </button>
                )}
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
