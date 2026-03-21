import { useModules, type ModuleKey } from '../context/ModuleContext';

/**
 * Check if a module is enabled. Returns { enabled, loading, loaded }.
 *
 * Usage:
 *   const { enabled } = useModule('bookings');
 *   if (!enabled) return <ModuleDisabled name="Bookings" />;
 */
export function useModule(key: ModuleKey) {
  const { isModuleEnabled, loading, loaded } = useModules();
  return {
    enabled: isModuleEnabled(key),
    loading,
    loaded,
  };
}

/**
 * Check multiple modules at once.
 *
 * Usage:
 *   const { allEnabled, anyEnabled } = useModules(['bookings', 'recurring_bookings']);
 */
export function useModuleGroup(keys: ModuleKey[]) {
  const { isModuleEnabled, loading, loaded } = useModules();
  return {
    allEnabled: keys.every(k => isModuleEnabled(k)),
    anyEnabled: keys.some(k => isModuleEnabled(k)),
    loading,
    loaded,
  };
}
