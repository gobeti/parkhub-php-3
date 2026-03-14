import { useState, useEffect } from 'react';
import { motion } from 'framer-motion';
import { SlidersHorizontal, SpinnerGap, Check, Buildings, CalendarCheck, Timer, CurrencyCircleDollar, Car } from '@phosphor-icons/react';
import { api } from '../api/client';
import { useTranslation } from 'react-i18next';
import toast from 'react-hot-toast';

interface AppSettings {
  // General
  company_name: string;
  use_case: 'corporate' | 'public' | 'mixed';
  self_registration: boolean;
  // Booking Rules
  max_bookings_per_day: number;
  max_booking_days_ahead: number;
  allow_guest_bookings: boolean;
  require_vehicle: boolean;
  // Auto-Release
  auto_release_enabled: boolean;
  auto_release_timeout_minutes: number;
  // Credits
  credits_enabled: boolean;
  credits_per_booking: number;
  // License Plate
  license_plate_mode: 'visible' | 'hidden' | 'optional';
}

const defaultSettings: AppSettings = {
  company_name: 'ParkHub',
  use_case: 'corporate',
  self_registration: false,
  max_bookings_per_day: 1,
  max_booking_days_ahead: 14,
  allow_guest_bookings: false,
  require_vehicle: false,
  auto_release_enabled: false,
  auto_release_timeout_minutes: 15,
  credits_enabled: false,
  credits_per_booking: 1,
  license_plate_mode: 'optional',
};

const API_BASE = import.meta.env.VITE_API_URL || '';

