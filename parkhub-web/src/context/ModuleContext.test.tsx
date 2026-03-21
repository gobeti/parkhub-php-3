import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import { ModuleProvider, useModules } from './ModuleContext';

// Helper component to consume the context
function ModuleConsumer() {
  const { modules, version, loaded, loading, error, isModuleEnabled } = useModules();
  return (
    <div>
      <span data-testid="loading">{String(loading)}</span>
      <span data-testid="loaded">{String(loaded)}</span>
      <span data-testid="error">{error ?? 'null'}</span>
      <span data-testid="version">{version}</span>
      <span data-testid="bookings">{String(isModuleEnabled('bookings'))}</span>
      <span data-testid="stripe">{String(isModuleEnabled('stripe'))}</span>
      <span data-testid="module-count">{Object.keys(modules).length}</span>
    </div>
  );
}

describe('ModuleContext', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('useModules throws outside ModuleProvider', () => {
    const spy = vi.spyOn(console, 'error').mockImplementation(() => {});

    expect(() => render(<ModuleConsumer />)).toThrow(
      'useModules must be used within ModuleProvider',
    );

    spy.mockRestore();
  });

  it('loads modules from API and sets state', async () => {
    const mockResponse = {
      success: true,
      data: {
        modules: {
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
        },
        version: '1.9.2',
      },
    };

    vi.spyOn(global, 'fetch').mockResolvedValue({
      ok: true,
      json: async () => mockResponse,
    } as Response);

    render(
      <ModuleProvider>
        <ModuleConsumer />
      </ModuleProvider>,
    );

    await waitFor(() => {
      expect(screen.getByTestId('loaded').textContent).toBe('true');
    });

    expect(screen.getByTestId('loading').textContent).toBe('false');
    expect(screen.getByTestId('error').textContent).toBe('null');
    expect(screen.getByTestId('version').textContent).toBe('1.9.2');
    expect(screen.getByTestId('bookings').textContent).toBe('true');
    expect(screen.getByTestId('stripe').textContent).toBe('false');
    expect(Number(screen.getByTestId('module-count').textContent)).toBe(22);
  });

  it('falls back to defaults on network error', async () => {
    vi.spyOn(global, 'fetch').mockRejectedValue(new Error('Network error'));

    render(
      <ModuleProvider>
        <ModuleConsumer />
      </ModuleProvider>,
    );

    await waitFor(() => {
      expect(screen.getByTestId('loading').textContent).toBe('false');
    });

    expect(screen.getByTestId('error').textContent).toBe('Network error');
    // Defaults: bookings enabled, stripe disabled
    expect(screen.getByTestId('bookings').textContent).toBe('true');
    expect(screen.getByTestId('stripe').textContent).toBe('false');
  });

  it('handles API error (non-200)', async () => {
    vi.spyOn(global, 'fetch').mockResolvedValue({
      ok: false,
      status: 500,
      json: async () => ({}),
    } as Response);

    render(
      <ModuleProvider>
        <ModuleConsumer />
      </ModuleProvider>,
    );

    await waitFor(() => {
      expect(screen.getByTestId('loading').textContent).toBe('false');
    });

    expect(screen.getByTestId('error').textContent).toBe('HTTP 500');
  });
});
