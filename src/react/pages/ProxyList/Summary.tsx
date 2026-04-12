import React, { useState, useEffect } from 'react';
import { formatNumberWithThousandSeparators } from '../../../utils/number';
import { createUrl } from '../../utils/url';

export type CounterProxies = {
  total_proxies?: number;
  working_proxies?: number;
  private_proxies?: number;
  https_proxies?: number;
  untested_proxies?: number;
  dead_proxies?: number;
};

type SummaryItem = {
  key: keyof CounterProxies;
  label: string;
  icon: string;
  bg: string;
  text: string;
};

const counterList: SummaryItem[] = [
  {
    key: 'total_proxies',
    label: 'Total',
    icon: 'fa-duotone fa-list',
    bg: 'bg-gray-50 dark:bg-gray-800',
    text: 'text-gray-800 dark:text-gray-200'
  },
  {
    key: 'working_proxies',
    label: 'Alive',
    icon: 'fa-duotone fa-heart-pulse',
    bg: 'bg-green-50 dark:bg-green-900',
    text: 'text-green-700 dark:text-green-300'
  },
  {
    key: 'https_proxies',
    label: 'HTTPS',
    icon: 'fa-duotone fa-lock',
    bg: 'bg-blue-50 dark:bg-blue-900',
    text: 'text-blue-700 dark:text-blue-300'
  },
  {
    key: 'private_proxies',
    label: 'Private',
    icon: 'fa-duotone fa-user-secret',
    bg: 'bg-purple-50 dark:bg-purple-900',
    text: 'text-purple-700 dark:text-purple-300'
  },
  {
    key: 'untested_proxies',
    label: 'Untested',
    icon: 'fa-duotone fa-eye-slash',
    bg: 'bg-yellow-50 dark:bg-yellow-900',
    text: 'text-yellow-700 dark:text-yellow-300'
  },
  {
    key: 'dead_proxies',
    label: 'Dead',
    icon: 'fa-duotone fa-skull',
    bg: 'bg-red-50 dark:bg-red-900',
    text: 'text-red-700 dark:text-red-300'
  }
];

export default function Summary() {
  const [counters, setCounters] = useState<CounterProxies>({});

  useEffect(() => {
    const fetchCounters = async () => {
      try {
        const res = await fetch(createUrl('/php_backend/proxy-summary.php'), { method: 'POST' });
        if (!res.ok) return;
        const data = await res.json();
        if (data.counter_proxies && typeof data.counter_proxies === 'object') {
          setCounters({
            total_proxies: Number(data.counter_proxies.total_proxies) || 0,
            working_proxies: Number(data.counter_proxies.working_proxies) || 0,
            private_proxies: Number(data.counter_proxies.private_proxies) || 0,
            https_proxies: Number(data.counter_proxies.https_proxies) || 0,
            untested_proxies: Number(data.counter_proxies.untested_proxies) || 0,
            dead_proxies: Number(data.counter_proxies.dead_proxies) || 0
          });
        }
      } catch (_err) {
        // ignore errors
      }
    };

    fetchCounters();
  }, []);
  return (
    <div className="mb-3">
      <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-2">
        {counterList.map((item) => {
          const value = counters[item.key];
          const displayValue = Number.isFinite(Number(value)) ? formatNumberWithThousandSeparators(Number(value)) : '-';

          return (
            <div
              key={item.key}
              className={`flex items-center gap-3 px-3 py-2 rounded-lg border ${item.bg} ${item.text} shadow-sm`}>
              <div className="w-8 h-8 flex items-center justify-center rounded-full bg-white/60 dark:bg-black/40 border border-gray-200 dark:border-gray-700 text-sm">
                <i className={`${item.icon} text-lg`} aria-hidden="true" />
              </div>
              <div className="flex flex-col">
                <span className="text-xs text-gray-600 dark:text-gray-400">{item.label}</span>
                <span className="text-sm font-semibold">{displayValue}</span>
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}
