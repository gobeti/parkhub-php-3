import { useState, useEffect, useMemo } from 'react';
import { motion } from 'framer-motion';
import type { Icon } from '@phosphor-icons/react';
import {
  ChartBar, SpinnerGap, CalendarBlank, Clock, TrendUp, Fire,
} from '@phosphor-icons/react';
import { api, AdminReportData, DashboardChartData, HeatmapEntry } from '../api/client';
import { useTranslation } from 'react-i18next';

// ── Helpers ──────────────────────────────────────────────────────────────────

const STATUS_COLORS: Record<string, string> = {
  confirmed: '#3B82F6',
  active: '#10B981',
  completed: '#6366F1',
  cancelled: '#EF4444',
  no_show: '#F59E0B',
};

const STATUS_LABEL_KEYS: Record<string, { key: string; fallback: string }> = {
  confirmed: { key: 'reports.statusLabels.confirmed', fallback: 'Bestätigt' },
  active: { key: 'reports.statusLabels.active', fallback: 'Aktiv' },
  completed: { key: 'reports.statusLabels.completed', fallback: 'Abgeschlossen' },
  cancelled: { key: 'reports.statusLabels.cancelled', fallback: 'Storniert' },
  no_show: { key: 'reports.statusLabels.no_show', fallback: 'Nicht erschienen' },
};

const TYPE_COLORS: Record<string, string> = {
  einmalig: '#3B82F6',
  mehrtaegig: '#8B5CF6',
  dauer: '#10B981',
};

const TYPE_LABEL_KEYS: Record<string, { key: string; fallback: string }> = {
  einmalig: { key: 'reports.typeLabels.single', fallback: 'Einmalig' },
  mehrtaegig: { key: 'reports.typeLabels.multiDay', fallback: 'Mehrtägig' },
  dauer: { key: 'reports.typeLabels.permanent', fallback: 'Dauerparkplatz' },
};

function heatColor(value: number, max: number): string {
  if (max === 0 || value === 0) return 'rgba(59,130,246,0.05)';
  const intensity = value / max;
  if (intensity < 0.25) return 'rgba(59,130,246,0.15)';
  if (intensity < 0.5) return 'rgba(59,130,246,0.35)';
  if (intensity < 0.75) return 'rgba(59,130,246,0.6)';
  return 'rgba(59,130,246,0.9)';
}

function formatDuration(hours: number | null): string {
  if (hours === null || hours === 0) return '—';
  if (hours < 1) return `${Math.round(hours * 60)} min`;
  const h = Math.floor(hours);
  const m = Math.round((hours - h) * 60);
  return m > 0 ? `${h} h ${m} min` : `${h} h`;
}

// ── Overview Card ────────────────────────────────────────────────────────────

function StatCard({ icon: IconComp, label, value, sub }: {
  icon: Icon;
  label: string;
  value: string | number;
  sub?: string;
}) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 12 }}
      animate={{ opacity: 1, y: 0 }}
      className="card p-5 flex items-start gap-4"
    >
      <div className="w-11 h-11 rounded-xl bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center shrink-0">
        <IconComp weight="fill" className="w-5 h-5 text-primary-600 dark:text-primary-400" />
      </div>
      <div className="min-w-0">
        <p className="text-sm text-gray-500 dark:text-gray-400 truncate">{label}</p>
        <p className="text-2xl font-bold text-gray-900 dark:text-white mt-0.5">{value}</p>
        {sub && <p className="text-xs text-gray-400 dark:text-gray-500 mt-1">{sub}</p>}
      </div>
    </motion.div>
  );
}

// ── SVG Bar Chart (booking trend) ────────────────────────────────────────────

function BookingTrendChart({ labels, data }: { labels: string[]; data: number[] }) {
  const max = Math.max(...data, 1);
  const barWidth = 100 / labels.length;
  const chartH = 180;
  const padTop = 20;
  const padBottom = 40;
  const usable = chartH - padTop - padBottom;

  return (
    <svg viewBox={`0 0 600 ${chartH}`} className="w-full" preserveAspectRatio="xMidYMid meet">
      {/* grid lines */}
      {[0, 0.25, 0.5, 0.75, 1].map((f) => {
        const y = padTop + usable * (1 - f);
        return (
          <g key={f}>
            <line x1="40" y1={y} x2="590" y2={y} stroke="currentColor" className="text-gray-200 dark:text-gray-700" strokeWidth="0.5" />
            <text x="36" y={y + 3} textAnchor="end" className="fill-gray-400 dark:fill-gray-500" fontSize="9">{Math.round(max * f)}</text>
          </g>
        );
      })}
      {/* bars */}
      {data.map((v, i) => {
        const bw = 550 / labels.length;
        const x = 42 + i * bw;
        const h = (v / max) * usable;
        const y = padTop + usable - h;
        return (
          <g key={i}>
            <rect x={x + bw * 0.15} y={y} width={bw * 0.7} height={h} rx="3" className="fill-primary-500 dark:fill-primary-400" opacity="0.85">
              <title>{labels[i]}: {v}</title>
            </rect>
            {labels.length <= 15 || i % Math.ceil(labels.length / 15) === 0 ? (
              <text x={x + bw / 2} y={chartH - 8} textAnchor="middle" className="fill-gray-400 dark:fill-gray-500" fontSize="8">
                {labels[i].slice(5)}
              </text>
            ) : null}
          </g>
        );
      })}
    </svg>
  );
}

