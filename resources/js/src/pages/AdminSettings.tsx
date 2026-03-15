import { useState, useEffect } from 'react';
import { motion } from 'framer-motion';
import { SlidersHorizontal, SpinnerGap, Check, Buildings, CalendarCheck, Timer, CurrencyCircleDollar, Car, Bell, Queue } from '@phosphor-icons/react';
import { api } from '../api/client';
import { useTranslation } from 'react-i18next';
import toast from 'react-hot-toast';

interface AppSettings {
  company_name: string;
  use_case: string;
  self_registration: string;
  license_plate_mode: string;
  display_name_format: string;
  max_bookings_per_day: string;
  allow_guest_bookings: string;
  auto_release_minutes: string;
  require_vehicle: string;
  waitlist_enabled: string;
  min_booking_duration_hours: string;
  max_booking_duration_hours: string;
  credits_enabled: string;
  credits_per_booking: string;
  [key: string]: string;
}

const defaultSettings: AppSettings = {
  company_name: 'ParkHub',
  use_case: 'corporate',
  self_registration: 'true',
  license_plate_mode: 'optional',
  display_name_format: 'first_name',
  max_bookings_per_day: '3',
  allow_guest_bookings: 'false',
  auto_release_minutes: '30',
  require_vehicle: 'false',
  waitlist_enabled: 'true',
  min_booking_duration_hours: '0',
  max_booking_duration_hours: '0',
  credits_enabled: 'false',
  credits_per_booking: '1',
};

const API_BASE = import.meta.env.VITE_API_URL || '';

