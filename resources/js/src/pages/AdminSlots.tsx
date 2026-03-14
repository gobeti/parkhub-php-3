import { useState, useEffect, useCallback } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import {
  GridFour, SpinnerGap, Buildings, CaretDown, Check,
  Car, House, Wrench, Lock, CheckCircle, Warning,
  ArrowsClockwise, Funnel, MagnifyingGlass,
} from '@phosphor-icons/react';
import { api, ParkingLot, ParkingSlot } from '../api/client';
import { useTranslation } from 'react-i18next';
import toast from 'react-hot-toast';

// ── Types ────────────────────────────────────────────────────────────────────

interface Zone {
  id: string;
  lot_id: string;
  name: string;
  color?: string;
  description?: string;
}

interface SlotWithBooking extends ParkingSlot {
  slot_number?: string;
  current_booking?: {
    booking_id: string;
    user_id: string;
    license_plate?: string;
    start_time: string;
    end_time: string;
  } | null;
  zone_id?: string | null;
  reserved_for_department?: string | null;
}

type SlotStatus = 'available' | 'occupied' | 'reserved' | 'disabled';

interface SlotStats {
  total: number;
  available: number;
  occupied: number;
  reserved: number;
  disabled: number;
}

// ── Helpers ──────────────────────────────────────────────────────────────────

const STATUS_CONFIG: Record<SlotStatus, { color: string; bg: string; border: string; icon: typeof Car; labelKey: string; labelFallback: string }> = {
  available: { color: 'text-emerald-600 dark:text-emerald-400', bg: 'bg-emerald-50 dark:bg-emerald-900/20', border: 'border-emerald-200 dark:border-emerald-800', icon: CheckCircle, labelKey: 'slots.status.available', labelFallback: 'Frei' },
  occupied:  { color: 'text-red-600 dark:text-red-400',     bg: 'bg-red-50 dark:bg-red-900/20',     border: 'border-red-200 dark:border-red-800',     icon: Car,         labelKey: 'slots.status.occupied', labelFallback: 'Belegt' },
  reserved:  { color: 'text-amber-600 dark:text-amber-400', bg: 'bg-amber-50 dark:bg-amber-900/20', border: 'border-amber-200 dark:border-amber-800', icon: Lock,        labelKey: 'slots.status.reserved', labelFallback: 'Reserviert' },
  disabled:  { color: 'text-gray-500 dark:text-gray-400',   bg: 'bg-gray-100 dark:bg-gray-800',     border: 'border-gray-200 dark:border-gray-700',   icon: Wrench,      labelKey: 'slots.status.maintenance', labelFallback: 'Wartung' },
};

function getStatusConfig(status: SlotStatus, t: (key: string, fallback: string) => string) {
  const cfg = STATUS_CONFIG[status] ?? STATUS_CONFIG.available;
  return {
    ...cfg,
    label: t(cfg.labelKey, cfg.labelFallback),
  };
}

function computeStats(slots: SlotWithBooking[]): SlotStats {
  return slots.reduce<SlotStats>(
    (acc, slot) => {
      acc.total++;
      const status = slot.status as SlotStatus;
      if (status in acc) {
        acc[status]++;
      }
      return acc;
    },
    { total: 0, available: 0, occupied: 0, reserved: 0, disabled: 0 },
  );
}

// ── API calls (direct fetch for non-wrapped endpoints) ───────────────────────

async function fetchSlots(lotId: string): Promise<SlotWithBooking[]> {
  const token = localStorage.getItem('parkhub_token');
  const base = (import.meta.env.VITE_API_URL as string) || '';
  const res = await fetch(`${base}/api/v1/lots/${lotId}/slots`, {
    headers: token ? { Authorization: `Bearer ${token}` } : {},
  });
  const data = await res.json();
  // Backend may return {success, data} or raw array
  return Array.isArray(data) ? data : (data.data ?? []);
}