// ── Occupancy Bars ───────────────────────────────────────────────────────────

function OccupancyBars({ total, occupied }: { total: number; occupied: number }) {
  const { t } = useTranslation();
  const pct = total > 0 ? Math.round((occupied / total) * 100) : 0;
  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between text-sm">
        <span className="text-gray-600 dark:text-gray-300 font-medium">{t('reports.total', 'Gesamt')}</span>
        <span className="text-gray-900 dark:text-white font-bold">{occupied} / {total} ({pct}%)</span>
      </div>
      <div className="w-full h-6 bg-gray-100 dark:bg-gray-800 rounded-lg overflow-hidden">
        <motion.div
          initial={{ width: 0 }}
          animate={{ width: `${pct}%` }}
          transition={{ duration: 0.6, ease: 'easeOut' }}
          className={`h-full rounded-lg ${pct > 85 ? 'bg-red-500' : pct > 60 ? 'bg-amber-500' : 'bg-emerald-500'}`}
        />
      </div>
    </div>
  );
}

// ── Status Breakdown ─────────────────────────────────────────────────────────

function StatusBreakdown({ data }: { data: Record<string, number> }) {
  const { t } = useTranslation();
  const total = Object.values(data).reduce((a, b) => a + b, 0);
  if (total === 0) return <p className="text-sm text-gray-400">{t('reports.noData', 'Keine Daten')}</p>;
  const entries = Object.entries(data).sort((a, b) => b[1] - a[1]);

  const getStatusLabel = (status: string) => {
    const cfg = STATUS_LABEL_KEYS[status];
    return cfg ? t(cfg.key, cfg.fallback) : status;
  };

  return (
    <div className="space-y-3">
      {/* stacked bar */}
      <div className="flex h-5 rounded-lg overflow-hidden">
        {entries.map(([status, count]) => (
          <motion.div
            key={status}
            initial={{ width: 0 }}
            animate={{ width: `${(count / total) * 100}%` }}
            transition={{ duration: 0.6, ease: 'easeOut' }}
            style={{ backgroundColor: STATUS_COLORS[status] || '#9CA3AF' }}
            title={`${getStatusLabel(status)}: ${count}`}
          />
        ))}
      </div>
      {/* legend */}
      <div className="flex flex-wrap gap-x-5 gap-y-1.5">
        {entries.map(([status, count]) => (
          <div key={status} className="flex items-center gap-2 text-sm">
            <span className="w-3 h-3 rounded-sm shrink-0" style={{ backgroundColor: STATUS_COLORS[status] || '#9CA3AF' }} />
            <span className="text-gray-600 dark:text-gray-300">{getStatusLabel(status)}</span>
            <span className="font-semibold text-gray-900 dark:text-white">{count}</span>
            <span className="text-gray-400 text-xs">({Math.round((count / total) * 100)}%)</span>
          </div>
        ))}
      </div>
    </div>
  );
}

// ── Type Breakdown ───────────────────────────────────────────────────────────

function TypeBreakdown({ data }: { data: Record<string, number> }) {
  const { t } = useTranslation();
  const total = Object.values(data).reduce((a, b) => a + b, 0);
  if (total === 0) return <p className="text-sm text-gray-400">{t('reports.noData', 'Keine Daten')}</p>;
  const entries = Object.entries(data).sort((a, b) => b[1] - a[1]);

  const getTypeLabel = (type: string) => {
    const cfg = TYPE_LABEL_KEYS[type];
    return cfg ? t(cfg.key, cfg.fallback) : type;
  };

  return (
    <div className="space-y-3">
      {entries.map(([type, count]) => {
        const pct = Math.round((count / total) * 100);
        return (
          <div key={type} className="space-y-1">
            <div className="flex items-center justify-between text-sm">
              <span className="text-gray-600 dark:text-gray-300">{getTypeLabel(type)}</span>
              <span className="font-semibold text-gray-900 dark:text-white">{count} <span className="text-gray-400 font-normal">({pct}%)</span></span>
            </div>
            <div className="w-full h-3 bg-gray-100 dark:bg-gray-800 rounded-full overflow-hidden">
              <motion.div
                initial={{ width: 0 }}
                animate={{ width: `${pct}%` }}
                transition={{ duration: 0.6, ease: 'easeOut' }}
                className="h-full rounded-full"
                style={{ backgroundColor: TYPE_COLORS[type] || '#6B7280' }}
              />
            </div>
          </div>
        );
      })}
    </div>
  );
}

