import React from 'react';
import DefaultDataTable from './DefaultDataTable';

const DataTablesExamples: React.FC = () => {
  return (
    <main className="min-h-screen bg-gray-50 dark:bg-gray-900 py-8">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h1 className="text-4xl font-bold text-gray-900 dark:text-white mb-2">DataTables Examples</h1>
        <p className="text-lg text-gray-600 dark:text-gray-400">
          Interactive data table components built with Flowbite and simple-datatables
        </p>

        <div className="mt-8 bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
          <DefaultDataTable />
        </div>
      </div>
    </main>
  );
};

export default DataTablesExamples;
