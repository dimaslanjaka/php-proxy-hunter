import React, { createContext, useContext, useState, ReactNode, useCallback } from 'react';

export type SnackbarType = 'default' | 'success' | 'danger' | 'warning';

interface SnackbarOptions {
  message: string;
  type?: SnackbarType;
  duration?: number; // ms
}

interface SnackbarContextProps {
  showSnackbar: (options: SnackbarOptions) => void;
}

const SnackbarContext = createContext<SnackbarContextProps | undefined>(undefined);

export const useSnackbar = () => {
  const ctx = useContext(SnackbarContext);
  if (!ctx) throw new Error('useSnackbar must be used within a SnackbarProvider');
  return ctx;
};

const typeStyles: Record<SnackbarType, string> = {
  default: 'text-gray-500 bg-white dark:text-gray-400 dark:bg-gray-800',
  success: 'text-green-500 bg-white dark:text-green-400 dark:bg-gray-800',
  danger: 'text-red-500 bg-white dark:text-red-400 dark:bg-gray-800',
  warning: 'text-orange-500 bg-white dark:text-orange-400 dark:bg-gray-800'
};

export const SnackbarProvider = ({ children }: { children: ReactNode }) => {
  const [snackbar, setSnackbar] = useState<SnackbarOptions & { visible: boolean }>({
    message: '',
    type: 'default',
    duration: 3000,
    visible: false
  });
  const [timeoutId, setTimeoutId] = useState<NodeJS.Timeout | null>(null);

  const showSnackbar = useCallback(
    (options: SnackbarOptions) => {
      if (timeoutId) clearTimeout(timeoutId);
      setSnackbar({
        message: options.message,
        type: options.type || 'default',
        duration: options.duration || 3000,
        visible: true
      });
      const id = setTimeout(() => {
        setSnackbar((prev) => ({ ...prev, visible: false }));
      }, options.duration || 3000);
      setTimeoutId(id);
    },
    [timeoutId]
  );

  const handleClose = () => {
    setSnackbar((prev) => ({ ...prev, visible: false }));
    if (timeoutId) clearTimeout(timeoutId);
  };

  return (
    <SnackbarContext.Provider value={{ showSnackbar }}>
      {children}
      {snackbar.visible && (
        <div
          className={`fixed bottom-5 right-5 z-50 flex items-center w-full max-w-xs p-4 rounded-lg shadow-sm ${typeStyles[snackbar.type || 'default']}`}
          role="alert">
          <div className="ms-3 text-sm font-normal flex-1">{snackbar.message}</div>
          <button
            type="button"
            className="ms-auto -mx-1.5 -my-1.5 bg-white text-gray-400 hover:text-gray-900 rounded-lg focus:ring-2 focus:ring-gray-300 p-1.5 hover:bg-gray-100 inline-flex items-center justify-center h-8 w-8 dark:text-gray-500 dark:hover:text-white dark:bg-gray-800 dark:hover:bg-gray-700"
            aria-label="Close"
            onClick={handleClose}>
            <span className="sr-only">Close</span>
            <svg
              className="w-3 h-3"
              aria-hidden="true"
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 14 14">
              <path
                stroke="currentColor"
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth="2"
                d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"
              />
            </svg>
          </button>
        </div>
      )}
    </SnackbarContext.Provider>
  );
};