async function fetchZones(lotId: string): Promise<Zone[]> {
  const token = localStorage.getItem('parkhub_token');
  const base = (import.meta.env.VITE_API_URL as string) || '';
  const res = await fetch(`${base}/api/v1/lots/${lotId}/zones`, {
    headers: token ? { Authorization: `Bearer ${token}` } : {},
  });
  const data = await res.json();
  return Array.isArray(data) ? data : (data.data ?? []);
}

async function updateSlotAdmin(slotId: string, payload: Record<string, unknown>): Promise<SlotWithBooking> {
  const token = localStorage.getItem('parkhub_token');
  const base = (import.meta.env.VITE_API_URL as string) || '';
  // Try PUT first (api.php), fall back to PATCH (api_v1.php)
  let res = await fetch(`${base}/api/v1/admin/slots/${slotId}`, {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: JSON.stringify(payload),
  });
  if (res.status === 405) {
    res = await fetch(`${base}/api/v1/admin/slots/${slotId}`, {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json',
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
      },
      body: JSON.stringify(payload),
    });
  }
  if (!res.ok) {
    const err = await res.json().catch(() => null);
    throw new Error(err?.message ?? err?.error?.message ?? `HTTP ${res.status}`);
  }
  const data = await res.json();
  return data.data ?? data;
}

// ── Components ───────────────────────────────────────────────────────────────

function StatCard({ label, value, color, iconColor, Icon }: {
  label: string;
  value: number;
  color: string;
  iconColor: string;
  Icon: typeof Car;
}) {
  return (
    <div className="stat-card">
      <div className="flex items-center justify-between">
        <div>
          <p className="stat-label">{label}</p>
          <p className="stat-value text-gray-900 dark:text-white">{value}</p>
        </div>
        <div className={`w-12 h-12 ${color} rounded-xl flex items-center justify-center`}>
          <Icon weight="fill" className={`w-6 h-6 ${iconColor}`} />
        </div>
      </div>
    </div>
  );
}

