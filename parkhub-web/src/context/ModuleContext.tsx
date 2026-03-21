import { createContext, useContext, useEffect, useState, useCallback, type ReactNode } from 'react';

// ── Types ──

/** All backend module keys from config/modules.php */
export type ModuleKey =
  | 'bookings'
  | 'vehicles'
  | 'absences'
  | 'payments'
  | 'webhooks'
  | 'notifications'
  | 'branding'
  | 'import'
  | 'qr_codes'
  | 'favorites'
  | 'swap_requests'
  | 'recurring_bookings'
  | 'zones'
  | 'credits'
  | 'metrics'
  | 'broadcasting'
  | 'admin_reports'
  | 'data_export'
  | 'setup_wizard'
  | 'gdpr'
  | 'push_notifications'
  | 'stripe';

export interface ModulesResponse {
  modules: Record<ModuleKey, boolean>;
  version: string;
}

interface ModuleState {
  /** Map of module key -> enabled/disabled */
  modules: Record<string, boolean>;
  /** Backend version string */
  version: string;
  /** Whether modules have been loaded from the API */
  loaded: boolean;
  /** Loading state */
  loading: boolean;
  /** Error if fetch failed */
  error: string | null;
  /** Check if a specific module is enabled */
  isModuleEnabled: (key: ModuleKey) => boolean;
  /** Refresh modules from the API */
  refresh: () => Promise<void>;
}

const ModuleContext = createContext<ModuleState | null>(null);

// Default: all enabled (optimistic — prevents flash of hidden content)
const DEFAULT_MODULES: Record<string, boolean> = {
  bookings: true,
  vehicles: true,
  absences: true,
  payments: true,
  webhooks: true,
  notifications: true,
  branding: true,
  import: true,
  qr_codes: true,
  favorites: true,
  swap_requests: true,
  recurring_bookings: true,
  zones: true,
  credits: true,
  metrics: true,
  broadcasting: true,
  admin_reports: true,
  data_export: true,
  setup_wizard: true,
  gdpr: true,
  push_notifications: true,
  stripe: false,
};

export function ModuleProvider({ children }: { children: ReactNode }) {
  const [modules, setModules] = useState<Record<string, boolean>>(DEFAULT_MODULES);
  const [version, setVersion] = useState('');
  const [loaded, setLoaded] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchModules = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await fetch('/api/v1/modules');
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const json = await res.json();
      // Handle wrapped response: { success, data: { modules, version } }
      const data = json?.data ?? json;
      if (data?.modules) {
        setModules(data.modules);
        setVersion(data.version ?? '');
        setLoaded(true);
      }
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load modules');
      // Keep defaults on error — don't break the UI
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchModules();
  }, [fetchModules]);

  const isModuleEnabled = useCallback(
    (key: ModuleKey) => modules[key] ?? false,
    [modules],
  );

  return (
    <ModuleContext.Provider
      value={{ modules, version, loaded, loading, error, isModuleEnabled, refresh: fetchModules }}
    >
      {children}
    </ModuleContext.Provider>
  );
}

export function useModules() {
  const ctx = useContext(ModuleContext);
  if (!ctx) throw new Error('useModules must be used within ModuleProvider');
  return ctx;
}