export function AdminSettingsPage() {
  const { t } = useTranslation();
  const [settings, setSettings] = useState<AppSettings>(defaultSettings);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    loadSettings();
  }, []);

  async function loadSettings() {
    try {
      const token = api.getToken();
      const response = await fetch(`${API_BASE}/api/v1/admin/settings`, {
        headers: {
          'Content-Type': 'application/json',
          ...(token ? { 'Authorization': `Bearer ${token}` } : {}),
        },
      });
      const res = await response.json();
      if (res.success && res.data) {
        setSettings(prev => ({ ...prev, ...res.data }));
      }
    } catch {
      toast.error(t('admin.settings.loadError', 'Einstellungen konnten nicht geladen werden'));
    } finally {
      setLoading(false);
    }
  }

  async function handleSave() {
    setSaving(true);
    try {
      const token = api.getToken();
      const response = await fetch(`${API_BASE}/api/v1/admin/settings`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          ...(token ? { 'Authorization': `Bearer ${token}` } : {}),
        },
        body: JSON.stringify(settings),
      });
      const res = await response.json();
      if (res.success) {
        toast.success(t('admin.settings.saved', 'Einstellungen gespeichert!'));
      } else {
        toast.error(res.error?.message || t('admin.settings.saveError', 'Speichern fehlgeschlagen'));
      }
    } catch {
      toast.error(t('admin.settings.saveError', 'Speichern fehlgeschlagen'));
    } finally {
      setSaving(false);
    }
  }

  function update<K extends keyof AppSettings>(key: K, value: AppSettings[K]) {
    setSettings(prev => ({ ...prev, [key]: value }));
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <SpinnerGap weight="bold" className="w-8 h-8 text-primary-600 animate-spin" />
      </div>
    );
  }

  return (
    <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="space-y-8">
      <div className="flex items-center gap-3">
        <SlidersHorizontal weight="fill" className="w-6 h-6 text-primary-600" />
        <h2 className="text-xl font-semibold text-gray-900 dark:text-white">
          {t('admin.settings.title', 'Systemeinstellungen')}
        </h2>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <div className="space-y-6">
          {/* General */}
          <div className="card p-6 space-y-4">
            <h3 className="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
              <Buildings weight="fill" className="w-5 h-5 text-primary-600" />
              {t('admin.settings.general', 'Allgemein')}
            </h3>

            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                {t('admin.settings.companyName', 'Firmenname')}
              </label>
              <input
                type="text"
                value={settings.company_name}
                onChange={(e) => update('company_name', e.target.value)}
                className="input"
                placeholder="ParkHub"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                {t('admin.settings.useCase', 'Einsatzbereich')}
              </label>
              <select
                value={settings.use_case}
                onChange={(e) => update('use_case', e.target.value as AppSettings['use_case'])}
                className="input"
              >
                <option value="corporate">{t('admin.settings.useCaseCorporate', 'Unternehmen')}</option>
                <option value="public">{t('admin.settings.useCasePublic', 'Öffentlich')}</option>
                <option value="mixed">{t('admin.settings.useCaseMixed', 'Gemischt')}</option>
              </select>
            </div>

            <ToggleRow
              label={t('admin.settings.selfRegistration', 'Selbstregistrierung')}
              description={t('admin.settings.selfRegistrationDesc', 'Nutzer können sich selbst registrieren')}
              checked={settings.self_registration}
              onChange={(v) => update('self_registration', v)}
            />
          </div>

          {/* Booking Rules */}
          <div className="card p-6 space-y-4">
            <h3 className="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
              <CalendarCheck weight="fill" className="w-5 h-5 text-primary-600" />
              {t('admin.settings.bookingRules', 'Buchungsregeln')}
            </h3>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  {t('admin.settings.maxBookingsPerDay', 'Max. Buchungen / Tag')}
                </label>
                <input
                  type="number"
                  min={1}
                  max={10}
                  value={settings.max_bookings_per_day}
                  onChange={(e) => update('max_bookings_per_day', Math.max(1, parseInt(e.target.value) || 1))}
                  className="input"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  {t('admin.settings.maxBookingDaysAhead', 'Max. Tage im Voraus')}
                </label>
                <input
                  type="number"
                  min={1}
                  max={365}
                  value={settings.max_booking_days_ahead}
                  onChange={(e) => update('max_booking_days_ahead', Math.max(1, parseInt(e.target.value) || 1))}
                  className="input"
                />
              </div>
            </div>

            <ToggleRow
              label={t('admin.settings.allowGuestBookings', 'Gastbuchungen erlauben')}
              description={t('admin.settings.allowGuestBookingsDesc', 'Nicht registrierte Nutzer können buchen')}
              checked={settings.allow_guest_bookings}
              onChange={(v) => update('allow_guest_bookings', v)}
            />

            <ToggleRow
              label={t('admin.settings.requireVehicle', 'Fahrzeug erforderlich')}
              description={t('admin.settings.requireVehicleDesc', 'Bei Buchung muss ein Fahrzeug angegeben werden')}
              checked={settings.require_vehicle}
              onChange={(v) => update('require_vehicle', v)}
            />
          </div>

          {/* Save Button */}
          <button
            onClick={handleSave}
            disabled={saving}
            className="btn btn-primary w-full"
          >
            {saving ? <SpinnerGap weight="bold" className="w-4 h-4 animate-spin" /> : <Check weight="bold" className="w-4 h-4" />}
            {t('admin.settings.save', 'Einstellungen speichern')}
          </button>
        </div>

        <div className="space-y-6">
          {/* Auto-Release */}
          <div className="card p-6 space-y-4">
            <h3 className="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
              <Timer weight="fill" className="w-5 h-5 text-primary-600" />
              {t('admin.settings.autoRelease', 'Auto-Freigabe')}
            </h3>

            <ToggleRow
              label={t('admin.settings.autoReleaseEnabled', 'Auto-Freigabe aktivieren')}
              description={t('admin.settings.autoReleaseEnabledDesc', 'Nicht genutzte Buchungen werden automatisch freigegeben')}
              checked={settings.auto_release_enabled}
              onChange={(v) => update('auto_release_enabled', v)}
            />

            {settings.auto_release_enabled && (
              <motion.div initial={{ opacity: 0, height: 0 }} animate={{ opacity: 1, height: 'auto' }} exit={{ opacity: 0, height: 0 }}>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  {t('admin.settings.autoReleaseTimeout', 'Timeout (Minuten)')}
                </label>
                <input
                  type="number"
                  min={5}
                  max={120}
                  value={settings.auto_release_timeout_minutes}
                  onChange={(e) => update('auto_release_timeout_minutes', Math.max(5, parseInt(e.target.value) || 15))}
                  className="input"
                />
                <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                  {t('admin.settings.autoReleaseTimeoutHint', 'Buchung wird nach dieser Zeit ohne Check-in freigegeben')}
                </p>
              </motion.div>
            )}
          </div>

          {/* Credits System */}
          <div className="card p-6 space-y-4">
            <h3 className="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
              <CurrencyCircleDollar weight="fill" className="w-5 h-5 text-primary-600" />
              {t('admin.settings.credits', 'Kontingent-System')}
            </h3>

            <ToggleRow
              label={t('admin.settings.creditsEnabled', 'Kontingent aktivieren')}
              description={t('admin.settings.creditsEnabledDesc', 'Nutzer benötigen Kontingent zum Buchen')}
              checked={settings.credits_enabled}
              onChange={(v) => update('credits_enabled', v)}
            />

            {settings.credits_enabled && (
              <motion.div initial={{ opacity: 0, height: 0 }} animate={{ opacity: 1, height: 'auto' }} exit={{ opacity: 0, height: 0 }}>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  {t('admin.settings.creditsPerBooking', 'Kontingent pro Buchung')}
                </label>
                <input
                  type="number"
                  min={1}
                  max={100}
                  value={settings.credits_per_booking}
                  onChange={(e) => update('credits_per_booking', Math.max(1, parseInt(e.target.value) || 1))}
                  className="input"
                />
              </motion.div>
            )}
          </div>

          {/* License Plate Mode */}
          <div className="card p-6 space-y-4">
            <h3 className="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
              <Car weight="fill" className="w-5 h-5 text-primary-600" />
              {t('admin.settings.licensePlate', 'Kennzeichen')}
            </h3>

            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                {t('admin.settings.licensePlateMode', 'Kennzeichen-Modus')}
              </label>
              <select
                value={settings.license_plate_mode}
                onChange={(e) => update('license_plate_mode', e.target.value as AppSettings['license_plate_mode'])}
                className="input"
              >
                <option value="visible">{t('admin.settings.licensePlateVisible', 'Sichtbar (Pflichtfeld)')}</option>
                <option value="optional">{t('admin.settings.licensePlateOptional', 'Optional')}</option>
                <option value="hidden">{t('admin.settings.licensePlateHidden', 'Ausgeblendet')}</option>
              </select>
              <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                {settings.license_plate_mode === 'visible' && t('admin.settings.licensePlateVisibleHint', 'Kennzeichen muss bei Buchung angegeben werden')}
                {settings.license_plate_mode === 'optional' && t('admin.settings.licensePlateOptionalHint', 'Kennzeichen kann optional angegeben werden')}
                {settings.license_plate_mode === 'hidden' && t('admin.settings.licensePlateHiddenHint', 'Kennzeichenfeld wird nicht angezeigt')}
              </p>
            </div>
          </div>
        </div>
      </div>
    </motion.div>
  );
}

function ToggleRow({ label, description, checked, onChange }: {
  label: string;
  description?: string;
  checked: boolean;
  onChange: (value: boolean) => void;
}) {
  return (
    <div className="flex items-center justify-between gap-4">
      <div className="flex-1 min-w-0">
        <p className="text-sm font-medium text-gray-900 dark:text-white">{label}</p>
        {description && <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{description}</p>}
      </div>
      <button
        type="button"
        role="switch"
        aria-checked={checked}
        onClick={() => onChange(!checked)}
        className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 ${
          checked ? 'bg-primary-600' : 'bg-gray-300 dark:bg-gray-600'
        }`}
      >
        <span
          className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform shadow-sm ${
            checked ? 'translate-x-6' : 'translate-x-1'
          }`}
        />
      </button>
    </div>
  );
}
