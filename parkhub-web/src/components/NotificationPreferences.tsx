import { useState, useEffect } from 'react';
import { Bell, BellSlash, Moon, SpinnerGap } from '@phosphor-icons/react';
import { api, type NotificationPreferences as NotifPrefs } from '../api/client';
import { useTranslation } from 'react-i18next';
import toast from 'react-hot-toast';

function Toggle({ checked, onChange, label }: { checked: boolean; onChange: (v: boolean) => void; label: string }) {
  return (
    <label className="flex items-center justify-between py-2">
      <span className="text-sm text-gray-700 dark:text-gray-300">{label}</span>
      <button
        type="button"
        role="switch"
        aria-checked={checked}
        onClick={() => onChange(!checked)}
        className={`relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors ${checked ? 'bg-brand-600' : 'bg-gray-300 dark:bg-surface-600'}`}
      >
        <span className={`pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition-transform ${checked ? 'translate-x-5' : 'translate-x-0'}`} />
      </button>
    </label>
  );
}

export function NotificationPreferencesPanel() {
  const { t } = useTranslation();
  const [prefs, setPrefs] = useState<NotifPrefs | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    api.getNotificationPreferences().then(res => {
      if (res.success && res.data) setPrefs(res.data);
    }).finally(() => setLoading(false));
  }, []);

  async function handleChange(key: keyof NotifPrefs, value: any) {
    if (!prefs) return;
    const updated = { ...prefs, [key]: value };
    setPrefs(updated);
    setSaving(true);
    try {
      const res = await api.updateNotificationPreferences({ [key]: value });
      if (!res.success) {
        toast.error(res.error?.message || 'Failed');
        setPrefs(prefs); // revert
      }
    } finally { setSaving(false); }
  }

  if (loading) return <div className="flex justify-center p-8"><SpinnerGap size={24} className="animate-spin text-gray-400" /></div>;
  if (!prefs) return null;

  return (
    <div className="bg-white dark:bg-surface-900 rounded-xl border border-gray-200 dark:border-surface-700 p-6">
      <div className="flex items-center gap-3 mb-4">
        <Bell size={24} weight="duotone" className="text-brand-600" />
        <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
          {t('notifications.preferences', 'Notification Preferences')}
        </h3>
        {saving && <SpinnerGap size={16} className="animate-spin text-gray-400" />}
      </div>

      <div className="divide-y divide-gray-100 dark:divide-surface-700">
        <Toggle
          checked={prefs.email_booking_confirm}
          onChange={(v) => handleChange('email_booking_confirm', v)}
          label={t('notifications.emailBookingConfirm', 'Email: Booking confirmation')}
        />
        <Toggle
          checked={prefs.email_reminder}
          onChange={(v) => handleChange('email_reminder', v)}
          label={t('notifications.emailReminder', 'Email: Booking reminders')}
        />
        <Toggle
          checked={prefs.email_swap}
          onChange={(v) => handleChange('email_swap', v)}
          label={t('notifications.emailSwap', 'Email: Swap requests')}
        />
        <Toggle
          checked={prefs.push_enabled}
          onChange={(v) => handleChange('push_enabled', v)}
          label={t('notifications.pushEnabled', 'Push notifications')}
        />
      </div>

      <div className="mt-4 pt-4 border-t border-gray-100 dark:border-surface-700">
        <div className="flex items-center gap-2 mb-3">
          <Moon size={18} className="text-gray-500" />
          <span className="text-sm font-medium text-gray-700 dark:text-gray-300">
            {t('notifications.quietHours', 'Quiet Hours')}
          </span>
        </div>
        <div className="flex gap-3 items-center">
          <div className="flex-1">
            <label className="text-xs text-gray-500 mb-1 block">{t('common.from', 'From')}</label>
            <input
              type="time"
              value={prefs.quiet_hours_start || ''}
              onChange={(e) => handleChange('quiet_hours_start', e.target.value || null)}
              className="w-full px-3 py-2 border rounded-lg text-sm dark:bg-surface-800 dark:border-surface-600"
            />
          </div>
          <div className="flex-1">
            <label className="text-xs text-gray-500 mb-1 block">{t('common.to', 'To')}</label>
            <input
              type="time"
              value={prefs.quiet_hours_end || ''}
              onChange={(e) => handleChange('quiet_hours_end', e.target.value || null)}
              className="w-full px-3 py-2 border rounded-lg text-sm dark:bg-surface-800 dark:border-surface-600"
            />
          </div>
          {(prefs.quiet_hours_start || prefs.quiet_hours_end) && (
            <button
              onClick={() => { handleChange('quiet_hours_start', null); handleChange('quiet_hours_end', null); }}
              className="text-xs text-red-500 hover:text-red-700 mt-4"
            >
              {t('common.clear', 'Clear')}
            </button>
          )}
        </div>
      </div>
    </div>
  );
}
