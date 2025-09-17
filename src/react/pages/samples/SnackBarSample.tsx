import React from 'react';
import { useSnackbar } from '../../components/Snackbar';

function SnackBarSample() {
  const { showSnackbar } = useSnackbar();

  return (
    <div className="flex flex-col items-center justify-center min-h-screen bg-gray-50 dark:bg-gray-900 p-8 gap-4">
      <h1 className="text-2xl font-bold mb-4 text-gray-900 dark:text-gray-100">Snackbar Sample</h1>
      <div className="flex flex-wrap gap-2">
        <button
          className="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700"
          onClick={() => showSnackbar({ message: 'Default notification!' })}>
          Show Default
        </button>
        <button
          className="px-4 py-2 rounded bg-green-600 text-white hover:bg-green-700"
          onClick={() => showSnackbar({ message: 'Success notification!', type: 'success' })}>
          Show Success
        </button>
        <button
          className="px-4 py-2 rounded bg-red-600 text-white hover:bg-red-700"
          onClick={() => showSnackbar({ message: 'Danger notification!', type: 'danger' })}>
          Show Danger
        </button>
        <button
          className="px-4 py-2 rounded bg-orange-500 text-white hover:bg-orange-600"
          onClick={() => showSnackbar({ message: 'Warning notification!', type: 'warning' })}>
          Show Warning
        </button>
      </div>
    </div>
  );
}

export default SnackBarSample;
