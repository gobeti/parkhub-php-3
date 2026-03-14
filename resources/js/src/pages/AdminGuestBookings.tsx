import { useState, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import {
  UserPlus, SpinnerGap, XCircle, Plus, MagnifyingGlass,
  ClipboardText, Car, Buildings, Clock,
  Check, X,
} from '@phosphor-icons/react';
import { api, ParkingLot, ParkingSlot, AdminGuestBooking } from '../api/client';
import { useTranslation } from 'react-i18next';
import toast from 'react-hot-toast';

// ── Types ────────────────────────────────────────────────────────────────────

type StatusFilter = 'all' | 'confirmed' | 'active' | 'completed' | 'cancelled';

const STATUS_CONFIG: Record<string, { label: string; class: string }> = {
  confirmed: { label: 'Bestätigt', class: 'badge-success' },
  active: { label: 'Aktiv', class: 'badge-success' },
  completed: { label: 'Abgeschlossen', class: 'badge-gray' },
  cancelled: { label: 'Storniert', class: 'badge-error' },
};

// ── Create Guest Booking Modal ───────────────────────────────────────────────

function CreateGuestBookingModal({ onClose, onCreated }: { onClose: () => void; onCreated: () => void }) {
  const { t } = useTranslation();
  const [lots, setLots] = useState<ParkingLot[]>([]);
  const [slots, setSlots] = useState<ParkingSlot[]>([]);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);

  const [guestName, setGuestName] = useState('');
  const [lotId, setLotId] = useState('');
  const [slotId, setSlotId] = useState('');
  const [startTime, setStartTime] = useState(() => {
    const now = new Date();
    now.setMinutes(0, 0, 0);
    return now.toISOString().slice(0, 16);
  });
  const [endTime, setEndTime] = useState(() => {
    const now = new Date();
    now.setHours(now.getHours() + 8);
    now.setMinutes(0, 0, 0);
    return now.toISOString().slice(0, 16);
  });
  const [vehiclePlate, setVehiclePlate] = useState('');

  useEffect(() => {
    (async () => {
      try {
        const res = await api.getLots();
        if (res.success && res.data) {
          setLots(res.data);
          if (res.data.length > 0) {
            setLotId(res.data[0].id);
          }
        }
      } finally {
        setLoading(false);
      }
    })();
  }, []);

  useEffect(() => {
    if (!lotId) {
      setSlots([]);
      return;
    }
    (async () => {
      const res = await api.getLotSlots(lotId);
      if (res.success && res.data) {
        setSlots(res.data);
      }
    })();
  }, [lotId]);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!guestName.trim() || !lotId || !startTime || !endTime) return;

    setSubmitting(true);
    try {
      const res = await api.createGuestBooking({
        lot_id: lotId,
        slot_id: slotId || undefined as unknown as string,
        guest_name: guestName.trim(),
        start_time: new Date(startTime).toISOString(),
        end_time: new Date(endTime).toISOString(),
        license_plate: vehiclePlate.trim() || undefined,
      });

      if (res.success) {
        toast.success(t('admin.guests.created', 'Gästebuchung erstellt'));
        onCreated();
      } else {
        const msg = res.error?.message || 'Fehler beim Erstellen';
        toast.error(msg);
      }
    } finally {
      setSubmitting(false);
    }
  }

  if (loading) {
    return (
      <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div className="card p-8">
          <SpinnerGap weight="bold" className="w-8 h-8 text-primary-600 animate-spin mx-auto" />
        </div>
      </div>
    );
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" onClick={onClose}>
      <motion.div
        initial={{ opacity: 0, scale: 0.95 }}
        animate={{ opacity: 1, scale: 1 }}
        exit={{ opacity: 0, scale: 0.95 }}
        className="card p-6 w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="flex items-center justify-between mb-6">
          <h3 className="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
            <UserPlus weight="fill" className="w-5 h-5 text-primary-600" />
            {t('admin.guests.createTitle', 'Neue Gästebuchung')}
          </h3>
          <button onClick={onClose} className="p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
            <X weight="bold" className="w-5 h-5 text-gray-400" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4">
          {/* Guest Name */}
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {t('admin.guests.guestName', 'Name des Gastes')} *
            </label>
            <input
              type="text"
              value={guestName}
              onChange={(e) => setGuestName(e.target.value)}
              className="input"
              placeholder="Max Mustermann"
              required
              autoFocus
            />
          </div>

          {/* Lot Selection */}
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {t('admin.guests.lot', 'Parkplatz')} *
            </label>
            <select
              value={lotId}
              onChange={(e) => { setLotId(e.target.value); setSlotId(''); }}
              className="input"
              required
            >
              <option value="">{t('admin.guests.selectLot', 'Parkplatz wählen...')}</option>
              {lots.map((lot) => (
                <option key={lot.id} value={lot.id}>
                  {lot.name} ({lot.available_slots}/{lot.total_slots} {t('common.free', 'frei')})
                </option>
              ))}
            </select>
          </div>

          {/* Slot Selection */}
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {t('admin.guests.slot', 'Stellplatz')}
            </label>
            <select
              value={slotId}
              onChange={(e) => setSlotId(e.target.value)}
              className="input"
            >
              <option value="">{t('admin.guests.autoAssign', 'Automatisch zuweisen')}</option>
              {slots
                .filter((s) => s.status === 'available')
                .map((s) => (
                  <option key={s.id} value={s.id}>
                    {s.number}{s.section ? ` (${s.section})` : ''}
                  </option>
                ))}
            </select>
          </div>

          {/* Date/Time */}
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                {t('admin.guests.startTime', 'Von')} *
              </label>
              <input
                type="datetime-local"
                value={startTime}
                onChange={(e) => setStartTime(e.target.value)}
                className="input"
                required
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                {t('admin.guests.endTime', 'Bis')} *
              </label>
              <input
                type="datetime-local"
                value={endTime}
                onChange={(e) => setEndTime(e.target.value)}
                className="input"
                required
              />
            </div>
          </div>

          {/* Vehicle Plate */}
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {t('admin.guests.vehiclePlate', 'Kennzeichen')}
              <span className="text-gray-400 ml-1">({t('common.optional', 'optional')})</span>
            </label>
            <input
              type="text"
              value={vehiclePlate}
              onChange={(e) => setVehiclePlate(e.target.value.toUpperCase())}
              className="input font-mono"
              placeholder="M-AB 1234"
            />
          </div>

          {/* Actions */}
          <div className="flex gap-3 pt-2">
            <button type="button" onClick={onClose} className="btn btn-secondary flex-1">
              {t('common.cancel', 'Abbrechen')}
            </button>
            <button
              type="submit"
              disabled={submitting || !guestName.trim() || !lotId}
              className="btn btn-primary flex-1"
            >
              {submitting
                ? <SpinnerGap weight="bold" className="w-4 h-4 animate-spin" />
                : <Plus weight="bold" className="w-4 h-4" />}
              {t('admin.guests.create', 'Erstellen')}
            </button>
          </div>
        </form>
      </motion.div>
    </div>
  );
}