// ── Heatmap Grid ─────────────────────────────────────────────────────────────

function HeatmapGrid({ entries }: { entries: HeatmapEntry[] }) {
  const { t } = useTranslation();
  const dayLabels = [
    t('reports.days.sun', 'So'),
    t('reports.days.mon', 'Mo'),
    t('reports.days.tue', 'Di'),
    t('reports.days.wed', 'Mi'),
    t('reports.days.thu', 'Do'),
    t('reports.days.fri', 'Fr'),
    t('reports.days.sat', 'Sa'),
  ];

  const grid = useMemo(() => {
    const g: number[][] = Array.from({ length: 7 }, () => Array(24).fill(0));
    let max = 0;
    for (const e of entries) {
      g[e.day][e.hour] = e.count;
      if (e.count > max) max = e.count;
    }
    return { data: g, max };
  }, [entries]);

  const hours = Array.from({ length: 24 }, (_, i) => i);

  return (
    <div className="overflow-x-auto">
      <div className="min-w-[640px]">
        {/* hour labels */}
        <div className="flex ml-10 mb-1">
          {hours.map((h) => (
            <div key={h} className="flex-1 text-center text-[10px] text-gray-400 dark:text-gray-500">
              {h % 2 === 0 ? `${String(h).padStart(2, '0')}` : ''}
            </div>
          ))}
        </div>
        {/* rows */}
        {grid.data.map((row, dayIdx) => (
          <div key={dayIdx} className="flex items-center gap-1 mb-0.5">
            <span className="w-8 text-right text-xs text-gray-500 dark:text-gray-400 font-medium shrink-0">
              {dayLabels[dayIdx]}
            </span>
            <div className="flex flex-1 gap-[2px]">
              {row.map((count, hourIdx) => (
                <div
                  key={hourIdx}
                  className="flex-1 aspect-square rounded-sm transition-colors cursor-default"
                  style={{ backgroundColor: heatColor(count, grid.max) }}
                  title={`${dayLabels[dayIdx]} ${String(hourIdx).padStart(2, '0')}:00 — ${count} ${t('reports.bookings', 'Buchungen')}`}
                />
              ))}
            </div>
          </div>
        ))}
        {/* legend */}
        <div className="flex items-center justify-end gap-1.5 mt-3 mr-1">
          <span className="text-[10px] text-gray-400">{t('reports.less', 'Weniger')}</span>
          {[0.05, 0.15, 0.35, 0.6, 0.9].map((o, i) => (
            <div key={i} className="w-3 h-3 rounded-sm" style={{ backgroundColor: `rgba(59,130,246,${o})` }} />
          ))}
          <span className="text-[10px] text-gray-400">{t('reports.more', 'Mehr')}</span>
        </div>
      </div>
    </div>
  );
}

// ── Main Page ────────────────────────────────────────────────────────────────

