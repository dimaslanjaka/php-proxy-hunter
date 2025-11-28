import React, { useEffect } from 'react';
import { DataTable } from 'simple-datatables';
import tableData from './searchTableData.json';

const SearchDataTable: React.FC = () => {
  useEffect(() => {
    const searchTable = document.getElementById('search-table') as HTMLTableElement;
    if (searchTable) {
      new DataTable(searchTable, {
        data: tableData,
        searchable: true,
        perPage: 10,
        sortable: true,
        sensitivity: 'base',
        perPageSelect: [5, 10, 15, 20]
      });
    }
  }, []);

  return (
    <main className="min-h-screen bg-gray-50 dark:bg-gray-900 py-8">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h1 className="text-4xl font-bold text-gray-900 dark:text-white mb-2">Search DataTable Example</h1>
        <p className="text-lg text-gray-600 dark:text-gray-400">Search across all columns to filter data</p>

        <div className="mt-8 bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
          <div className="p-6">
            <div className="mb-4">
              <h2 className="text-2xl font-bold text-gray-900 dark:text-white mb-2">Search DataTable</h2>
              <p className="text-gray-600 dark:text-gray-400">Search across all columns to filter company data</p>
            </div>

            <div className="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
              <table id="search-table" className="w-full text-sm text-left text-gray-500 dark:text-gray-400" />
            </div>
          </div>
        </div>

        <div className="mt-8">
          <a
            href="/examples/tables"
            className="inline-block px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition">
            ← Back to Tables
          </a>
        </div>
      </div>
    </main>
  );
};

export default SearchDataTable;
