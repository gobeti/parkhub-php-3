import { useState, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import {
  WebhooksLogo, Plus, PencilSimple, Trash, SpinnerGap, Check, X,
  Eye, EyeSlash, Copy, CheckCircle, XCircle, Lightning,
} from '@phosphor-icons/react';
import { api, Webhook } from '../api/client';
import { useTranslation } from 'react-i18next';
import toast from 'react-hot-toast';

const WEBHOOK_EVENTS = [
  'booking.created', 'booking.updated', 'booking.cancelled', 'booking.checked_in',
  'user.created', 'user.updated', 'user.deleted',
  'lot.created', 'lot.updated', 'lot.deleted',
  'slot.updated',
  'absence.created', 'absence.deleted',
  'guest_booking.created', 'guest_booking.cancelled',
];

interface WebhookForm {
  url: string;
  events: string[];
  secret: string;
  active: boolean;
}

const emptyForm: WebhookForm = {
  url: '',
  events: [],
  secret: '',
  active: true,
};

function EventBadge({ event }: { event: string }) {
  const [domain, action] = event.split('.');
  const colors: Record<string, string> = {
    booking: 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300',
    user: 'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300',
    lot: 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300',
    slot: 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300',
    absence: 'bg-teal-100 dark:bg-teal-900/30 text-teal-700 dark:text-teal-300',
    guest_booking: 'bg-pink-100 dark:bg-pink-900/30 text-pink-700 dark:text-pink-300',
  };
  return (
    <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium ${colors[domain] || 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400'}`}>
      {domain}.{action}
    </span>
  );
}

export function AdminWebhooksPage() {
  const { t } = useTranslation();
  const [webhooks, setWebhooks] = useState<Webhook[]>([]);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [editId, setEditId] = useState<string | null>(null);
  const [form, setForm] = useState<WebhookForm>(emptyForm);
  const [saving, setSaving] = useState(false);
  const [showSecrets, setShowSecrets] = useState<Record<string, boolean>>({});

  const load = async () => {
    setLoading(true);
    const res = await api.getWebhooks();
    if (res.data) {
      // getWebhooks returns the array directly (not wrapped)
      setWebhooks(Array.isArray(res.data) ? res.data : []);
    }
    setLoading(false);
  };

  useEffect(() => { load(); }, []);

  const openCreate = () => {
    setEditId(null);
    setForm(emptyForm);
    setShowForm(true);
  };

  const openEdit = (wh: Webhook) => {
    setEditId(wh.id);
    setForm({
      url: wh.url,
      events: wh.events || [],
      secret: wh.secret || '',
      active: wh.active,
    });
    setShowForm(true);
  };

  const toggleEvent = (event: string) => {
    setForm(prev => ({
      ...prev,
      events: prev.events.includes(event)
        ? prev.events.filter(e => e !== event)
        : [...prev.events, event],
    }));
  };

  const selectAllEvents = () => {
    setForm(prev => ({ ...prev, events: [...WEBHOOK_EVENTS] }));
  };

  const clearAllEvents = () => {
    setForm(prev => ({ ...prev, events: [] }));
  };

  const save = async () => {
    if (!form.url.trim()) {
      toast.error('URL is required');
      return;
    }
    setSaving(true);
    try {
      if (editId) {
        await api.updateWebhook(editId, {
          url: form.url,
          events: form.events.length > 0 ? form.events : undefined,
          secret: form.secret || undefined,
          active: form.active,
        });
        toast.success(t('webhooks.updated', 'Webhook aktualisiert'));
      } else {
        await api.createWebhook({
          url: form.url,
          events: form.events.length > 0 ? form.events : undefined,
          secret: form.secret || undefined,
          active: form.active,
        });
        toast.success(t('webhooks.created', 'Webhook erstellt'));
      }
      setShowForm(false);
      load();
    } catch {
      toast.error(t('webhooks.error', 'Fehler beim Speichern'));
    } finally {
      setSaving(false);
    }
  };

  const remove = async (id: string) => {
    if (!confirm(t('webhooks.confirmDelete', 'Webhook wirklich löschen?'))) return;
    await api.deleteWebhook(id);
    toast.success(t('webhooks.deleted', 'Webhook gelöscht'));
    load();
  };

  const toggleActive = async (wh: Webhook) => {
    await api.updateWebhook(wh.id, { active: !wh.active });
    load();
  };

  const copySecret = (secret: string) => {
    navigator.clipboard.writeText(secret);
    toast.success('Copied');
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center py-20">
        <SpinnerGap className="w-8 h-8 animate-spin text-primary-500" />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
            <Lightning weight="fill" className="w-6 h-6 text-primary-500" />
            {t('webhooks.title', 'Webhooks')}
          </h2>
          <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
            {t('webhooks.description', 'HTTP-Callbacks bei Ereignissen auslösen — z.B. für Slack, Zapier oder eigene Systeme.')}
          </p>
        </div>
        <button onClick={openCreate} className="flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors text-sm font-medium">
          <Plus weight="bold" className="w-4 h-4" />
          {t('webhooks.add', 'Webhook hinzufügen')}
        </button>
      </div>

      {/* Webhook list */}
      {webhooks.length === 0 && !showForm ? (
        <div className="text-center py-16 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800">
          <Lightning className="w-12 h-12 text-gray-300 dark:text-gray-600 mx-auto mb-3" />
          <p className="text-gray-500 dark:text-gray-400">{t('webhooks.empty', 'Noch keine Webhooks konfiguriert.')}</p>
          <button onClick={openCreate} className="mt-4 text-primary-600 dark:text-primary-400 text-sm font-medium hover:underline">
            {t('webhooks.addFirst', 'Ersten Webhook erstellen')}
          </button>
        </div>
      ) : (
        <div className="space-y-3">
          {webhooks.map(wh => (
            <motion.div
              key={wh.id}
              initial={{ opacity: 0, y: 8 }}
              animate={{ opacity: 1, y: 0 }}
              className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-5"
            >
              <div className="flex items-start justify-between gap-4">
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-3 mb-2">
                    <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium ${wh.active ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300' : 'bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400'}`}>
                      {wh.active ? <CheckCircle weight="fill" className="w-3.5 h-3.5" /> : <XCircle weight="fill" className="w-3.5 h-3.5" />}
                      {wh.active ? 'Active' : 'Inactive'}
                    </span>
                    <code className="text-sm text-gray-900 dark:text-gray-100 truncate font-mono">{wh.url}</code>
                  </div>

                  {/* Events */}
                  <div className="flex flex-wrap gap-1.5 mb-2">
                    {(wh.events && wh.events.length > 0) ? (
                      wh.events.map(e => <EventBadge key={e} event={e} />)
                    ) : (
                      <span className="text-xs text-gray-400 italic">{t('webhooks.allEvents', 'Alle Ereignisse')}</span>
                    )}
                  </div>

                  {/* Secret */}
                  {wh.secret && (
                    <div className="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                      <span>Secret:</span>
                      <code className="font-mono">
                        {showSecrets[wh.id] ? wh.secret : '••••••••••••'}
                      </code>
                      <button onClick={() => setShowSecrets(s => ({ ...s, [wh.id]: !s[wh.id] }))} className="p-0.5 hover:text-gray-700 dark:hover:text-gray-300">
                        {showSecrets[wh.id] ? <EyeSlash className="w-3.5 h-3.5" /> : <Eye className="w-3.5 h-3.5" />}
                      </button>
                      <button onClick={() => copySecret(wh.secret!)} className="p-0.5 hover:text-gray-700 dark:hover:text-gray-300">
                        <Copy className="w-3.5 h-3.5" />
                      </button>
                    </div>
                  )}
                </div>

                {/* Actions */}
                <div className="flex items-center gap-1">
                  <button onClick={() => toggleActive(wh)} className={`p-2 rounded-lg transition-colors ${wh.active ? 'hover:bg-amber-50 dark:hover:bg-amber-900/20 text-amber-600' : 'hover:bg-green-50 dark:hover:bg-green-900/20 text-green-600'}`} title={wh.active ? 'Deactivate' : 'Activate'}>
                    {wh.active ? <XCircle className="w-5 h-5" /> : <CheckCircle className="w-5 h-5" />}
                  </button>
                  <button onClick={() => openEdit(wh)} className="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-500 transition-colors">
                    <PencilSimple className="w-5 h-5" />
                  </button>
                  <button onClick={() => remove(wh.id)} className="p-2 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 text-red-500 transition-colors">
                    <Trash className="w-5 h-5" />
                  </button>
                </div>
              </div>
            </motion.div>
          ))}
        </div>
      )}

      {/* Create/Edit Modal */}
      <AnimatePresence>
        {showForm && (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4"
            onClick={e => { if (e.target === e.currentTarget) setShowForm(false); }}
          >
            <motion.div
              initial={{ opacity: 0, scale: 0.95, y: 20 }}
              animate={{ opacity: 1, scale: 1, y: 0 }}
              exit={{ opacity: 0, scale: 0.95, y: 20 }}
              className="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-lg max-h-[85vh] overflow-y-auto"
            >
              <div className="p-6 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between">
                <h3 className="text-lg font-bold text-gray-900 dark:text-white">
                  {editId ? t('webhooks.edit', 'Webhook bearbeiten') : t('webhooks.create', 'Neuer Webhook')}
                </h3>
                <button onClick={() => setShowForm(false)} className="p-1 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg">
                  <X className="w-5 h-5" />
                </button>
              </div>

              <div className="p-6 space-y-5">
                {/* URL */}
                <div>
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                    Payload URL *
                  </label>
                  <input
                    type="url"
                    value={form.url}
                    onChange={e => setForm(f => ({ ...f, url: e.target.value }))}
                    placeholder="https://example.com/webhook"
                    className="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                  />
                  <p className="text-xs text-gray-400 mt-1">{t('webhooks.urlHint', 'Muss eine öffentlich erreichbare HTTPS-URL sein (keine internen IPs).')}</p>
                </div>

                {/* Secret */}
                <div>
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                    Secret
                  </label>
                  <input
                    type="text"
                    value={form.secret}
                    onChange={e => setForm(f => ({ ...f, secret: e.target.value }))}
                    placeholder="whsec_..."
                    className="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-sm font-mono focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                  />
                  <p className="text-xs text-gray-400 mt-1">{t('webhooks.secretHint', 'Optional. Wird als X-Webhook-Secret Header mitgesendet.')}</p>
                </div>

                {/* Active toggle */}
                <div className="flex items-center gap-3">
                  <button
                    onClick={() => setForm(f => ({ ...f, active: !f.active }))}
                    className={`relative w-11 h-6 rounded-full transition-colors ${form.active ? 'bg-primary-600' : 'bg-gray-300 dark:bg-gray-700'}`}
                  >
                    <span className={`absolute top-0.5 w-5 h-5 rounded-full bg-white shadow transition-transform ${form.active ? 'left-[22px]' : 'left-0.5'}`} />
                  </button>
                  <span className="text-sm text-gray-700 dark:text-gray-300">{form.active ? 'Aktiv' : 'Inaktiv'}</span>
                </div>

                {/* Events */}
                <div>
                  <div className="flex items-center justify-between mb-2">
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                      {t('webhooks.events', 'Ereignisse')}
                    </label>
                    <div className="flex gap-2 text-xs">
                      <button onClick={selectAllEvents} className="text-primary-600 dark:text-primary-400 hover:underline">Alle</button>
                      <span className="text-gray-300">|</span>
                      <button onClick={clearAllEvents} className="text-gray-500 hover:underline">Keine</button>
                    </div>
                  </div>
                  <p className="text-xs text-gray-400 mb-3">{t('webhooks.eventsHint', 'Leer = alle Ereignisse. Wähle spezifische Events für gezielte Benachrichtigungen.')}</p>
                  <div className="grid grid-cols-2 gap-2">
                    {WEBHOOK_EVENTS.map(event => (
                      <label key={event} className="flex items-center gap-2 text-sm cursor-pointer group">
                        <input
                          type="checkbox"
                          checked={form.events.includes(event)}
                          onChange={() => toggleEvent(event)}
                          className="rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500"
                        />
                        <span className="text-gray-700 dark:text-gray-300 group-hover:text-gray-900 dark:group-hover:text-white transition-colors">
                          {event}
                        </span>
                      </label>
                    ))}
                  </div>
                </div>
              </div>

              <div className="p-6 border-t border-gray-200 dark:border-gray-800 flex justify-end gap-3">
                <button onClick={() => setShowForm(false)} className="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors">
                  {t('common.cancel', 'Abbrechen')}
                </button>
                <button onClick={save} disabled={saving || !form.url.trim()} className="flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors text-sm font-medium disabled:opacity-50">
                  {saving ? <SpinnerGap className="w-4 h-4 animate-spin" /> : <Check weight="bold" className="w-4 h-4" />}
                  {editId ? t('common.save', 'Speichern') : t('webhooks.create', 'Erstellen')}
                </button>
              </div>
            </motion.div>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  );
}
