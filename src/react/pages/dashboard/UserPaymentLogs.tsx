import { useEffect, useState } from 'react';
import type { LogEntry } from '../../../../types/php_backend/logs';
import { isEmpty } from '../../../utils/string';
import { getUserLogs } from '../../utils/user';

interface Props {
  logs?: LogEntry[];
  maxItems?: number;
  className?: string;
}

interface MergedLogEntry {
  tx?: string;
  created_at?: string;
  package_buy?: any;
  payment?: any;
}

export default function UserPaymentLogs({ logs: initialLogs, maxItems = 50, className = '' }: Props) {
  const [logs, setLogs] = useState<MergedLogEntry[] | null>(initialLogs ?? null);
  const [openSections, setOpenSections] = useState<Record<string, { pkg: boolean; pay: boolean }>>({});
  const [loading, setLoading] = useState<boolean>(!initialLogs);
  const [error, setError] = useState<string | null>(null);

  // ---------------------------------------
  // SIMPLE INLINE GET TX
  // ---------------------------------------
  function extractTx(item: any): string | undefined {
    let d = item.details;
    if (typeof d === 'string') {
      try {
        d = JSON.parse(d);
      } catch {
        //
      }
    }

    return d?.transaction_id ?? d?.trx ?? d?.trx_id ?? item?.transaction_id ?? item?.trx ?? item?.trx_id ?? undefined;
  }

  useEffect(() => {
    if (initialLogs) return;

    let mounted = true;
    setLoading(true);

    (async () => {
      try {
        // GLOBAL GROUP — in one file, inline
        const groups = new Map<string, MergedLogEntry>();
        const noTxGroups: MergedLogEntry[] = [];

        let page = 1;
        const maxPages = 200;

        while (mounted && page <= maxPages) {
          const res = await getUserLogs(page);
          if (!mounted) return;

          if (isEmpty(res)) break;
          if (res && 'logs' in res && isEmpty(res.logs)) break;

          const items = (res?.logs as any[]) ?? [];
          if (items.length === 0) break;

          // ---------------------------------------
          // INLINE MERGE LOGIC (NO FUNCTIONS OUTSIDE)
          // ---------------------------------------
          for (const it of items) {
            const tx = extractTx(it);
            const action = String(it.action_type ?? it.type ?? '').toLowerCase();

            if (!tx) {
              // store non-tx entries separately
              const obj: MergedLogEntry = {
                tx: undefined,
                created_at: it.created_at
              };
              if (/buy|package/.test(action)) obj.package_buy = it;
              if (/payment/.test(action)) obj.payment = it;
              noTxGroups.push(obj);
              continue;
            }

            const existing =
              groups.get(tx) ??
              ({
                tx,
                created_at: it.created_at,
                package_buy: undefined,
                payment: undefined
              } as MergedLogEntry);

            if (/buy|package/.test(action)) existing.package_buy = it;
            if (/payment/.test(action)) existing.payment = it;

            // newest created_at wins
            if (
              !existing.created_at ||
              (it.created_at && new Date(existing.created_at).getTime() < new Date(it.created_at).getTime())
            ) {
              existing.created_at = it.created_at;
            }

            groups.set(tx, existing);
          }

          // pagination
          const current = Number((res as any)?.current_page ?? (res as any)?.page ?? page);
          const last = Number((res as any)?.last_page ?? (res as any)?.total_pages ?? 0);
          if (last && current >= last) break;

          page++;
        }

        if (!mounted) return;

        // output list
        const mergedAll = [...groups.values(), ...noTxGroups];
        mergedAll.sort((a, b) => new Date(b.created_at || 0).getTime() - new Date(a.created_at || 0).getTime());

        setLogs(mergedAll);
      } catch (err) {
        if (!mounted) return;
        setError(String(err));
      } finally {
        if (mounted) setLoading(false);
      }
    })();

    return () => {
      mounted = false;
    };
  }, [initialLogs]);

  if (loading)
    return (
      <div className={className}>
        <p className="text-sm text-gray-500">Loading payment logs…</p>
      </div>
    );
  if (error)
    return (
      <div className={className}>
        <p className="text-sm text-red-500">Error loading logs: {error}</p>
      </div>
    );
  if (!logs || logs.length === 0)
    return (
      <div className={className}>
        <p className="text-sm text-gray-500">No payment activity found.</p>
      </div>
    );

  // -------------------------------------
  // RENDER
  // -------------------------------------
  return (
    <div className={`${className} w-full`} role="list" aria-label="User payment logs">
      {logs
        .filter((e) => e.package_buy || e.payment)
        .slice(0, maxItems)
        .filter((entry) => {
          const pkg = entry.package_buy;
          const pay = entry.payment;
          const package_type = pkg?.action_type.toUpperCase();
          const payment_type = pay?.action_type.toUpperCase();
          if (payment_type === 'PAYMENT') {
            return true;
          }
          if (package_type === 'PACKAGE_BUY') {
            return true;
          }
          return false;
        })
        .map((entry, i) => {
          const pkg = entry.package_buy;
          const pay = entry.payment;
          // const package_type = pkg?.action_type;
          // const payment_type = pay?.action_type;
          // console.log({ package_type, payment_type });
          // compute transaction status from payment or package buy details
          function getTxStatus(): string | null {
            const d = pay?.details ?? pkg?.details ?? pay ?? pkg ?? null;
            if (!d) return null;

            // normalize if details is string
            let details = d;
            if (typeof details === 'string') {
              try {
                details = JSON.parse(details);
              } catch {
                // leave as-is
              }
            }

            // common fields: status, payment_result.error, payment_result.message, error
            if (typeof details === 'object') {
              if (details.status) return String(details.status);
              if (details.payment_result) {
                const pr = details.payment_result;
                if (pr.error === true) return 'error';
                if (pr.error === false && pr.message) return String(pr.message);
                if (pr.error === false) return 'successful';
              }
              if (details.error === true) return 'error';
              if (details.error === false) return 'successful';
            }

            return null;
          }
          const amount = pay?.amount ?? pkg?.amount ?? undefined;
          const when = entry.created_at ?? pay?.created_at ?? pkg?.created_at;

          // stable string id for key and openSections (prefer tx, fall back to ids or index)
          const id = entry.tx ?? `${pkg?.id ?? pay?.id ?? `no-tx-${i}`}`;

          return (
            <div
              key={id}
              role="listitem"
              className="mb-3 bg-white dark:bg-gray-800 rounded-md shadow-sm dark:shadow-white overflow-hidden">
              <div className="px-3 py-2 flex items-center justify-between gap-3">
                <div className="flex items-center gap-3 min-w-0">
                  <div className="flex-shrink-0">
                    <div className="h-8 w-8 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-xs text-gray-600">
                      TX
                    </div>
                  </div>
                  <div className="min-w-0">
                    <p className="text-sm font-medium text-gray-800 dark:text-gray-100 truncate">{id}</p>
                    {(() => {
                      const status = getTxStatus();
                      return status ? (
                        <div className="mt-1 flex items-center gap-2">
                          <span className="text-xs text-gray-500 dark:text-gray-400">Status</span>
                          <span className="text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-900 text-gray-700 dark:text-gray-200">
                            {status}
                          </span>
                        </div>
                      ) : null;
                    })()}
                  </div>
                </div>

                <div className="flex-shrink-0 text-right">
                  {typeof amount === 'number' && (
                    <p className="text-sm font-semibold text-green-600 dark:text-green-400">{Math.round(amount)}</p>
                  )}
                  {when && (
                    <p className="text-xs text-gray-400 dark:text-gray-500">{new Date(when).toLocaleString()}</p>
                  )}
                </div>
              </div>

              {/* Collapsible details section */}
              <div className="px-3 pb-3">
                <div className="flex items-center justify-between text-[10px] text-gray-400 dark:text-gray-500 mb-1">
                  <div className="flex items-center gap-3">
                    {pkg && (
                      <div className="flex items-center gap-2">
                        <span>PACKAGE_BUY</span>
                        <button
                          type="button"
                          className="text-xs text-blue-600 dark:text-blue-400"
                          onClick={() =>
                            setOpenSections((s) => ({
                              ...s,
                              [id]: {
                                pkg: !s[id]?.pkg,
                                pay: s[id]?.pay ?? false
                              }
                            }))
                          }>
                          {openSections[id]?.pkg ? 'Hide' : 'Show'}
                        </button>
                      </div>
                    )}

                    {pay && (
                      <div className="flex items-center gap-2">
                        <span>PAYMENT</span>
                        <button
                          type="button"
                          className="text-xs text-blue-600 dark:text-blue-400"
                          onClick={() =>
                            setOpenSections((s) => ({
                              ...s,
                              [id]: {
                                pay: !s[id]?.pay,
                                pkg: s[id]?.pkg ?? false
                              }
                            }))
                          }>
                          {openSections[id]?.pay ? 'Hide' : 'Show'}
                        </button>
                      </div>
                    )}
                  </div>
                </div>

                {openSections[id]?.pkg && (
                  <pre className="text-xs text-gray-500 dark:text-gray-400 overflow-auto bg-gray-50 dark:bg-gray-900 p-2 rounded w-full whitespace-pre-wrap max-h-60 mb-2">
                    {JSON.stringify(pkg, null, 2)}
                  </pre>
                )}

                {openSections[id]?.pay && (
                  <pre className="text-xs text-gray-500 dark:text-gray-400 overflow-auto bg-gray-50 dark:bg-gray-900 p-2 rounded w-full whitespace-pre-wrap max-h-60">
                    {JSON.stringify(pay, null, 2)}
                  </pre>
                )}
              </div>
            </div>
          );
        })}
    </div>
  );
}
