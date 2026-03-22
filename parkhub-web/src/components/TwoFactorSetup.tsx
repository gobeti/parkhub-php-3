import { useState } from 'react';
import { Shield, ShieldCheck, ShieldSlash, SpinnerGap } from '@phosphor-icons/react';
import { api } from '../api/client';
import { useAuth } from '../context/AuthContext';
import { useTranslation } from 'react-i18next';
import toast from 'react-hot-toast';

export function TwoFactorSetup() {
  const { t } = useTranslation();
  const { user, refreshUser } = useAuth();
  const [step, setStep] = useState<'idle' | 'setup' | 'verify'>('idle');
  const [secret, setSecret] = useState('');
  const [qrUri, setQrUri] = useState('');
  const [code, setCode] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);

  const is2faEnabled = (user as any)?.two_factor_enabled ?? false;

  async function handleSetup() {
    setLoading(true);
    try {
      const res = await api.setup2fa();
      if (res.success && res.data) {
        setSecret(res.data.secret);
        setQrUri(res.data.qr_uri);
        setStep('verify');
      } else {
        toast.error(res.error?.message || 'Setup failed');
      }
    } finally { setLoading(false); }
  }

  async function handleVerify() {
    if (code.length !== 6) { toast.error(t('security.codeLength', 'Code must be 6 digits')); return; }
    setLoading(true);
    try {
      const res = await api.verify2fa(code);
      if (res.success) {
        toast.success(t('security.2faEnabled', '2FA enabled'));
        setStep('idle');
        setCode('');
        refreshUser?.();
      } else {
        toast.error(res.error?.message || 'Invalid code');
      }
    } finally { setLoading(false); }
  }

  async function handleDisable() {
    if (!password) { toast.error(t('security.passwordRequired', 'Password required')); return; }
    setLoading(true);
    try {
      const res = await api.disable2fa(password);
      if (res.success) {
        toast.success(t('security.2faDisabled', '2FA disabled'));
        setPassword('');
        refreshUser?.();
      } else {
        toast.error(res.error?.message || 'Failed');
      }
    } finally { setLoading(false); }
  }

  return (
    <div className="bg-white dark:bg-surface-900 rounded-xl border border-gray-200 dark:border-surface-700 p-6">
      <div className="flex items-center gap-3 mb-4">
        <Shield size={24} weight="duotone" className="text-brand-600" />
        <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
          {t('security.twoFactor', 'Two-Factor Authentication')}
        </h3>
        {is2faEnabled && (
          <span className="px-2 py-0.5 text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 rounded-full">
            {t('common.enabled', 'Enabled')}
          </span>
        )}
      </div>

      {!is2faEnabled && step === 'idle' && (
        <div>
          <p className="text-sm text-gray-600 dark:text-gray-400 mb-4">
            {t('security.2faDescription', 'Add an extra layer of security with a TOTP authenticator app.')}
          </p>
          <button
            onClick={handleSetup}
            disabled={loading}
            className="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700 disabled:opacity-50"
          >
            {loading ? <SpinnerGap size={16} className="animate-spin" /> : <ShieldCheck size={16} />}
            {t('security.enable2fa', 'Enable 2FA')}
          </button>
        </div>
      )}

      {step === 'verify' && (
        <div className="space-y-4">
          <p className="text-sm text-gray-600 dark:text-gray-400">
            {t('security.scanQr', 'Scan this QR code with your authenticator app, then enter the 6-digit code.')}
          </p>

          {qrUri && (
            <div className="flex justify-center p-4 bg-white rounded-lg">
              <img src={`https://chart.googleapis.com/chart?cht=qr&chs=200x200&chl=${encodeURIComponent(qrUri)}`} alt="QR Code" width={200} height={200} />
            </div>
          )}

          <div className="text-center">
            <code className="text-xs bg-gray-100 dark:bg-surface-800 px-3 py-1 rounded font-mono">{secret}</code>
          </div>

          <div className="flex gap-2">
            <input
              type="text"
              value={code}
              onChange={(e) => setCode(e.target.value.replace(/\D/g, '').slice(0, 6))}
              placeholder="000000"
              className="flex-1 px-3 py-2 border rounded-lg text-center font-mono text-lg tracking-widest dark:bg-surface-800 dark:border-surface-600"
              maxLength={6}
            />
            <button
              onClick={handleVerify}
              disabled={loading || code.length !== 6}
              className="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50"
            >
              {loading ? <SpinnerGap size={16} className="animate-spin" /> : t('common.verify', 'Verify')}
            </button>
          </div>

          <button onClick={() => setStep('idle')} className="text-sm text-gray-500 hover:text-gray-700">
            {t('common.cancel', 'Cancel')}
          </button>
        </div>
      )}

      {is2faEnabled && step === 'idle' && (
        <div className="space-y-3">
          <p className="text-sm text-green-600 dark:text-green-400">
            {t('security.2faActive', '2FA is active. Your account is protected.')}
          </p>
          <div className="flex gap-2">
            <input
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              placeholder={t('security.confirmPassword', 'Enter password to disable')}
              className="flex-1 px-3 py-2 border rounded-lg dark:bg-surface-800 dark:border-surface-600"
            />
            <button
              onClick={handleDisable}
              disabled={loading || !password}
              className="inline-flex items-center gap-2 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50"
            >
              {loading ? <SpinnerGap size={16} className="animate-spin" /> : <ShieldSlash size={16} />}
              {t('security.disable2fa', 'Disable')}
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
