import React from 'react';
import type gitHistoryToJson from '../../dev/git-history-to-json';
import { createUrl } from '../utils/url';
import ReactMarkdown from 'react-markdown';
import style from './Changelog.module.scss';

type Commit = ReturnType<typeof gitHistoryToJson>[number];

// Module-level cache to ensure fetch runs only once per session
let gitHistoryCache: Commit[] | null = null;

export default function Changelog() {
  const [commits, setCommits] = React.useState<Commit[] | null>(null);
  const [error, setError] = React.useState<string | null>(null);
  const [page, setPage] = React.useState(1);
  const [isLoading, setIsLoading] = React.useState(false);
  const [refreshKey, setRefreshKey] = React.useState(0);
  const COMMITS_PER_PAGE = 30;

  // Try to load cached first page from localStorage on mount
  React.useEffect(() => {
    if (!commits) {
      const cached = localStorage.getItem('gitHistoryFirstPage');
      if (cached) {
        try {
          const parsed = JSON.parse(cached);
          if (Array.isArray(parsed) && parsed.length > 0) {
            setCommits(parsed);
          }
        } catch {
          setError('Failed to parse cached git history');
        }
      }
    }
  }, []);

  // Fetch full data and update cache/UI
  React.useEffect(() => {
    let cancelled = false;
    setIsLoading(true);
    // On refresh, always fetch new data and update cache
    const fetchData = async () => {
      try {
        // Add cache buster to avoid stale fetches
        const url = createUrl(`/data/git-history.json`, { v: import.meta.env.VITE_GIT_COMMIT, t: Date.now() });
        const res = await fetch(url, { headers: { 'Cache-Control': 'no-cache', Pragma: 'no-cache' } });
        const commits: Commit[] = await res.json();
        if (!cancelled) {
          console.log(commits.slice(0, 5));
          setCommits(commits);
          gitHistoryCache = commits;
          try {
            localStorage.setItem('gitHistoryFirstPage', JSON.stringify(commits.slice(0, COMMITS_PER_PAGE)));
          } catch {
            setError('Failed to update git history cache');
          }
        }
      } catch (err: any) {
        if (!cancelled) setError(err.message);
      } finally {
        if (!cancelled) setIsLoading(false);
      }
    };
    // If not a refresh, use cache if available
    if (refreshKey === 0 && gitHistoryCache) {
      setCommits(gitHistoryCache);
      try {
        localStorage.setItem('gitHistoryFirstPage', JSON.stringify(gitHistoryCache.slice(0, COMMITS_PER_PAGE)));
      } catch {
        setError('Failed to update git history cache');
      }
      setIsLoading(false);
    } else {
      fetchData();
    }
    return () => {
      cancelled = true;
    };
  }, [COMMITS_PER_PAGE, refreshKey]);

  // Pagination logic
  const totalCommits = commits ? commits.length : 0;
  const totalPages = Math.ceil(totalCommits / COMMITS_PER_PAGE);
  const paginatedCommits = commits ? commits.slice((page - 1) * COMMITS_PER_PAGE, page * COMMITS_PER_PAGE) : [];

  // Refresh handler: clear cache and refetch
  const handleRefresh = () => {
    gitHistoryCache = null;
    setCommits(null);
    setError(null);
    setIsLoading(true);
    setPage(1);
    localStorage.removeItem('gitHistoryFirstPage');
    setRefreshKey((k) => k + 1);
  };

  return (
    <>
      <div className="container mx-auto px-4 py-8">
        <div className="mb-8 text-center">
          <h1 className="text-3xl font-bold mb-2 flex items-center justify-center gap-2">
            <i className="fa-duotone fa-code-branch text-blue-600 dark:text-blue-400"></i>
            <span className="text-gray-900 dark:text-gray-100">Repository Progress</span>
          </h1>
          <p className="text-gray-600 dark:text-gray-300 max-w-2xl mx-auto">
            Explore the historical progress and key milestones of this repository. Each entry below represents a
            significant update, feature, or fix that has shaped the project.
          </p>
          <button
            className="mt-4 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors disabled:opacity-50"
            onClick={handleRefresh}
            disabled={isLoading}
            aria-label="Refresh changelog">
            <i className="fa-duotone fa-arrows-rotate mr-2"></i>
            Refresh
          </button>
        </div>
        {error && <div className="text-red-500 dark:text-red-400 text-center mb-4">{error}</div>}
        {isLoading && (
          <div className="flex justify-center items-center py-8">
            <i className="fa-duotone fa-spinner-third animate-spin text-2xl text-blue-600 dark:text-blue-400 mr-2"></i>
            <span className="text-gray-500 dark:text-gray-300">Loading git history...</span>
          </div>
        )}
        {commits && (
          <>
            {/* Commit list */}
            <ol className="relative border-l border-gray-200 dark:border-gray-700">
              {paginatedCommits.map((commit: Commit) => (
                <li key={commit.hash} className="mb-10 ml-6">
                  <span className="absolute flex items-center justify-center w-8 h-8 bg-blue-100 dark:bg-blue-900 rounded-full -left-4 ring-8 ring-white dark:ring-gray-900">
                    <i className="fa-duotone fa-rocket-launch text-blue-600 dark:text-blue-400"></i>
                  </span>
                  <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-2 md:gap-4">
                    <h3 className="flex-1 text-lg font-semibold text-gray-900 dark:text-white">
                      {commit.message.split('\n')[0]}
                    </h3>
                    <div className="flex flex-col md:items-end gap-1">
                      <span className="flex items-center text-xs text-gray-400 dark:text-gray-300 font-mono">
                        <i className="fa-duotone fa-calendar-days mr-1"></i>
                        {commit.date
                          ? new Date(commit.date).toLocaleString(undefined, {
                              year: 'numeric',
                              month: 'short',
                              day: 'numeric',
                              hour: '2-digit',
                              minute: '2-digit'
                            })
                          : ''}
                      </span>
                      <span className="flex items-center text-xs text-gray-400 dark:text-gray-300 font-mono">
                        <i className="fa-duotone fa-hashtag mr-1"></i>
                        {commit.hash.substring(0, 8)}
                      </span>
                    </div>
                  </div>
                  <div className={`prose dark:prose-invert mb-2 text-gray-400 dark:text-gray-300 ${style.markdown}`}>
                    <ReactMarkdown>{commit.message.split('\n').slice(1).join('\n').trim()}</ReactMarkdown>
                  </div>
                  {commit.files && commit.files.length > 0 && (
                    <div className="mb-2">
                      <span className="text-xs text-gray-400 dark:text-gray-300 mr-2">
                        <i className="fa-duotone fa-file-code mr-1"></i>
                        Files changed:
                      </span>
                      <ul className="list-disc list-inside text-xs text-gray-500 dark:text-gray-400 overflow-x-auto break-all max-w-full">
                        {commit.files.map((file: string) => (
                          <li key={file} className="break-all max-w-full">
                            {file}
                          </li>
                        ))}
                      </ul>
                    </div>
                  )}
                </li>
              ))}
            </ol>
            {/* Pagination Controls */}
            {totalPages > 1 && (
              <nav className="flex justify-center mt-8" aria-label="Pagination">
                <ul className="inline-flex -space-x-px text-sm">
                  {/* First page button (only one left arrow, disables on first page) */}
                  <li>
                    <button
                      className="px-3 py-2 ml-0 leading-tight text-gray-500 bg-white border border-gray-300 rounded-l-lg hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
                      onClick={() => setPage(1)}
                      disabled={page === 1}
                      aria-label="First page">
                      <i className="fa-duotone fa-angles-left"></i>
                    </button>
                  </li>
                  {/* Previous ellipsis removed */}
                  {/* Centered page numbers */}
                  {[-1, 0, 1].map((offset) => {
                    const p = page + offset;
                    if (p < 1 || p > totalPages) return null;
                    return (
                      <li key={p}>
                        <button
                          className={`px-3 py-2 leading-tight border ${
                            p === page
                              ? 'text-white bg-blue-600 border-blue-600 dark:bg-blue-700 dark:border-blue-700'
                              : 'text-gray-500 bg-white border-gray-300 hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'
                          }`}
                          onClick={() => setPage(p)}
                          aria-current={p === page ? 'page' : undefined}>
                          {p}
                        </button>
                      </li>
                    );
                  })}
                  {/* Next ellipsis removed */}
                  {/* Last page button (only one right arrow, disables on last page) */}
                  <li>
                    <button
                      className="px-3 py-2 leading-tight text-gray-500 bg-white border border-gray-300 rounded-r-lg hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
                      onClick={() => setPage(totalPages)}
                      disabled={page === totalPages}
                      aria-label="Last page">
                      <i className="fa-duotone fa-angles-right"></i>
                    </button>
                  </li>
                </ul>
              </nav>
            )}
          </>
        )}
      </div>
    </>
  );
}
