import { useState, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import {
  Megaphone, Plus, PencilSimple, Trash, SpinnerGap, Check, X,
  Info, Warning, WarningCircle, CheckCircle, Clock,
} from '@phosphor-icons/react';
import { api, Announcement } from '../api/client';
import { useTranslation } from 'react-i18next';
import toast from 'react-hot-toast';

type Severity = 'info' | 'warning' | 'error' | 'success';

interface AnnouncementForm {
  title: string;
  message: string;
  severity: Severity;
  active: boolean;
  expires_at: string;
}

const emptForm: AnnouncementForm = {
  title: '',
  message: '',
  severity: 'info',
  active: true,
  expires_at: '',
};

const severityConfig: Record<Severity, { label: string; labelDe: string; color: string; bg: string; icon: typeof Info }> = {
  info:    { label: 'Info',    labelDe: 'Info',    color: 'text-blue-600 dark:text-blue-400',   bg: 'bg-blue-100 dark:bg-blue-900/30',   icon: Info },
  warning: { label: 'Warning', labelDe: 'Warnung', color: 'text-amber-600 dark:text-amber-400', bg: 'bg-amber-100 dark:bg-amber-900/30', icon: Warning },
  error:   { label: 'Error',   labelDe: 'Fehler',  color: 'text-red-600 dark:text-red-400',     bg: 'bg-red-100 dark:bg-red-900/30',     icon: WarningCircle },
  success: { label: 'Success', labelDe: 'Erfolg',  color: 'text-green-600 dark:text-green-400', bg: 'bg-green-100 dark:bg-green-900/30', icon: CheckCircle },
};

function SeverityBadge({ severity }: { severity: Severity }) {
  const cfg = severityConfig[severity] || severityConfig.info;
  const Icon = cfg.icon;
  return (
    <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium ${cfg.bg} ${cfg.color}`}>
      <Icon weight="fill" className="w-3.5 h-3.5" />
      {cfg.label}
    </span>
  );
}

function StatusBadge({ active, expiresAt }: { active: boolean; expiresAt?: string }) {
  const isExpired = expiresAt && new Date(expiresAt) < new Date();
  if (!active || isExpired) {
    return (
      <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400">
        <Clock weight="fill" className="w-3.5 h-3.5" />
        {isExpired ? 'Expired' : 'Inactive'}
      </span>
    );
  }
  return (
    <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400">
      <CheckCircle weight="fill" className="w-3.5 h-3.5" />
      Active
    </span>
  );
}

export function AdminAnnouncementsPage() {
  const { t } = useTranslation();
  const [announcements, setAnnouncements] = useState<Announcement[]>([]);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [form, setForm] = useState<AnnouncementForm>({ ...emptForm });
  const [saving, setSaving] = useState(false);
  const [deletingId, setDeletingId] = useState<string | null>(null);

  useEffect(() => {
    loadAnnouncements();
  }, []);

  async function loadAnnouncements() {
    try {
      const res = await api.getAdminAnnouncements();
      if (res.success && res.data) {
        setAnnouncements(res.data);
      }
    } finally {
      setLoading(false);
    }
  }

  function openCreate() {
    setEditingId(null);
    setForm({ ...emptForm });
    setShowForm(true);
  }

  function openEdit(a: Announcement) {
    setEditingId(a.id);
    setForm({
      title: a.title,
      message: a.message,
      severity: a.severity as Severity,
      active: a.active,
      expires_at: a.expires_at ? a.expires_at.slice(0, 16) : '',
    });
    setShowForm(true);
  }

  function closeForm() {
    setShowForm(false);
    setEditingId(null);
    setForm({ ...emptForm });
  }

  async function handleSave() {
    if (!form.title.trim() || !form.message.trim()) {
      toast.error(t('admin.announcements.validationError', 'Title and message are required.'));
      return;
    }
    setSaving(true);
    try {
      const payload = {
        title: form.title.trim(),
        message: form.message.trim(),
        severity: form.severity,
        active: form.active,
        expires_at: form.expires_at || undefined,
      };
      let res;
      if (editingId) {
        res = await api.updateAnnouncement(editingId, payload);
      } else {
        res = await api.createAnnouncement(payload);
      }
      if (res.success) {
        toast.success(editingId
          ? t('admin.announcements.updated', 'Announcement updated.')
          : t('admin.announcements.created', 'Announcement created.'));
        closeForm();
        await loadAnnouncements();
      } else {
        toast.error(res.error?.message || t('admin.announcements.saveFailed', 'Failed to save.'));
      }
    } finally {
      setSaving(false);
    }
  }

  async function handleDelete(id: string) {
    if (!confirm(t('admin.announcements.confirmDelete', 'Delete this announcement?'))) return;
    setDeletingId(id);
    try {
      const res = await api.deleteAnnouncement(id);
      if (res.success) {
        setAnnouncements(prev => prev.filter(a => a.id !== id));
        toast.success(t('admin.announcements.deleted', 'Announcement deleted.'));
        if (editingId === id) closeForm();
      } else {
        toast.error(res.error?.message || t('admin.announcements.deleteFailed', 'Failed to delete.'));
      }
    } finally {
      setDeletingId(null);
    }
  }

  if (loading) {
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
          <Megaphone weight="fill" className="w-6 h-6 text-primary-600" />
          <h2 className="text-xl font-semibold text-gray-900 dark:text-white">
            {t('admin.announcements.title', 'Announcements')}
          </h2>
        </div>
        <button onClick={openCreate} className="btn btn-primary">
          <Plus weight="bold" className="w-4 h-4" />
          {t('admin.announcements.new', 'New Announcement')}
        </button>
      </div>

      {/* Form (Create / Edit) */}
      <AnimatePresence>
        {showForm && (
          <motion.div
            initial={{ opacity: 0, height: 0 }}
            animate={{ opacity: 1, height: 'auto' }}
            exit={{ opacity: 0, height: 0 }}
            className="overflow-hidden"
          >
            <div className="card p-6 space-y-5">
              <div className="flex items-center justify-between">
                <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                  {editingId
                    ? t('admin.announcements.editTitle', 'Edit Announcement')
                    : t('admin.announcements.createTitle', 'New Announcement')}
                </h3>
                <button onClick={closeForm} className="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                  <X weight="bold" className="w-5 h-5 text-gray-400" />
                </button>
              </div>

              {/* Title */}
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  {t('admin.announcements.labelTitle', 'Title')}
                </label>
                <input
                  type="text"
                  value={form.title}
                  onChange={e => setForm(prev => ({ ...prev, title: e.target.value }))}
                  className="input"
                  placeholder={t('admin.announcements.titlePlaceholder', 'Announcement title...')}
                />
              </div>

              {/* Message */}
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  {t('admin.announcements.labelMessage', 'Message')}
                </label>
                <textarea
                  value={form.message}
                  onChange={e => setForm(prev => ({ ...prev, message: e.target.value }))}
                  className="input h-28 resize-y"
                  placeholder={t('admin.announcements.messagePlaceholder', 'Announcement message...')}
                />
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-3 gap-5">
                {/* Severity */}
                <div>
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    {t('admin.announcements.labelSeverity', 'Severity')}
                  </label>
                  <div className="flex flex-wrap gap-2">
                    {(Object.keys(severityConfig) as Severity[]).map(sev => {
                      const cfg = severityConfig[sev];
                      const Icon = cfg.icon;
                      const isSelected = form.severity === sev;
                      return (
                        <button
                          key={sev}
                          type="button"
                          onClick={() => setForm(prev => ({ ...prev, severity: sev }))}
                          className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium border-2 transition-all ${
                            isSelected
                              ? `${cfg.bg} ${cfg.color} border-current`
                              : 'border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 hover:border-gray-300 dark:hover:border-gray-600'
                          }`}
                        >
                          <Icon weight={isSelected ? 'fill' : 'regular'} className="w-4 h-4" />
                          {cfg.label}
                        </button>
                      );
                    })}
                  </div>
                </div>

                {/* Active toggle */}
                <div>
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    {t('admin.announcements.labelActive', 'Status')}
                  </label>
                  <button
                    type="button"
                    onClick={() => setForm(prev => ({ ...prev, active: !prev.active }))}
                    className={`flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium border-2 transition-all ${
                      form.active
                        ? 'bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400 border-green-300 dark:border-green-700'
                        : 'bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 border-gray-200 dark:border-gray-700'
                    }`}
                  >
                    {form.active ? (
                      <><CheckCircle weight="fill" className="w-4 h-4" />{t('admin.announcements.active', 'Active')}</>
                    ) : (
                      <><Clock weight="fill" className="w-4 h-4" />{t('admin.announcements.inactive', 'Inactive')}</>
                    )}
                  </button>
                </div>

                {/* Expires at */}
                <div>
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    {t('admin.announcements.labelExpiresAt', 'Expires at (optional)')}
                  </label>
                  <input
                    type="datetime-local"
                    value={form.expires_at}
                    onChange={e => setForm(prev => ({ ...prev, expires_at: e.target.value }))}
                    className="input"
                  />
                </div>
              </div>

              {/* Actions */}
              <div className="flex gap-3 pt-2">
                <button onClick={handleSave} disabled={saving} className="btn btn-primary">
                  {saving
                    ? <SpinnerGap weight="bold" className="w-4 h-4 animate-spin" />
                    : <Check weight="bold" className="w-4 h-4" />}
                  {editingId
                    ? t('admin.announcements.save', 'Save')
                    : t('admin.announcements.create', 'Create')}
                </button>
                <button onClick={closeForm} className="btn btn-secondary">
                  {t('common.cancel', 'Cancel')}
                </button>
              </div>
            </div>
          </motion.div>
        )}
      </AnimatePresence>

      {/* Announcements List */}
      {announcements.length === 0 && !showForm ? (
        <div className="p-12 text-center">
          <Megaphone weight="light" className="w-16 h-16 text-gray-300 dark:text-gray-700 mx-auto mb-4" />
          <p className="text-gray-500 dark:text-gray-400">
            {t('admin.announcements.empty', 'No announcements yet.')}
          </p>
        </div>
      ) : (
        <div className="space-y-3">
          {announcements.map((a, i) => (
            <motion.div
              key={a.id}
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: i * 0.04 }}
              className="card-hover p-5"
            >
              <div className="flex items-start justify-between gap-4">
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-3 mb-2">
                    <h3 className="text-base font-semibold text-gray-900 dark:text-white truncate">
                      {a.title}
                    </h3>
                    <SeverityBadge severity={a.severity as Severity} />
                    <StatusBadge active={a.active} expiresAt={a.expires_at} />
                  </div>
                  <p className="text-sm text-gray-600 dark:text-gray-400 line-clamp-2 mb-2">
                    {a.message}
                  </p>
                  <div className="flex items-center gap-4 text-xs text-gray-400 dark:text-gray-500">
                    <span>
                      {t('admin.announcements.createdAt', 'Created')}: {new Date(a.created_at).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })}
                    </span>
                    {a.expires_at && (
                      <span>
                        {t('admin.announcements.expiresAt', 'Expires')}: {new Date(a.expires_at).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })}
                      </span>
                    )}
                  </div>
                </div>

                <div className="flex items-center gap-1.5 shrink-0">
                  <button
                    onClick={() => openEdit(a)}
                    className="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors text-gray-400 hover:text-primary-600"
                    title={t('admin.announcements.edit', 'Edit')}
                  >
                    <PencilSimple weight="bold" className="w-4.5 h-4.5" />
                  </button>
                  <button
                    onClick={() => handleDelete(a.id)}
                    disabled={deletingId === a.id}
                    className="p-2 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors text-gray-400 hover:text-red-600 disabled:opacity-50"
                    title={t('admin.announcements.delete', 'Delete')}
                  >
                    {deletingId === a.id
                      ? <SpinnerGap weight="bold" className="w-4.5 h-4.5 animate-spin" />
                      : <Trash weight="bold" className="w-4.5 h-4.5" />}
                  </button>
                </div>
              </div>
            </motion.div>
          ))}
        </div>
      )}
    </motion.div>
  );
}
