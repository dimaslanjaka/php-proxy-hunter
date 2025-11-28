import React from 'react';
import { Link } from 'react-router-dom';

interface TableLink {
  title: string;
  description: string;
  path: string;
  icon: React.ReactNode;
}

const DataTablesExamples: React.FC = () => {
  const tables: TableLink[] = [
    {
      title: 'Default DataTable',
      description: 'Basic datatable with sorting, pagination, and search functionality',
      path: '/examples/tables/default',
      icon: (
        <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"
          />
        </svg>
      )
    },
    {
      title: 'Sorting DataTable',
      description: 'Click on column headers to sort data in ascending or descending order',
      path: '/examples/tables/sorting',
      icon: (
        <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"
          />
        </svg>
      )
    },
    {
      title: 'Search DataTable',
      description: 'Search across all columns to filter company data',
      path: '/examples/tables/search',
      icon: (
        <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
          />
        </svg>
      )
    },
    {
      title: 'Advanced DataTable',
      description: 'Comprehensive data table with multiple columns and advanced filtering options',
      path: '/examples/tables/advanced',
      icon: (
        <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M9 3v12m6-12v12m6-12v12M3 9h18M3 15h18"
          />
        </svg>
      )
    }
  ];

  return (
    <main className="min-h-screen bg-gray-50 dark:bg-gray-900 py-8">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="mb-8">
          <h1 className="text-4xl font-bold text-gray-900 dark:text-white mb-2">DataTables Examples</h1>
          <p className="text-lg text-gray-600 dark:text-gray-400">
            Interactive data table components built with Flowbite and simple-datatables
          </p>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          {tables.map((table) => (
            <Link
              key={table.path}
              to={table.path}
              className="group bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 hover:shadow-lg hover:border-blue-400 dark:hover:border-blue-500 transition transform hover:scale-105">
              <div className="p-6">
                <div className="flex items-center justify-center w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-lg text-blue-600 dark:text-blue-400 mb-4 group-hover:bg-blue-200 dark:group-hover:bg-blue-800 transition">
                  {table.icon}
                </div>
                <h3 className="text-xl font-bold text-gray-900 dark:text-white mb-2 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition">
                  {table.title}
                </h3>
                <p className="text-gray-600 dark:text-gray-400 text-sm">{table.description}</p>
                <div className="mt-4 inline-block">
                  <span className="inline-flex items-center text-blue-600 dark:text-blue-400 font-semibold group-hover:translate-x-1 transition transform">
                    View Example
                    <svg className="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                    </svg>
                  </span>
                </div>
              </div>
            </Link>
          ))}
        </div>
      </div>
    </main>
  );
};

export default DataTablesExamples;