export function AdminSettingsPage() {
  const { t } = useTranslation();
  const [settings, setSettings] = useState<AppSettings>(defaultSettings);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  useEffect(() => { loadSettings(); }, []);

  async function loadSettings() {
    try {
      const token = api.getToken();
      const response = await fetch(`${API_BASE}/api/v1/admin/settings`, {
        headers: { 'Content-Type': 'application/json', ...(token ? { 'Authorization': `Bearer ${token}` } : {}) },
      });
      const data = await response.json();
      // Backend wraps response in {success, data, error, meta}
      const settingsObj = data?.data ?? data;
      if (settingsObj && typeof settingsObj === 'object' && !settingsObj.error) {
        setSettings(prev => ({ ...prev, ...settingsObj }));
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
        headers: { 'Content-Type': 'application/json', ...(token ? { 'Authorization': `Bearer ${token}` } : {}) },
        body: JSON.stringify(settings),
      });
      const res = await response.json();
      if (response.ok) {
        toast.success(t('admin.settings.saved', 'Einstellungen gespeichert'));
      } else {
        toast.error(res.error?.message || res.message || t('admin.settings.saveError', 'Speichern fehlgeschlagen'));
      }
    } catch {
      toast.error(t('admin.settings.saveError', 'Speichern fehlgeschlagen'));
    } finally {
      setSaving(false);
    }
  }

  function update(key: string, value: string) {
    setSettings(prev => ({ ...prev, [key]: value }));
  }

  function toBool(v: string): boolean { return v === 'true' || v === '1'; }
  function fromBool(v: boolean): string { return v ? 'true' : 'false'; }

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
              <input type="text" value={settings.company_name} onChange={(e) => update('company_name', e.target.value)} className="input" placeholder="ParkHub" />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                {t('admin.settings.useCase', 'Einsatzbereich')}
              </label>
              <select value={settings.use_case} onChange={(e) => update('use_case', e.target.value)} className="input">
                <option value="corporate">{t('admin.settings.useCaseCorporate', 'Unternehmen')}</option>
                <option value="university">{t('admin.settings.useCaseUniversity', 'Universität')}</option>
                <option value="residential">{t('admin.settings.useCaseResidential', 'Wohnanlage')}</option>
                <option value="other">{t('admin.settings.useCaseOther', 'Andere')}</option>
              </select>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                {t('admin.settings.displayNameFormat', 'Anzeigename')}
              </label>
              <select value={settings.display_name_format} onChange={(e) => update('display_name_format', e.target.value)} className="input">
                <option value="first_name">Vorname</option>
                <option value="full_name">Vollständiger Name</option>
                <option value="username">Benutzername</option>
              </select>
            </div>

            <ToggleRow
              label={t('admin.settings.selfRegistration', 'Selbstregistrierung')}
              description={t('admin.settings.selfRegistrationDesc', 'Nutzer können sich selbst registrieren')}
              checked={toBool(settings.self_registration)}
              onChange={(v) => update('self_registration', fromBool(v))}
            />
          </div>

          {/* Booking Rules */}
          <div className="card p-6 space-y-4">
            <h3 className="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
              <CalendarCheck weight="fill" className="w-5 h-5 text-primary-600" />
              {t('admin.settings.bookingRules', 'Buchungsregeln')}
            </h3>

            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  {t('admin.settings.maxBookingsPerDay', 'Max. Buchungen / Tag')}
                </label>
                <input type="number" min={0} max={50} value={settings.max_bookings_per_day}
                  onChange={(e) => update('max_bookings_per_day', e.target.value)} className="input" />
                <p className="text-xs text-gray-400 mt-1">0 = unbegrenzt</p>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Min. Dauer (Std.)
                </label>
                <input type="number" min={0} max={24} step={0.5} value={settings.min_booking_duration_hours}
                  onChange={(e) => update('min_booking_duration_hours', e.target.value)} className="input" />
                <p className="text-xs text-gray-400 mt-1">0 = kein Minimum</p>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Max. Dauer (Std.)
                </label>
                <input type="number" min={0} max={72} step={0.5} value={settings.max_booking_duration_hours}
                  onChange={(e) => update('max_booking_duration_hours', e.target.value)} className="input" />
                <p className="text-xs text-gray-400 mt-1">0 = unbegrenzt</p>
              </div>
            </div>

            <ToggleRow
              label={t('admin.settings.allowGuestBookings', 'Gastbuchungen erlauben')}
              description="Gäste können ohne Account über Admins gebucht werden"
              checked={toBool(settings.allow_guest_bookings)}
              onChange={(v) => update('allow_guest_bookings', fromBool(v))}
            />

            <ToggleRow
              label={t('admin.settings.requireVehicle', 'Fahrzeug/Kennzeichen erforderlich')}
              description="Buchung erfordert ein Fahrzeugkennzeichen"
              checked={toBool(settings.require_vehicle)}
              onChange={(v) => update('require_vehicle', fromBool(v))}
            />
          </div>

          {/* Save Button */}
          <button onClick={handleSave} disabled={saving} className="btn btn-primary w-full">
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

            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Auto-Freigabe nach (Minuten)
              </label>
              <input type="number" min={0} max={480} value={settings.auto_release_minutes}
                onChange={(e) => update('auto_release_minutes', e.target.value)} className="input" />
              <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                Buchungen ohne Check-in werden nach dieser Zeit automatisch freigegeben. 0 = deaktiviert.
              </p>
            </div>
          </div>

          {/* Waitlist */}
          <div className="card p-6 space-y-4">
            <h3 className="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
              <Queue weight="fill" className="w-5 h-5 text-primary-600" />
              Warteliste
            </h3>

            <ToggleRow
              label="Warteliste aktivieren"
              description="Nutzer können sich auf die Warteliste setzen wenn ein Parkplatz voll ist"
              checked={toBool(settings.waitlist_enabled)}
              onChange={(v) => update('waitlist_enabled', fromBool(v))}
            />
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
              checked={toBool(settings.credits_enabled)}
              onChange={(v) => update('credits_enabled', fromBool(v))}
            />

            {toBool(settings.credits_enabled) && (
              <motion.div initial={{ opacity: 0, height: 0 }} animate={{ opacity: 1, height: 'auto' }}>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  {t('admin.settings.creditsPerBooking', 'Kontingent pro Buchung')}
                </label>
                <input type="number" min={1} max={100} value={settings.credits_per_booking}
                  onChange={(e) => update('credits_per_booking', e.target.value)} className="input" />
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
              <select value={settings.license_plate_mode} onChange={(e) => update('license_plate_mode', e.target.value)} className="input">
                <option value="required">Pflichtfeld</option>
                <option value="optional">Optional</option>
                <option value="disabled">Ausgeblendet</option>
              </select>
              <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                {settings.license_plate_mode === 'required' && 'Kennzeichen muss bei jeder Buchung angegeben werden'}
                {settings.license_plate_mode === 'optional' && 'Kennzeichen kann optional angegeben werden'}
                {settings.license_plate_mode === 'disabled' && 'Kennzeichenfeld wird nicht angezeigt'}
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
        <span className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform shadow-sm ${checked ? 'translate-x-6' : 'translate-x-1'}`} />
      </button>
    </div>
  );
}
