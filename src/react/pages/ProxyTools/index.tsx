import React from 'react';
import { Routes, Route, NavLink, Navigate } from 'react-router-dom';
import ProxySubmission from '../ProxyList/ProxySubmission';
import ProxyExtractor from '../ProxyList/ProxyExtractor';
import LogViewer from '../ProxyList/LogViewer';

export default function ProxyToolsRouter() {
  return (
    <div className="mx-2">
      <div className="my-4">
        <div className="inline-flex items-center rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-1 gap-1">
          <NavLink
            to="/proxy-tools/submit"
            className={({ isActive }) =>
              `px-3 py-2 rounded-md text-sm font-medium transition-colors ${
                isActive
                  ? 'bg-indigo-600 text-white'
                  : 'text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800'
              }`
            }>
            <i className="fa-duotone fa-magnifying-glass mr-2" aria-hidden="true" />
            Proxy Checker
          </NavLink>

          <NavLink
            to="/proxy-tools/extract"
            className={({ isActive }) =>
              `px-3 py-2 rounded-md text-sm font-medium transition-colors ${
                isActive
                  ? 'bg-cyan-700 text-white'
                  : 'text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800'
              }`
            }>
            <i className="fa-duotone fa-network-wired mr-2" aria-hidden="true" />
            Proxy Extractor
          </NavLink>

          <NavLink
            to="/proxy-tools/logs"
            className={({ isActive }) =>
              `px-3 py-2 rounded-md text-sm font-medium transition-colors ${
                isActive
                  ? 'bg-gray-800 text-white'
                  : 'text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800'
              }`
            }>
            <i className="fa-duotone fa-list mr-2" aria-hidden="true" />
            Logs
          </NavLink>

          <NavLink
            to="/proxy-list"
            className={({ isActive }) =>
              `px-3 py-2 rounded-md text-sm font-medium transition-colors ${
                isActive
                  ? 'bg-indigo-600 text-white'
                  : 'text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800'
              }`
            }>
            <i className="fa-duotone fa-table-list mr-2" aria-hidden="true" />
            Proxy List
          </NavLink>
        </div>
      </div>

      <Routes>
        <Route index element={<Navigate to="submit" replace />} />
        <Route
          path="submit"
          element={
            <>
              <ProxySubmission />
              <LogViewer />
            </>
          }
        />
        <Route path="extract" element={<ProxyExtractor />} />
        <Route path="logs" element={<LogViewer />} />
      </Routes>
    </div>
  );
}