function SlotCard({ slot, zones, onStatusChange, onDepartmentChange, saving }: {
  slot: SlotWithBooking;
  zones: Zone[];
  onStatusChange: (slotId: string, status: SlotStatus) => void;
  onDepartmentChange: (slotId: string, department: string) => void;
  saving: string | null;
}) {
  const { t } = useTranslation();
  const [showActions, setShowActions] = useState(false);
  const status = (slot.status as SlotStatus) || 'available';
  const cfg = getStatusConfig(status, t);
  const StatusIcon = cfg.icon;
  const isSaving = saving === slot.id;
  const zone = zones.find(z => z.id === slot.zone_id);

  const availableStatuses: SlotStatus[] = ['available', 'reserved', 'disabled'];

  return (
    <motion.div
      layout
      initial={{ opacity: 0, scale: 0.95 }}
      animate={{ opacity: 1, scale: 1 }}
      className={`card p-4 border-2 transition-all ${cfg.border} ${cfg.bg} relative`}
    >
      {isSaving && (
        <div className="absolute inset-0 bg-white/60 dark:bg-gray-900/60 rounded-xl flex items-center justify-center z-10">
          <SpinnerGap weight="bold" className="w-6 h-6 text-primary-600 animate-spin" />
        </div>
      )}

      {/* Header */}
      <div className="flex items-center justify-between mb-3">
        <div className="flex items-center gap-2">
          <span className="text-lg font-bold text-gray-900 dark:text-white">
            {slot.number || slot.slot_number}
          </span>
          {zone && (
            <span
              className="px-2 py-0.5 rounded-full text-xs font-medium"
              style={{
                backgroundColor: (zone.color ?? '#6b7280') + '20',
                color: zone.color ?? '#6b7280',
              }}
            >
              {zone.name}
            </span>
          )}
        </div>
        <div className={`flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold ${cfg.color} ${cfg.bg}`}>
          <StatusIcon weight="fill" className="w-3.5 h-3.5" />
          {cfg.label}
        </div>
      </div>

      {/* Booking info */}
      {slot.current_booking && (
        <div className="mb-3 p-2.5 rounded-lg bg-white/70 dark:bg-gray-900/40 border border-gray-200/60 dark:border-gray-700/40">
          <div className="flex items-center gap-2 text-sm">
            <Car weight="fill" className="w-4 h-4 text-gray-500 flex-shrink-0" />
            <span className="text-gray-700 dark:text-gray-300 font-medium truncate">
              {slot.current_booking.license_plate || t('admin.slots.noPlate', 'Kein Kennzeichen')}
            </span>
          </div>
          <div className="mt-1 text-xs text-gray-500 dark:text-gray-400">
            {new Date(slot.current_booking.start_time).toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' })}
            {' – '}
            {new Date(slot.current_booking.end_time).toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' })}
          </div>
        </div>
      )}

      {/* Department */}
      {slot.reserved_for_department && (
        <div className="mb-3 flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
          <House weight="fill" className="w-3.5 h-3.5" />
          {t('admin.slots.department', 'Abteilung')}: {slot.reserved_for_department}
        </div>
      )}

      {/* Actions toggle */}
      <button
        onClick={() => setShowActions(prev => !prev)}
        className="w-full btn btn-sm btn-secondary justify-center"
      >
        {showActions ? t('admin.slots.hideActions', 'Schließen') : t('admin.slots.editSlot', 'Bearbeiten')}
        <CaretDown weight="bold" className={`w-3.5 h-3.5 transition-transform ${showActions ? 'rotate-180' : ''}`} />
      </button>

      {/* Expanded actions */}
      <AnimatePresence>
        {showActions && (
          <motion.div
            initial={{ opacity: 0, height: 0 }}
            animate={{ opacity: 1, height: 'auto' }}
            exit={{ opacity: 0, height: 0 }}
            className="overflow-hidden"
          >
            <div className="mt-3 space-y-3 pt-3 border-t border-gray-200 dark:border-gray-700">
              {/* Status change */}
              <div>
                <label className="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5">
                  {t('admin.slots.changeStatus', 'Status ändern')}
                </label>
                <div className="flex flex-wrap gap-1.5">
                  {availableStatuses.map(s => {
                    const sCfg = getStatusConfig(s, t);
                    const isActive = status === s;
                    return (
                      <button
                        key={s}
                        onClick={() => !isActive && onStatusChange(slot.id, s)}
                        disabled={isActive || isSaving}
                        className={`px-2.5 py-1.5 rounded-lg text-xs font-medium transition-all flex items-center gap-1 ${
                          isActive
                            ? 'ring-2 ring-primary-500 bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300'
                            : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700'
                        }`}
                      >
                        {isActive && <Check weight="bold" className="w-3 h-3" />}
                        {sCfg.label}
                      </button>
                    );
                  })}
                </div>
              </div>

              {/* Department assignment */}
              <div>
                <label className="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5">
                  {t('admin.slots.assignDepartment', 'Abteilung zuweisen')}
                </label>
                <DepartmentInput
                  value={slot.reserved_for_department ?? ''}
                  onChange={(dept) => onDepartmentChange(slot.id, dept)}
                  disabled={isSaving}
                />
              </div>
            </div>
          </motion.div>
        )}
      </AnimatePresence>
    </motion.div>
  );
}

function DepartmentInput({ value, onChange, disabled }: {
  value: string;
  onChange: (val: string) => void;
  disabled: boolean;
}) {
  const { t } = useTranslation();
  const [localValue, setLocalValue] = useState(value);
  const [dirty, setDirty] = useState(false);

  useEffect(() => {
    setLocalValue(value);
    setDirty(false);
  }, [value]);

  function handleBlurOrSubmit() {
    if (dirty && localValue !== value) {
      onChange(localValue);
    }
    setDirty(false);
  }

  return (
    <div className="flex gap-1.5">
      <input
        type="text"
        value={localValue}
        onChange={(e) => { setLocalValue(e.target.value); setDirty(true); }}
        onBlur={handleBlurOrSubmit}
        onKeyDown={(e) => { if (e.key === 'Enter') handleBlurOrSubmit(); }}
        placeholder={t('admin.slots.departmentPlaceholder', 'z.B. IT, HR, Marketing…')}
        className="input text-sm flex-1"
        disabled={disabled}
      />
      {dirty && localValue !== value && (
        <button
          onClick={handleBlurOrSubmit}
          disabled={disabled}
          className="btn btn-sm btn-primary"
        >
          <Check weight="bold" className="w-3.5 h-3.5" />
        </button>
      )}
    </div>
  );
}

// ── Main page ────────────────────────────────────────────────────────────────

export function AdminSlotsPage() {
  const { t } = useTranslation();
  const [lots, setLots] = useState<ParkingLot[]>([]);
  const [selectedLotId, setSelectedLotId] = useState<string | null>(null);
  const [slots, setSlots] = useState<SlotWithBooking[]>([]);
  const [zones, setZones] = useState<Zone[]>([]);
  const [loadingLots, setLoadingLots] = useState(true);
  const [loadingSlots, setLoadingSlots] = useState(false);
  const [savingSlotId, setSavingSlotId] = useState<string | null>(null);
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState<SlotStatus | 'all'>('all');

  // Load lots on mount
  useEffect(() => {
    (async () => {
      try {
        const res = await api.getLots();
        if (res.success && res.data) {
          setLots(res.data);
          // Auto-select first lot
          if (res.data.length > 0) {
            setSelectedLotId(res.data[0].id);
          }
        }
      } finally {
        setLoadingLots(false);
      }
    })();
  }, []);

  // Load slots + zones when lot changes
  const loadSlots = useCallback(async (lotId: string) => {
    setLoadingSlots(true);
    try {
      const [slotsData, zonesData] = await Promise.all([
        fetchSlots(lotId),
        fetchZones(lotId),
      ]);
      setSlots(slotsData);
      setZones(zonesData);
    } catch (err) {
      toast.error(t('admin.slots.loadError', 'Stellplätze konnten nicht geladen werden'));
      console.error('Failed to load slots:', err);
    } finally {
      setLoadingSlots(false);
    }
  }, [t]);

  useEffect(() => {
    if (selectedLotId) {
      void loadSlots(selectedLotId);
    } else {
      setSlots([]);
      setZones([]);
    }
  }, [selectedLotId, loadSlots]);

  // Handlers
  async function handleStatusChange(slotId: string, newStatus: SlotStatus) {
    setSavingSlotId(slotId);
    try {
      const updated = await updateSlotAdmin(slotId, { status: newStatus });
      setSlots(prev => prev.map(s => s.id === slotId ? { ...s, ...updated } : s));
      const cfg = getStatusConfig(newStatus, t);
      toast.success(t('admin.slots.statusUpdated', `Status auf „${cfg.label}" geändert`));
    } catch (err) {
      toast.error(
        err instanceof Error
          ? err.message
          : t('admin.slots.updateError', 'Aktualisierung fehlgeschlagen'),
      );
    } finally {
      setSavingSlotId(null);
    }
  }

  async function handleDepartmentChange(slotId: string, department: string) {
    setSavingSlotId(slotId);
    try {
      const updated = await updateSlotAdmin(slotId, { reserved_for_department: department || null });
      setSlots(prev => prev.map(s => s.id === slotId ? { ...s, ...updated } : s));
      toast.success(
        department
          ? t('admin.slots.departmentAssigned', `Abteilung „${department}" zugewiesen`)
          : t('admin.slots.departmentCleared', 'Abteilungszuweisung entfernt'),
      );
    } catch (err) {
      toast.error(
        err instanceof Error
          ? err.message
          : t('admin.slots.updateError', 'Aktualisierung fehlgeschlagen'),
      );
    } finally {
      setSavingSlotId(null);
    }
  }

  function handleRefresh() {
    if (selectedLotId) {
      void loadSlots(selectedLotId);
    }
  }

  // Filtering
  const filteredSlots = slots.filter(slot => {
    if (statusFilter !== 'all' && slot.status !== statusFilter) return false;
    if (search) {
      const q = search.toLowerCase();
      const slotNum = (slot.number || slot.slot_number || '').toLowerCase();
      const dept = (slot.reserved_for_department || '').toLowerCase();
      const plate = (slot.current_booking?.license_plate || '').toLowerCase();
      if (!slotNum.includes(q) && !dept.includes(q) && !plate.includes(q)) return false;
    }
    return true;
  });

  const stats = computeStats(slots);
  const selectedLot = lots.find(l => l.id === selectedLotId);

  // Loading state
  if (loadingLots) {
    return (
      <div className="flex items-center justify-center h-64">
        <SpinnerGap weight="bold" className="w-8 h-8 text-primary-600 animate-spin" />
      </div>
    );
  }

  return (
    <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="space-y-6">
      {/* Header */}
      <div className="flex items-center gap-3">
        <GridFour weight="fill" className="w-6 h-6 text-primary-600" />
        <h2 className="text-xl font-semibold text-gray-900 dark:text-white">
          {t('admin.slots.title', 'Stellplatzverwaltung')}
        </h2>
      </div>

      {/* Lot selector */}
      <div className="card p-4">
        <div className="flex flex-col sm:flex-row items-start sm:items-center gap-4">
          <div className="flex items-center gap-2 flex-1 min-w-0">
            <Buildings weight="fill" className="w-5 h-5 text-gray-500 flex-shrink-0" />
            <label className="text-sm font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap">
              {t('admin.slots.selectLot', 'Parkplatz auswählen')}:
            </label>
            <select
              value={selectedLotId ?? ''}
              onChange={(e) => setSelectedLotId(e.target.value || null)}
              className="input text-sm flex-1 min-w-0"
            >
              <option value="">{t('admin.slots.chooseLot', '– Parkplatz wählen –')}</option>
              {lots.map(lot => (
                <option key={lot.id} value={lot.id}>
                  {lot.name} ({lot.total_slots} {t('admin.slots.slotsLabel', 'Stellplätze')})
                </option>
              ))}
            </select>
          </div>
          <button
            onClick={handleRefresh}
            disabled={!selectedLotId || loadingSlots}
            className="btn btn-secondary btn-sm"
          >
            <ArrowsClockwise weight="bold" className={`w-4 h-4 ${loadingSlots ? 'animate-spin' : ''}`} />
            {t('admin.slots.refresh', 'Aktualisieren')}
          </button>
        </div>
      </div>

      {/* No lot selected */}
      {!selectedLotId && (
        <div className="p-12 text-center">
          <Buildings weight="light" className="w-16 h-16 text-gray-300 dark:text-gray-700 mx-auto mb-4" />
          <p className="text-gray-500 dark:text-gray-400">
            {lots.length === 0
              ? t('admin.slots.noLots', 'Keine Parkplätze vorhanden. Erstellen Sie zuerst einen Parkplatz.')
              : t('admin.slots.selectLotHint', 'Wählen Sie einen Parkplatz aus, um dessen Stellplätze zu verwalten.')}
          </p>
        </div>
      )}

      {/* Content when lot is selected */}
      {selectedLotId && (
        <>
          {/* Summary stats */}
          {!loadingSlots && (
            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
              <StatCard
                label={t('admin.slots.stats.total', 'Gesamt')}
                value={stats.total}
                color="bg-primary-100 dark:bg-primary-900/30"
                iconColor="text-primary-600 dark:text-primary-400"
                Icon={GridFour}
              />
              <StatCard
                label={t('admin.slots.stats.available', 'Frei')}
                value={stats.available}
                color="bg-emerald-100 dark:bg-emerald-900/30"
                iconColor="text-emerald-600 dark:text-emerald-400"
                Icon={CheckCircle}
              />
              <StatCard
                label={t('admin.slots.stats.occupied', 'Belegt')}
                value={stats.occupied}
                color="bg-red-100 dark:bg-red-900/30"
                iconColor="text-red-600 dark:text-red-400"
                Icon={Car}
              />
              <StatCard
                label={t('admin.slots.stats.reserved', 'Reserviert')}
                value={stats.reserved}
                color="bg-amber-100 dark:bg-amber-900/30"
                iconColor="text-amber-600 dark:text-amber-400"
                Icon={Lock}
              />
              <StatCard
                label={t('admin.slots.stats.disabled', 'Wartung')}
                value={stats.disabled}
                color="bg-gray-200 dark:bg-gray-800"
                iconColor="text-gray-500 dark:text-gray-400"
                Icon={Wrench}
              />
            </div>
          )}

          {/* Filters */}
          {!loadingSlots && slots.length > 0 && (
            <div className="card p-4">
              <div className="flex flex-col sm:flex-row items-start sm:items-center gap-3">
                {/* Search */}
                <div className="relative flex-1 min-w-0 w-full sm:w-auto">
                  <MagnifyingGlass weight="bold" className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                  <input
                    type="text"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder={t('admin.slots.searchPlaceholder', 'Stellplatz, Kennzeichen oder Abteilung suchen…')}
                    className="input pl-9 text-sm w-full"
                  />
                </div>

                {/* Status filter */}
                <div className="flex items-center gap-2">
                  <Funnel weight="bold" className="w-4 h-4 text-gray-400" />
                  <select
                    value={statusFilter}
                    onChange={(e) => setStatusFilter(e.target.value as SlotStatus | 'all')}
                    className="input text-sm"
                  >
                    <option value="all">{t('admin.slots.filter.all', 'Alle Status')}</option>
                    <option value="available">{t('admin.slots.status.available', 'Frei')}</option>
                    <option value="occupied">{t('admin.slots.status.occupied', 'Belegt')}</option>
                    <option value="reserved">{t('admin.slots.status.reserved', 'Reserviert')}</option>
                    <option value="disabled">{t('admin.slots.status.disabled', 'Wartung')}</option>
                  </select>
                </div>
              </div>
            </div>
          )}

          {/* Loading slots */}
          {loadingSlots && (
            <div className="flex items-center justify-center h-48">
              <SpinnerGap weight="bold" className="w-8 h-8 text-primary-600 animate-spin" />
            </div>
          )}

          {/* Empty state */}
          {!loadingSlots && slots.length === 0 && (
            <div className="p-12 text-center">
              <Warning weight="light" className="w-16 h-16 text-gray-300 dark:text-gray-700 mx-auto mb-4" />
              <p className="text-gray-500 dark:text-gray-400">
                {t('admin.slots.noSlots', 'Dieser Parkplatz hat noch keine Stellplätze. Erstellen Sie Stellplätze im Layout-Editor unter „Parkplätze".')}
              </p>
            </div>
          )}

          {/* Filtered empty state */}
          {!loadingSlots && slots.length > 0 && filteredSlots.length === 0 && (
            <div className="p-8 text-center">
              <MagnifyingGlass weight="light" className="w-12 h-12 text-gray-300 dark:text-gray-700 mx-auto mb-3" />
              <p className="text-gray-500 dark:text-gray-400">
                {t('admin.slots.noResults', 'Keine Stellplätze gefunden.')}
              </p>
            </div>
          )}

          {/* Slot grid */}
          {!loadingSlots && filteredSlots.length > 0 && (
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
              {filteredSlots.map(slot => (
                <SlotCard
                  key={slot.id}
                  slot={slot}
                  zones={zones}
                  onStatusChange={handleStatusChange}
                  onDepartmentChange={handleDepartmentChange}
                  saving={savingSlotId}
                />
              ))}
            </div>
          )}

          {/* Lot info footer */}
          {!loadingSlots && selectedLot && slots.length > 0 && (
            <div className="text-xs text-gray-400 dark:text-gray-500 text-center">
              {selectedLot.name} — {filteredSlots.length !== slots.length
                ? t('admin.slots.filteredCount', `${filteredSlots.length} von ${slots.length} Stellplätzen angezeigt`)
                : t('admin.slots.totalCount', `${slots.length} Stellplätze`)}
            </div>
          )}
        </>
      )}
    </motion.div>
  );
}