export function AdminReportsPage() {
  const { t } = useTranslation();
  const [loading, setLoading] = useState(true);
  const [reports, setReports] = useState<AdminReportData | null>(null);
  const [charts, setCharts] = useState<DashboardChartData | null>(null);
  const [heatmap, setHeatmap] = useState<HeatmapEntry[]>([]);

  useEffect(() => {
    loadData();
  }, []);

  async function loadData() {
    setLoading(true);
    try {
      const [reportsRes, chartsRes, heatmapRes] = await Promise.all([
        api.getAdminReports(30),
        api.getDashboardCharts(30),
        api.getAdminHeatmap(),
      ]);
      if (reportsRes.success && reportsRes.data) setReports(reportsRes.data);
      if (chartsRes.success && chartsRes.data) setCharts(chartsRes.data);
      if (heatmapRes.success && heatmapRes.data) setHeatmap(heatmapRes.data);
    } finally {
      setLoading(false);
    }
  }

  // Derived stats
  const busiestDay = useMemo(() => {
    if (!reports?.by_day || Object.keys(reports.by_day).length === 0) return '—';
    const sorted = Object.entries(reports.by_day).sort((a, b) => b[1] - a[1]);
    const date = sorted[0][0];
    // Format as DD.MM.
    const [, m, d] = date.split('-');
    return `${d}.${m}.`;
  }, [reports]);

  const peakHour = useMemo(() => {
    if (heatmap.length === 0) return '—';
    const hourTotals = new Map<number, number>();
    for (const e of heatmap) {
      hourTotals.set(e.hour, (hourTotals.get(e.hour) || 0) + e.count);
    }
    let maxH = 0, maxC = 0;
    hourTotals.forEach((c, h) => { if (c > maxC) { maxC = c; maxH = h; } });
    return `${String(maxH).padStart(2, '0')}:00`;
  }, [heatmap]);

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <SpinnerGap weight="bold" className="w-8 h-8 text-primary-600 animate-spin" />
      </div>
    );
  }

  return (
    <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="space-y-8">
      {/* Header */}
      <div className="flex items-center gap-3">
        <ChartBar weight="fill" className="w-6 h-6 text-primary-600" />
        <h2 className="text-xl font-semibold text-gray-900 dark:text-white">
          {t('admin.reports.title', 'Berichte & Analysen')}
        </h2>
        <span className="text-sm text-gray-400 dark:text-gray-500 ml-auto">
          {t('admin.reports.period', 'Letzte 30 Tage')}
        </span>
      </div>

      {/* Overview Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard
          icon={CalendarBlank}
          label={t('admin.reports.totalBookings', 'Buchungen gesamt')}
          value={reports?.total_bookings ?? 0}
          sub={t('admin.reports.last30', 'Letzte 30 Tage')}
        />
        <StatCard
          icon={Clock}
          label={t('admin.reports.avgDuration', 'Durchschn. Dauer')}
          value={formatDuration(reports?.avg_duration_hours ?? null)}
        />
        <StatCard
          icon={TrendUp}
          label={t('admin.reports.busiestDay', 'Stärkster Tag')}
          value={busiestDay}
        />
        <StatCard
          icon={Fire}
          label={t('admin.reports.peakHour', 'Stoßzeit')}
          value={peakHour}
        />
      </div>

      {/* Charts Row */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Booking Trend */}
        <div className="card p-6">
          <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
            <TrendUp weight="fill" className="w-5 h-5 text-primary-600" />
            {t('admin.reports.bookingTrend', 'Buchungsverlauf')}
          </h3>
          {charts?.booking_trend_30d && charts.booking_trend_30d.length > 0 ? (
            <BookingTrendChart
              labels={charts.booking_trend_30d.map((d) => d.date)}
              data={charts.booking_trend_30d.map((d) => d.count)}
            />
          ) : reports?.by_day && Object.keys(reports.by_day).length > 0 ? (
            <BookingTrendChart
              labels={Object.keys(reports.by_day)}
              data={Object.values(reports.by_day)}
            />
          ) : (
            <p className="text-sm text-gray-400 text-center py-8">{t('admin.reports.noData', 'Keine Daten vorhanden')}</p>
          )}
        </div>

        {/* Current Occupancy */}
        <div className="card p-6">
          <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
            <ChartBar weight="fill" className="w-5 h-5 text-primary-600" />
            {t('admin.reports.occupancy', 'Aktuelle Belegung')}
          </h3>
          {charts ? (
            <OccupancyBars
              total={charts.occupancy_trend?.length ? charts.occupancy_trend.reduce((a, b) => a + b.rate, 0) : (charts as any).occupancy_now?.total ?? 0}
              occupied={charts.occupancy_trend?.length ? Math.round(charts.occupancy_trend.reduce((a, b) => a + b.rate, 0) / charts.occupancy_trend.length) : (charts as any).occupancy_now?.occupied ?? 0}
            />
          ) : (
            <p className="text-sm text-gray-400 text-center py-8">{t('admin.reports.noData', 'Keine Daten vorhanden')}</p>
          )}
        </div>
      </div>

      {/* Status & Type Breakdown */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div className="card p-6">
          <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">
            {t('admin.reports.statusBreakdown', 'Status-Verteilung')}
          </h3>
          <StatusBreakdown data={reports?.by_status ?? {}} />
        </div>

        <div className="card p-6">
          <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">
            {t('admin.reports.typeBreakdown', 'Buchungstypen')}
          </h3>
          <TypeBreakdown data={reports?.by_booking_type ?? {}} />
        </div>
      </div>

      {/* Heatmap */}
      <div className="card p-6">
        <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
          <Fire weight="fill" className="w-5 h-5 text-primary-600" />
          {t('admin.reports.heatmap', 'Buchungs-Heatmap')}
        </h3>
        <p className="text-sm text-gray-500 dark:text-gray-400 mb-4">
          {t('admin.reports.heatmapDesc', 'Buchungshäufigkeit nach Wochentag und Uhrzeit')}
        </p>
        {heatmap.length > 0 ? (
          <HeatmapGrid entries={heatmap} />
        ) : (
          <p className="text-sm text-gray-400 text-center py-8">{t('admin.reports.noData', 'Keine Daten vorhanden')}</p>
        )}
      </div>
    </motion.div>
  );
}
