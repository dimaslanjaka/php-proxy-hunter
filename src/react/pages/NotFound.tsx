import React from 'react';
import Link from '../components/Link';

const NotFound: React.FC = () => (
  <div className="flex flex-col items-center justify-center min-h-[60vh] py-8 px-4">
    <div className="flex flex-col items-center gap-2 bg-white dark:bg-gray-900 rounded-lg shadow-lg dark:shadow-white p-8 border border-gray-200 dark:border-gray-700">
      <div className="text-6xl text-red-500 mb-2">
        <i className="fa-duotone fa-circle-exclamation"></i>
      </div>
      <h1 className="text-4xl font-bold text-gray-800 dark:text-white mb-1">404</h1>
      <h2 className="text-xl text-gray-600 dark:text-gray-300 mb-2">Page Not Found</h2>
      <p className="text-gray-500 dark:text-gray-400 mb-4">The page you are looking for does not exist.</p>
      <Link
        href="/"
        className="inline-flex items-center gap-2 px-4 py-2 text-white bg-blue-600 hover:bg-blue-700 rounded transition-colors font-medium shadow dark:shadow-white focus:outline-none focus:ring-2 focus:ring-blue-400">
        <i className="fa-duotone fa-house"></i>
        Go to Home
      </Link>
    </div>
  </div>
);

export default NotFound;