// ── Guest Code Display ───────────────────────────────────────────────────────

function GuestCodeBadge({ code }: { code: string }) {
  const [copied, setCopied] = useState(false);

  function handleCopy() {
    navigator.clipboard.writeText(code).then(() => {
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    });
  }

  return (
    <button
      onClick={handleCopy}
      className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-800 text-primary-700 dark:text-primary-300 font-mono font-bold text-sm tracking-wider hover:bg-primary-100 dark:hover:bg-primary-900/30 transition-colors"
      title="Code kopieren"
    >
      {copied ? (
        <Check weight="bold" className="w-3.5 h-3.5 text-emerald-500" />
      ) : (
        <ClipboardText weight="bold" className="w-3.5 h-3.5" />
      )}
      {code}
    </button>
  );
}

// ── Main Page ────────────────────────────────────────────────────────────────

export function AdminGuestBookingsPage() {
  const { t } = useTranslation();
  const [bookings, setBookings] = useState<AdminGuestBooking[]>([]);
  const [loading, setLoading] = useState(true);
  const [statusFilter, setStatusFilter] = useState<StatusFilter>('all');
  const [fromDate, setFromDate] = useState('');
  const [toDate, setToDate] = useState('');
  const [search, setSearch] = useState('');
  const [cancellingId, setCancellingId] = useState<string | null>(null);
  const [showCreate, setShowCreate] = useState(false);

  async function loadBookings() {
    setLoading(true);
    try {
      const params: Record<string, string> = {};
      if (statusFilter !== 'all') params.status = statusFilter;
      if (fromDate) params.from_date = fromDate;
      if (toDate) params.to_date = toDate;

      const res = await api.getAdminGuestBookings(params);
      if (res.success && res.data) {
        setBookings(res.data);
      }
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    void loadBookings();
  }, [statusFilter, fromDate, toDate]);

  async function handleCancel(id: string, name: string) {
    if (!confirm(t('admin.guests.confirmCancel', `Gästebuchung von "${name}" wirklich stornieren?`))) return;
    setCancellingId(id);
    try {
      const res = await api.cancelAdminGuestBooking(id);
      if (res.success) {
        setBookings((prev) =>
          prev.map((b) => (b.id === id ? { ...b, status: 'cancelled' as const } : b))
        );
        toast.success(t('admin.guests.cancelled', 'Gästebuchung storniert'));
      } else {
        toast.error(res.error?.message || 'Fehler');
      }
    } finally {
      setCancellingId(null);
    }
  }

  function formatDateTime(iso: string) {
    const d = new Date(iso);
    return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
      + ' '
      + d.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
  }

  // Search filter (client-side)
  const filtered = bookings.filter((b) => {
    if (!search) return true;
    const q = search.toLowerCase();
    return (
      b.guest_name.toLowerCase().includes(q) ||
      b.guest_code.toLowerCase().includes(q) ||
      (b.vehicle_plate && b.vehicle_plate.toLowerCase().includes(q)) ||
      b.lot_name.toLowerCase().includes(q) ||
      b.slot_number.toLowerCase().includes(q) ||
      b.created_by_name.toLowerCase().includes(q)
    );
  });

  const counts = {
    total: bookings.length,
    confirmed: bookings.filter((b) => b.status === 'confirmed' || b.status === 'active').length,
    completed: bookings.filter((b) => b.status === 'completed').length,
    cancelled: bookings.filter((b) => b.status === 'cancelled').length,
  };

  if (loading && bookings.length === 0) {
    return (
      <div className="flex items-center justify-center h-64">
        <SpinnerGap weight="bold" className="w-8 h-8 text-primary-600 animate-spin" />
      </div>
    );
  }

  return (
    <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <UserPlus weight="fill" className="w-6 h-6 text-primary-600" />
          <h2 className="text-xl font-semibold text-gray-900 dark:text-white">
            {t('admin.guests.title', 'Gästebuchungen')}
          </h2>
        </div>
        <button onClick={() => setShowCreate(true)} className="btn btn-primary">
          <Plus weight="bold" className="w-4 h-4" />
          {t('admin.guests.newBooking', 'Neue Gästebuchung')}
        </button>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div className="stat-card">
          <div className="flex items-center justify-between">
            <div>
              <p className="stat-label">{t('admin.guests.statsTotal', 'Gesamt')}</p>
              <p className="stat-value text-gray-900 dark:text-white">{counts.total}</p>
            </div>
            <div className="w-10 h-10 bg-primary-100 dark:bg-primary-900/30 rounded-xl flex items-center justify-center">
              <UserPlus weight="fill" className="w-5 h-5 text-primary-600 dark:text-primary-400" />
            </div>
          </div>
        </div>
        <div className="stat-card">
          <div className="flex items-center justify-between">
            <div>
              <p className="stat-label">{t('admin.guests.statsActive', 'Aktiv')}</p>
              <p className="stat-value text-emerald-600 dark:text-emerald-400">{counts.confirmed}</p>
            </div>
            <div className="w-10 h-10 bg-emerald-100 dark:bg-emerald-900/30 rounded-xl flex items-center justify-center">
              <Check weight="bold" className="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
            </div>
          </div>
        </div>
        <div className="stat-card">
          <div className="flex items-center justify-between">
            <div>
              <p className="stat-label">{t('admin.guests.statsCancelled', 'Storniert')}</p>
              <p className="stat-value text-red-600 dark:text-red-400">{counts.cancelled}</p>
            </div>
            <div className="w-10 h-10 bg-red-100 dark:bg-red-900/30 rounded-xl flex items-center justify-center">
              <XCircle weight="fill" className="w-5 h-5 text-red-600 dark:text-red-400" />
            </div>
          </div>
        </div>
      </div>

      {/* Filters */}
      <div className="flex flex-col sm:flex-row gap-3 flex-wrap">
        <div className="relative flex-1 min-w-[200px]">
          <MagnifyingGlass weight="bold" className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
          <input
            type="text"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="input pl-9"
            placeholder={t('admin.guests.searchPlaceholder', 'Name, Code, Kennzeichen...')}
          />
        </div>
        <select
          value={statusFilter}
          onChange={(e) => setStatusFilter(e.target.value as StatusFilter)}
          className="input w-auto"
        >
          <option value="all">{t('admin.guests.allStatus', 'Alle Status')}</option>
          <option value="confirmed">{t('admin.guests.statusConfirmed', 'Bestätigt')}</option>
          <option value="completed">{t('admin.guests.statusCompleted', 'Abgeschlossen')}</option>
          <option value="cancelled">{t('admin.guests.statusCancelled', 'Storniert')}</option>
        </select>
        <input
          type="date"
          value={fromDate}
          onChange={(e) => setFromDate(e.target.value)}
          className="input w-auto"
          placeholder={t('admin.guests.fromDate', 'Von')}
          title={t('admin.guests.fromDate', 'Von')}
        />
        <input
          type="date"
          value={toDate}
          onChange={(e) => setToDate(e.target.value)}
          className="input w-auto"
          placeholder={t('admin.guests.toDate', 'Bis')}
          title={t('admin.guests.toDate', 'Bis')}
        />
        {(statusFilter !== 'all' || fromDate || toDate || search) && (
          <button
            onClick={() => { setStatusFilter('all'); setFromDate(''); setToDate(''); setSearch(''); }}
            className="btn btn-ghost btn-sm text-gray-500"
          >
            <X weight="bold" className="w-4 h-4" />
            {t('admin.guests.clearFilters', 'Filter zurücksetzen')}
          </button>
        )}
      </div>

      {/* Table */}
      <div className="card overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead>
              <tr className="bg-gray-50 dark:bg-gray-800/50">
                <th className="text-left px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  {t('admin.guests.colGuest', 'Gast')}
                </th>
                <th className="text-left px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  {t('admin.guests.colCode', 'Code')}
                </th>
                <th className="text-left px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  {t('admin.guests.colLotSlot', 'Parkplatz / Stellplatz')}
                </th>
                <th className="text-left px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  {t('admin.guests.colPeriod', 'Zeitraum')}
                </th>
                <th className="text-left px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  {t('admin.guests.colPlate', 'Kennzeichen')}
                </th>
                <th className="text-left px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  {t('admin.guests.colStatus', 'Status')}
                </th>
                <th className="text-left px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  {t('admin.guests.colCreatedBy', 'Erstellt von')}
                </th>
                <th className="text-right px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  {t('admin.users.actions', 'Aktionen')}
                </th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
              {filtered.map((b, i) => {
                const sc = STATUS_CONFIG[b.status] || { label: b.status, class: 'badge-gray' };
                return (
                  <motion.tr
                    key={b.id}
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    transition={{ delay: i * 0.03 }}
                    className="hover:bg-gray-50 dark:hover:bg-gray-800/30 transition-colors"
                  >
                    <td className="px-6 py-4">
                      <span className="font-medium text-gray-900 dark:text-white">{b.guest_name}</span>
                    </td>
                    <td className="px-6 py-4">
                      <GuestCodeBadge code={b.guest_code} />
                    </td>
                    <td className="px-6 py-4">
                      <div className="flex items-center gap-1.5">
                        <Buildings weight="regular" className="w-4 h-4 text-gray-400 shrink-0" />
                        <span className="text-sm text-gray-700 dark:text-gray-300">{b.lot_name}</span>
                        <span className="text-gray-400 dark:text-gray-600 mx-0.5">/</span>
                        <span className="font-mono font-bold text-gray-900 dark:text-white">{b.slot_number}</span>
                      </div>
                    </td>
                    <td className="px-6 py-4">
                      <div className="text-sm text-gray-500 dark:text-gray-400">
                        <div className="flex items-center gap-1.5">
                          <Clock weight="regular" className="w-3.5 h-3.5 shrink-0" />
                          {formatDateTime(b.start_time)}
                        </div>
                        <div className="flex items-center gap-1.5 mt-0.5">
                          <Clock weight="regular" className="w-3.5 h-3.5 shrink-0" />
                          {formatDateTime(b.end_time)}
                        </div>
                      </div>
                    </td>
                    <td className="px-6 py-4">
                      {b.vehicle_plate ? (
                        <span className="inline-flex items-center gap-1 text-sm font-mono text-gray-700 dark:text-gray-300">
                          <Car weight="regular" className="w-4 h-4 text-gray-400" />
                          {b.vehicle_plate}
                        </span>
                      ) : (
                        <span className="text-sm text-gray-400">-</span>
                      )}
                    </td>
                    <td className="px-6 py-4">
                      <span className={`badge ${sc.class}`}>{sc.label}</span>
                    </td>
                    <td className="px-6 py-4">
                      <span className="text-sm text-gray-500 dark:text-gray-400">{b.created_by_name}</span>
                    </td>
                    <td className="px-6 py-4 text-right">
                      {(b.status === 'confirmed' || b.status === 'active') && (
                        <button
                          onClick={() => handleCancel(b.id, b.guest_name)}
                          disabled={cancellingId === b.id}
                          className="btn btn-ghost btn-sm text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20"
                        >
                          {cancellingId === b.id ? (
                            <SpinnerGap weight="bold" className="w-4 h-4 animate-spin" />
                          ) : (
                            <XCircle weight="bold" className="w-4 h-4" />
                          )}
                          {t('bookings.cancelBtn', 'Stornieren')}
                        </button>
                      )}
                    </td>
                  </motion.tr>
                );
              })}
            </tbody>
          </table>
        </div>
        {filtered.length === 0 && (
          <div className="p-12 text-center">
            <UserPlus weight="light" className="w-16 h-16 text-gray-300 dark:text-gray-700 mx-auto mb-4" />
            <p className="text-gray-500 dark:text-gray-400">
              {search || statusFilter !== 'all' || fromDate || toDate
                ? t('admin.guests.noResults', 'Keine Gästebuchungen gefunden')
                : t('admin.guests.noBookings', 'Noch keine Gästebuchungen vorhanden')}
            </p>
          </div>
        )}
      </div>

      {/* Create Modal */}
      <AnimatePresence>
        {showCreate && (
          <CreateGuestBookingModal
            onClose={() => setShowCreate(false)}
            onCreated={() => { setShowCreate(false); void loadBookings(); }}
          />
        )}
      </AnimatePresence>
    </motion.div>
  );
}
