import React from 'react';

// Small utility: safely parse JSON, return null if not JSON
function tryParseJson(raw: string): any | null {
  try {
    return JSON.parse(raw);
  } catch (_) {
    return null;
  }
}

function prettyJson(data: any): string {
  try {
    return JSON.stringify(data, null, 2);
  } catch (_) {
    return String(data);
  }
}

export default function DetailsCell({ raw }: { raw: string }) {
  const [expanded, setExpanded] = React.useState(false);
  const parsed = React.useMemo(() => tryParseJson(raw), [raw]);

  const copy = React.useCallback(() => {
    const text = parsed ? prettyJson(parsed) : raw;
    if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
      navigator.clipboard.writeText(text);
      return;
    }
    const ta = document.createElement('textarea');
    ta.value = text;
    document.body.appendChild(ta);
    ta.select();
    try {
      document.execCommand('copy');
    } catch (_) {
      /* ignore */
    }
    document.body.removeChild(ta);
  }, [parsed, raw]);

  if (!parsed) {
    const short = raw.length > 200 && !expanded ? raw.slice(0, 200) + '…' : raw;
    return (
      <div className="text-xs break-words">
        <div className="mb-1 whitespace-pre-wrap">{short}</div>
        <div className="flex items-center gap-2">
          {raw.length > 200 && (
            <button
              onClick={() => setExpanded((v) => !v)}
              className="text-xs text-blue-600 dark:text-blue-300 p-1 rounded"
              aria-label={expanded ? 'Hide' : 'Show'}>
              <i className={expanded ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye'} aria-hidden="true" />
              <span className="sr-only">{expanded ? 'Hide' : 'Show'}</span>
            </button>
          )}
          <button onClick={copy} className="text-xs text-gray-600 dark:text-gray-300 p-1 rounded" aria-label="Copy">
            <i className="fa-solid fa-copy" aria-hidden="true" />
            <span className="sr-only">Copy</span>
          </button>
        </div>
      </div>
    );
  }

  const pretty = prettyJson(parsed);
  const oneLine = JSON.stringify(parsed);
  const preview = oneLine.length > 120 && !expanded ? oneLine.slice(0, 120) + '…' : oneLine;

  return (
    <div className="text-xs font-mono text-gray-800 dark:text-gray-200">
      <div className="mb-1 break-words whitespace-pre-wrap">
        {expanded ? <pre className="text-[11px] whitespace-pre-wrap break-words">{pretty}</pre> : preview}
      </div>
      <div className="flex items-center gap-2">
        <button
          onClick={() => setExpanded((v) => !v)}
          className="text-xs text-blue-600 dark:text-blue-300 p-1 rounded"
          aria-label={expanded ? 'Hide JSON' : 'Show JSON'}>
          <i className={expanded ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye'} aria-hidden="true" />
          <span className="sr-only">{expanded ? 'Hide JSON' : 'Show JSON'}</span>
        </button>
        <button onClick={copy} className="text-xs text-gray-600 dark:text-gray-300 p-1 rounded" aria-label="Copy JSON">
          <i className="fa-solid fa-copy" aria-hidden="true" />
          <span className="sr-only">Copy</span>
        </button>
      </div>
    </div>
  );
}
