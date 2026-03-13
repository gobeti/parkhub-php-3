import { useState, useEffect, useCallback, useRef } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { Timer, ArrowsClockwise, Eye, CaretUp, CaretDown, Sparkle } from '@phosphor-icons/react';

const API_BASE = import.meta.env.VITE_API_URL || '';

interface DemoStatus {
  enabled: boolean;
  timer: { remaining: number; duration: number; started_at: number };
  votes: { current: number; threshold: number; has_voted: boolean };
  viewers: number;
}

export function DemoOverlay() {
  const [status, setStatus] = useState<DemoStatus | null>(null);
  const [collapsed, setCollapsed] = useState(false);
  const [voting, setVoting] = useState(false);
  const [localRemaining, setLocalRemaining] = useState<number | null>(null);
  const [demoEnabled, setDemoEnabled] = useState<boolean>(
    import.meta.env.VITE_DEMO_MODE === 'true'
  );
  const lastSyncRef = useRef<number>(0);

  // Check if demo mode is enabled
  useEffect(() => {
    if (demoEnabled) return;
    fetch(`${API_BASE}/api/v1/demo/config`)
      .then(r => r.json())
      .then(data => { if (data.demo_mode) setDemoEnabled(true); })
      .catch(() => {});
  }, [demoEnabled]);

  // Fetch demo status
  const fetchStatus = useCallback(async () => {
    try {
      const res = await fetch(`${API_BASE}/api/v1/demo/status`);
      if (!res.ok) return;
      const data: DemoStatus = await res.json();
      setStatus(data);
      setLocalRemaining(data.timer.remaining);
      lastSyncRef.current = Date.now();
    } catch {
      // silently ignore fetch errors
    }
  }, []);

  // Initial fetch + polling every 15s
  useEffect(() => {
    if (!demoEnabled) return;
    fetchStatus();
    const interval = setInterval(fetchStatus, 15000);
    return () => clearInterval(interval);
  }, [demoEnabled, fetchStatus]);

  // Local countdown (ticks every second between server polls)
  useEffect(() => {
    if (localRemaining === null) return;
    const interval = setInterval(() => {
      setLocalRemaining(prev => {
        if (prev === null || prev <= 0) return 0;
        return prev - 1;
      });
    }, 1000);
    return () => clearInterval(interval);
  }, [localRemaining !== null]); // eslint-disable-line react-hooks/exhaustive-deps

  // Vote handler
  const handleVote = async () => {
    if (voting || status?.votes.has_voted) return;
    setVoting(true);
    try {
      const res = await fetch(`${API_BASE}/api/v1/demo/vote`, { method: 'POST' });
      const data = await res.json();
      if (data.reset) {
        // Demo is resetting
        setStatus(prev => prev ? { ...prev, votes: { ...prev.votes, current: 0 } } : prev);
        setTimeout(() => window.location.reload(), 2000);
      } else {
        // Update vote count locally
        setStatus(prev => prev ? {
          ...prev,
          votes: { ...prev.votes, current: data.votes, has_voted: true }
        } : prev);
      }
    } catch {
      // silently ignore vote errors
    } finally {
      setVoting(false);
    }
  };

  if (!demoEnabled || !status) return null;

  const remaining = localRemaining ?? status.timer.remaining;
  const minutes = Math.floor(remaining / 60);
  const seconds = remaining % 60;
  const timerStr = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
  const isUrgent = remaining < 300;
  const voteProgress = (status.votes.current / status.votes.threshold) * 100;

  return (
    <AnimatePresence>
      <motion.div
        initial={{ y: -80, opacity: 0 }}
        animate={{ y: 0, opacity: 1 }}
        exit={{ y: -80, opacity: 0 }}
        transition={{ type: 'spring', damping: 25, stiffness: 300 }}
        className="fixed top-3 left-1/2 -translate-x-1/2 z-[60]"
      >
        {collapsed ? (
          /* Minimized pill */
          <motion.button
            layout
            onClick={() => setCollapsed(false)}
            className="flex items-center gap-1.5 px-3 py-1.5 rounded-full
                       bg-primary-500/90 backdrop-blur-xl text-stone-900
                       text-xs font-bold shadow-lg shadow-primary-500/25
                       hover:bg-primary-400/90 transition-colors"
          >
            <Sparkle weight="fill" className="w-3.5 h-3.5" />
            DEMO
            <span className="font-mono text-[10px] opacity-80">{timerStr}</span>
            <CaretDown weight="bold" className="w-3 h-3" />
          </motion.button>
        ) : (
          /* Full overlay bar */
          <motion.div
            layout
            className="flex flex-wrap items-center justify-center gap-2 sm:gap-3 px-3 sm:px-4 py-2 rounded-2xl
                       bg-white/80 dark:bg-gray-900/80 backdrop-blur-xl
                       border border-gray-200/50 dark:border-gray-700/50
                       shadow-xl shadow-black/10 dark:shadow-black/30
                       text-sm max-w-[95vw]"
          >
            {/* DEMO badge */}
            <div className="flex items-center gap-1 px-2 py-0.5 rounded-lg
                            bg-primary-500 text-stone-900 font-bold text-xs tracking-wide">
              <Sparkle weight="fill" className="w-3.5 h-3.5 animate-pulse" />
              DEMO
            </div>

            <div className="w-px h-5 bg-gray-200 dark:bg-gray-700 hidden sm:block" />

            {/* Timer */}
            <div className={`flex items-center gap-1.5 font-mono font-semibold tabular-nums
                            ${isUrgent ? 'text-red-500 animate-pulse' : 'text-gray-700 dark:text-gray-300'}`}>
              <Timer weight="bold" className="w-4 h-4" />
              {timerStr}
            </div>

            <div className="w-px h-5 bg-gray-200 dark:bg-gray-700 hidden sm:block" />

            {/* Viewers */}
            <div className="flex items-center gap-1.5 text-gray-500 dark:text-gray-400">
              <Eye weight="bold" className="w-4 h-4" />
              <span className="text-xs font-medium">{status.viewers}</span>
            </div>

            <div className="w-px h-5 bg-gray-200 dark:bg-gray-700 hidden sm:block" />

            {/* Vote to reset */}
            <button
              onClick={handleVote}
              disabled={voting || status.votes.has_voted}
              className={`flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium
                         transition-all duration-200
                         ${status.votes.has_voted
                           ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 cursor-default'
                           : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-primary-100 dark:hover:bg-primary-900/30 hover:text-primary-700 dark:hover:text-primary-400'
                         }`}
            >
              <ArrowsClockwise weight="bold" className={`w-3.5 h-3.5 ${voting ? 'animate-spin' : ''}`} />
              <span>
                {status.votes.has_voted ? 'Voted' : 'Reset'}
              </span>
              {/* Vote progress bar */}
              <div className="w-10 h-1.5 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                <div
                  className="h-full rounded-full bg-primary-500 transition-all duration-500"
                  style={{ width: `${voteProgress}%` }}
                />
              </div>
              <span className="text-[10px] opacity-60">
                {status.votes.current}/{status.votes.threshold}
              </span>
            </button>

            {/* Collapse button */}
            <button
              onClick={() => setCollapsed(true)}
              className="p-0.5 rounded text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
              aria-label="Minimize demo bar"
            >
              <CaretUp weight="bold" className="w-3.5 h-3.5" />
            </button>
          </motion.div>
        )}
      </motion.div>
    </AnimatePresence>
  );
}
