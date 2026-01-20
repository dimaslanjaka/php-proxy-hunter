import React, { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { createUrl } from '../../utils/url';
import { timeAgo } from '../../../utils/date/timeAgo';

const backupPhp = createUrl('/php_backend/download/backups.php');

type BackupFile = {
  name: string;
  path: string;
  abs_path?: string;
  size: string;
  size_bytes: number;
  modified: string;
  modified_ts: number;
  type: string;
  url: string;
};

export default function BackupDatabase() {
  const { t } = useTranslation();
  const [files, setFiles] = useState<BackupFile[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let mounted = true;
    setLoading(true);
    setError(null);
    fetch(backupPhp, { credentials: 'same-origin' })
      .then((r) => r.json())
      .then((data) => {
        if (!mounted) return;
        if (data && data.error === false && Array.isArray(data.files)) {
          setFiles(data.files);
        } else if (data && data.error) {
          setError(data.message || 'Failed to load backups');
        } else {
          setFiles([]);
        }
      })
      .catch((e) => {
        if (!mounted) return;
        setError(e?.message || 'Fetch error');
      })
      .finally(() => {
        if (mounted) setLoading(false);
      });

    return () => {
      mounted = false;
    };
  }, []);

  return (
    <div className="flex flex-col items-center justify-center m-4 transition-colors">
      <div className="w-full bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 transition-colors">
        <h1 className="text-2xl font-bold mb-4 text-center flex items-center justify-center gap-2 text-blue-700 dark:text-blue-300">
          <i className="fa-solid fa-database text-green-500" aria-hidden="true"></i>
          {t('backup_database_title', 'Backup Database')}
        </h1>

        {loading ? (
          <div className="text-center text-sm text-gray-600 dark:text-gray-300">{t('loading', 'Loading...')}</div>
        ) : error ? (
          <div className="text-center text-sm text-red-600 dark:text-red-400">{error}</div>
        ) : files.length === 0 ? (
          <div className="text-center text-sm text-gray-600 dark:text-gray-300">
            {t('no_backups_found', 'No backups found')}
          </div>
        ) : (
          <div className="overflow-x-auto max-h-[60vh] overflow-y-auto">
            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
              <thead className="bg-gray-50 dark:bg-gray-900">
                <tr>
                  <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase sticky top-0 z-10 bg-gray-50 dark:bg-gray-900">
                    {t('name', 'Name')}
                  </th>
                  <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase sticky top-0 z-10 bg-gray-50 dark:bg-gray-900">
                    {t('size', 'Size')}
                  </th>
                  <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase sticky top-0 z-10 bg-gray-50 dark:bg-gray-900">
                    {t('modified', 'Modified')}
                  </th>
                  <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase sticky top-0 z-10 bg-gray-50 dark:bg-gray-900">
                    {t('path', 'Path')}
                  </th>
                  <th className="px-4 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase sticky top-0 z-10 bg-gray-50 dark:bg-gray-900">
                    {t('actions', 'Actions')}
                  </th>
                </tr>
              </thead>
              <tbody className="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                {files.map((f) => (
                  <tr key={f.path} className="hover:bg-gray-50 dark:hover:bg-gray-900">
                    <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-200">{f.name}</td>
                    <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-200">{f.size}</td>
                    <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-200">{timeAgo(f.modified, true)}</td>
                    <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{f.abs_path}</td>
                    <td className="px-4 py-3 text-sm text-center">
                      <div className="flex items-center justify-center gap-2">
                        <button
                          title={t('download', 'Download')}
                          className="inline-flex items-center px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-300"
                          onClick={() => (window.location.href = f.url)}>
                          <i className="fa-solid fa-download mr-2" aria-hidden="true"></i>
                          {t('download', 'Download')}
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
}
