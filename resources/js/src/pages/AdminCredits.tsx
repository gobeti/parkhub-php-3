import { useState, useEffect, useCallback } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import {
  Coins, SpinnerGap, MagnifyingGlass, ArrowsClockwise, CaretDown, CaretUp,
  ArrowUp, ArrowDown, Equals, Clock, Users as UsersIcon, WarningCircle,
} from '@phosphor-icons/react';
import { api, User, CreditTransaction } from '../api/client';
import toast from 'react-hot-toast';

type SortDir = 'asc' | 'desc';

interface UserWithCredits extends User {
  credits_balance: number;
  credits_monthly_quota: number;
  credits_last_refilled?: string;
  is_active?: boolean;
}

interface OverviewStats {
  totalCredits: number;
  zeroBalanceUsers: number;
  lastRefillDate: string | null;
  totalUsers: number;
}

export function AdminCreditsPage() {
  // ── State ──
  const [users, setUsers] = useState<UserWithCredits[]>([]);
  const [transactions, setTransactions] = useState<CreditTransaction[]>([]);
  const [loading, setLoading] = useState(true);
  const [txLoading, setTxLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [sortDir, setSortDir] = useState<SortDir>('asc');
  const [expandedUserId, setExpandedUserId] = useState<string | null>(null);
  const [grantAmount, setGrantAmount] = useState('');
  const [grantDescription, setGrantDescription] = useState('');
  const [granting, setGranting] = useState(false);
  const [refilling, setRefilling] = useState(false);
  const [showRefillConfirm, setShowRefillConfirm] = useState(false);

  // ── Data fetching ──
  const loadUsers = useCallback(async () => {
    try {
      const res = await api.getAdminUsers();
      if (res.success && res.data) {
        setUsers(res.data as UserWithCredits[]);
      }
    } catch {
      toast.error('Benutzerdaten konnten nicht geladen werden');
    } finally {
      setLoading(false);
    }
  }, []);

  const loadTransactions = useCallback(async () => {
    setTxLoading(true);
    try {
      const res = await api.adminGetCreditTransactions();
      if (res.success && res.data) {
        setTransactions(res.data);
      }
    } catch {
      toast.error('Transaktionen konnten nicht geladen werden');
    } finally {
      setTxLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadUsers();
    void loadTransactions();
  }, [loadUsers, loadTransactions]);

  // ── Overview stats ──
  const stats: OverviewStats = {
    totalCredits: users.reduce((sum, u) => sum + (u.credits_balance ?? 0), 0),
    zeroBalanceUsers: users.filter(u => (u.credits_balance ?? 0) === 0 && u.role === 'user').length,
    lastRefillDate: users
      .map(u => u.credits_last_refilled)
      .filter(Boolean)
      .sort()
      .reverse()[0] ?? null,
    totalUsers: users.filter(u => u.role === 'user').length,
  };

  // ── Filter & sort ──
  const filtered = users
    .filter(u => {
      if (!search) return true;
      const q = search.toLowerCase();
      return u.name.toLowerCase().includes(q) || u.username.toLowerCase().includes(q);
    })
    .sort((a, b) => {
      const diff = (a.credits_balance ?? 0) - (b.credits_balance ?? 0);
      return sortDir === 'asc' ? diff : -diff;
    });

  // ── Grant credits ──
  async function handleGrant(userId: string) {
    const amount = parseInt(grantAmount);
    if (!amount || amount < 1) {
      toast.error('Bitte einen gueltigen Betrag eingeben');
      return;
    }
    setGranting(true);
    try {
      const res = await api.adminGrantCredits(userId, amount, grantDescription || undefined);
      if (res.success) {
        toast.success(`${amount} Kontingent vergeben`);
        setGrantAmount('');
        setGrantDescription('');
        setExpandedUserId(null);
        await loadUsers();
        await loadTransactions();
      } else {
        toast.error(res.error?.message || 'Fehler beim Vergeben');
      }
    } catch {
      toast.error('Fehler beim Vergeben');
    } finally {
      setGranting(false);
    }
  }

  // ── Refill all ──
  async function handleRefillAll() {
    setRefilling(true);
    try {
      const res = await api.adminRefillAllCredits();
      if (res.success && res.data) {
        toast.success(`Kontingent fuer ${res.data.users_refilled} Benutzer aufgefuellt`);
        setShowRefillConfirm(false);
        await loadUsers();
        await loadTransactions();
      } else {
        toast.error(res.error?.message || 'Fehler beim Auffuellen');
      }
    } catch {
      toast.error('Fehler beim Auffuellen');
    } finally {
      setRefilling(false);
    }
  }

  // ── Transaction type labels ──
  const txTypeLabel: Record<string, { label: string; class: string }> = {
    grant: { label: 'Vergabe', class: 'badge-success' },
    deduction: { label: 'Abzug', class: 'badge-error' },
    refund: { label: 'Erstattung', class: 'badge-info' },
    monthly_refill: { label: 'Monatl. Auff.', class: 'badge-primary' },
  };

  // ── Loading ──
  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <SpinnerGap weight="bold" className="w-8 h-8 text-primary-600 animate-spin" />
      </div>
    );
  }

  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      className="space-y-8"
    >
      {/* Header */}
      <div className="flex items-center justify-between">
        <h2 className="text-xl font-semibold text-gray-900 dark:text-white">Kontingent-Verwaltung</h2>
        <button
          onClick={() => setShowRefillConfirm(true)}
          className="btn btn-primary"
        >
          <ArrowsClockwise weight="bold" className="w-4 h-4" />
          Alle auffuellen
        </button>
      </div>

      {/* Overview Stats */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        {[
          {
            label: 'Kontingent im Umlauf',
            value: String(stats.totalCredits),
            icon: Coins,
            color: 'bg-primary-100 dark:bg-primary-900/30',
            iconColor: 'text-primary-600 dark:text-primary-400',
          },
          {
            label: 'Benutzer ohne Kontingent',
            value: String(stats.zeroBalanceUsers),
            icon: WarningCircle,
            color: stats.zeroBalanceUsers > 0
              ? 'bg-red-100 dark:bg-red-900/30'
              : 'bg-emerald-100 dark:bg-emerald-900/30',
            iconColor: stats.zeroBalanceUsers > 0
              ? 'text-red-600 dark:text-red-400'
              : 'text-emerald-600 dark:text-emerald-400',
          },
          {
            label: 'Benutzer gesamt',
            value: String(stats.totalUsers),
            icon: UsersIcon,
            color: 'bg-blue-100 dark:bg-blue-900/30',
            iconColor: 'text-blue-600 dark:text-blue-400',
          },
          {
            label: 'Letzte Auffuellung',
            value: stats.lastRefillDate
              ? new Date(stats.lastRefillDate).toLocaleDateString('de-DE')
              : '--',
            icon: Clock,
            color: 'bg-amber-100 dark:bg-amber-900/30',
            iconColor: 'text-amber-600 dark:text-amber-400',
          },
        ].map((s, i) => {
          const Icon = s.icon;
          return (
            <motion.div
              key={s.label}
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: i * 0.08 }}
              className="stat-card"
            >
              <div className="flex items-center justify-between">
                <div>
                  <p className="stat-label">{s.label}</p>
                  <p className="stat-value text-gray-900 dark:text-white">{s.value}</p>
                </div>
                <div className={`w-12 h-12 ${s.color} rounded-xl flex items-center justify-center`}>
                  <Icon weight="fill" className={`w-6 h-6 ${s.iconColor}`} />
                </div>
              </div>
            </motion.div>
          );
        })}
      </div>

      {/* User Credits Table */}
      <div className="space-y-4">
        <h3 className="text-lg font-semibold text-gray-900 dark:text-white">Benutzer-Kontingent</h3>
        <div className="flex flex-col sm:flex-row gap-3">
          <div className="relative flex-1">
            <MagnifyingGlass weight="regular" className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
            <input
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Nach Name oder Benutzername suchen..."
              className="input pl-11"
            />
          </div>
          <button
            onClick={() => setSortDir(d => d === 'asc' ? 'desc' : 'asc')}
            className="btn btn-secondary flex items-center gap-2"
          >
            {sortDir === 'asc' ? (
              <><ArrowUp weight="bold" className="w-4 h-4" /> Konting. aufsteigend</>
            ) : (
              <><ArrowDown weight="bold" className="w-4 h-4" /> Konting. absteigend</>
            )}
          </button>
        </div>

        <div className="card overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead>
                <tr className="bg-gray-50 dark:bg-gray-800/50">
                  <th className="text-left px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Benutzer</th>
                  <th className="text-left px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    <button
                      onClick={() => setSortDir(d => d === 'asc' ? 'desc' : 'asc')}
                      className="flex items-center gap-1 hover:text-gray-700 dark:hover:text-gray-200"
                    >
                      Konting.
                      {sortDir === 'asc' ? <CaretUp weight="bold" className="w-3 h-3" /> : <CaretDown weight="bold" className="w-3 h-3" />}
                    </button>
                  </th>
                  <th className="text-left px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Monatl. Quota</th>
                  <th className="text-left px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Letzte Auffuellung</th>
                  <th className="text-left px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Rolle</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                {filtered.map((user, i) => (
                  <motion.tr
                    key={user.id}
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    transition={{ delay: i * 0.03 }}
                    className={`cursor-pointer transition-colors ${
                      expandedUserId === user.id
                        ? 'bg-primary-50 dark:bg-primary-900/10'
                        : 'hover:bg-gray-50 dark:hover:bg-gray-800/30'
                    }`}
                    onClick={() => setExpandedUserId(expandedUserId === user.id ? null : user.id)}
                  >
                    <td className="px-6 py-4">
                      <div className="flex items-center gap-3">
                        <div className="avatar text-sm">{user.name?.charAt(0) || '?'}</div>
                        <div>
                          <span className="font-medium text-gray-900 dark:text-white">{user.name}</span>
                          <p className="text-xs text-gray-500 dark:text-gray-400">@{user.username}</p>
                        </div>
                      </div>
                    </td>
                    <td className="px-6 py-4">
                      <span className={`font-mono font-bold text-lg ${
                        (user.credits_balance ?? 0) === 0
                          ? 'text-red-500'
                          : 'text-gray-900 dark:text-white'
                      }`}>
                        {user.credits_balance ?? 0}
                      </span>
                    </td>
                    <td className="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                      {user.credits_monthly_quota ?? '--'}
                    </td>
                    <td className="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                      {user.credits_last_refilled
                        ? new Date(user.credits_last_refilled).toLocaleDateString('de-DE')
                        : '--'}
                    </td>
                    <td className="px-6 py-4">
                      <span className={`badge ${user.role === 'admin' || user.role === 'superadmin' ? 'badge-error' : 'badge-info'}`}>
                        {user.role === 'admin' || user.role === 'superadmin' ? 'Admin' : 'User'}
                      </span>
                    </td>
                  </motion.tr>
                ))}
              </tbody>
            </table>
          </div>

          {/* Expanded row: Grant credits */}
          <AnimatePresence>
            {expandedUserId && (
              <motion.div
                initial={{ opacity: 0, height: 0 }}
                animate={{ opacity: 1, height: 'auto' }}
                exit={{ opacity: 0, height: 0 }}
                className="overflow-hidden border-t border-gray-100 dark:border-gray-800"
              >
                <div className="p-6 bg-gray-50 dark:bg-gray-800/30">
                  {(() => {
                    const user = users.find(u => u.id === expandedUserId);
                    if (!user) return null;
                    return (
                      <div className="flex flex-col sm:flex-row gap-4 items-end">
                        <div className="flex-1">
                          <p className="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Kontingent vergeben an <strong>{user.name}</strong>
                          </p>
                          <div className="flex gap-3">
                            <div>
                              <label className="label text-xs">Anzahl</label>
                              <input
                                type="number"
                                min={1}
                                max={1000}
                                value={grantAmount}
                                onChange={(e) => setGrantAmount(e.target.value)}
                                placeholder="z.B. 10"
                                className="input w-28"
                                onClick={(e) => e.stopPropagation()}
                              />
                            </div>
                            <div className="flex-1">
                              <label className="label text-xs">Beschreibung (optional)</label>
                              <input
                                type="text"
                                value={grantDescription}
                                onChange={(e) => setGrantDescription(e.target.value)}
                                placeholder="z.B. Bonus fuer Dezember"
                                className="input"
                                onClick={(e) => e.stopPropagation()}
                              />
                            </div>
                          </div>
                        </div>
                        <button
                          onClick={(e) => {
                            e.stopPropagation();
                            handleGrant(user.id);
                          }}
                          disabled={granting || !grantAmount}
                          className="btn btn-primary whitespace-nowrap"
                        >
                          {granting ? (
                            <SpinnerGap weight="bold" className="w-4 h-4 animate-spin" />
                          ) : (
                            <Coins weight="bold" className="w-4 h-4" />
                          )}
                          Vergeben
                        </button>
                      </div>
                    );
                  })()}
                </div>
              </motion.div>
            )}
          </AnimatePresence>

          {filtered.length === 0 && (
            <div className="p-12 text-center">
              <UsersIcon weight="light" className="w-16 h-16 text-gray-300 dark:text-gray-700 mx-auto mb-4" />
              <p className="text-gray-500 dark:text-gray-400">Keine Benutzer gefunden</p>
            </div>
          )}
        </div>
      </div>

      {/* Transaction History */}
      <div className="space-y-4">
        <div className="flex items-center justify-between">
          <h3 className="text-lg font-semibold text-gray-900 dark:text-white">Transaktionsverlauf</h3>
          <button
            onClick={loadTransactions}
            disabled={txLoading}
            className="btn btn-ghost btn-sm"
          >
            <ArrowsClockwise weight="bold" className={`w-4 h-4 ${txLoading ? 'animate-spin' : ''}`} />
            Aktualisieren
          </button>
        </div>

        <div className="card overflow-hidden">
          {txLoading ? (
            <div className="flex items-center justify-center p-12">
              <SpinnerGap weight="bold" className="w-6 h-6 text-primary-600 animate-spin" />
            </div>
          ) : transactions.length === 0 ? (
            <div className="p-12 text-center">
              <Equals weight="light" className="w-16 h-16 text-gray-300 dark:text-gray-700 mx-auto mb-4" />
              <p className="text-gray-500 dark:text-gray-400">Keine Transaktionen vorhanden</p>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead>
                  <tr className="bg-gray-50 dark:bg-gray-800/50">
                    <th className="text-left px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Zeitpunkt</th>
                    <th className="text-left px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Benutzer</th>
                    <th className="text-left px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Typ</th>
                    <th className="text-left px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Betrag</th>
                    <th className="text-left px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Beschreibung</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                  {transactions.slice(0, 100).map((tx, i) => {
                    const typeConfig = txTypeLabel[tx.type] || { label: tx.type, class: 'badge-gray' };
                    return (
                      <motion.tr
                        key={tx.id}
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        transition={{ delay: i * 0.02 }}
                        className="hover:bg-gray-50 dark:hover:bg-gray-800/30 transition-colors"
                      >
                        <td className="px-6 py-3 text-sm text-gray-500 dark:text-gray-400">
                          {new Date(tx.created_at).toLocaleString('de-DE', {
                            day: '2-digit',
                            month: '2-digit',
                            year: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit',
                          })}
                        </td>
                        <td className="px-6 py-3">
                          <span className="text-sm font-medium text-gray-900 dark:text-white">
                            {(tx as CreditTransaction & { user?: { name?: string; username?: string } }).user?.name || tx.user_id}
                          </span>
                        </td>
                        <td className="px-6 py-3">
                          <span className={`badge ${typeConfig.class}`}>{typeConfig.label}</span>
                        </td>
                        <td className="px-6 py-3">
                          <span className={`font-mono font-bold ${
                            tx.amount > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'
                          }`}>
                            {tx.amount > 0 ? '+' : ''}{tx.amount}
                          </span>
                        </td>
                        <td className="px-6 py-3 text-sm text-gray-500 dark:text-gray-400 max-w-xs truncate">
                          {tx.description || '--'}
                        </td>
                      </motion.tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>

      {/* Refill Confirmation Dialog */}
      {showRefillConfirm && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
          <motion.div
            initial={{ opacity: 0, scale: 0.95 }}
            animate={{ opacity: 1, scale: 1 }}
            className="bg-white dark:bg-gray-800 rounded-2xl p-6 max-w-md mx-4 shadow-xl"
          >
            <h3 className="text-lg font-bold text-gray-900 dark:text-white mb-2">
              Kontingent fuer alle Benutzer auffuellen?
            </h3>
            <p className="text-sm text-gray-600 dark:text-gray-400 mb-4">
              Alle aktiven Benutzer erhalten ihr monatliches Kontingent. Bestehende Kontingente werden dabei zurueckgesetzt.
            </p>
            <div className="flex gap-3 justify-end">
              <button
                onClick={() => setShowRefillConfirm(false)}
                disabled={refilling}
                className="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white"
              >
                Abbrechen
              </button>
              <button
                onClick={handleRefillAll}
                disabled={refilling}
                className="btn btn-primary"
              >
                {refilling ? (
                  <SpinnerGap weight="bold" className="w-4 h-4 animate-spin" />
                ) : (
                  <ArrowsClockwise weight="bold" className="w-4 h-4" />
                )}
                {refilling ? 'Wird aufgefuellt...' : 'Auffuellen'}
              </button>
            </div>
          </motion.div>
        </div>
      )}
    </motion.div>
  );
}
