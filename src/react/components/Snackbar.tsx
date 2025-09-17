import React, { createContext, useContext, useState, ReactNode, useCallback } from 'react';

export type SnackbarType = 'default' | 'success' | 'danger' | 'warning';

interface SnackbarOptions {
  message: string;
  type?: SnackbarType;
  duration?: number; // ms
}

interface SnackbarProviderProps {
  children: ReactNode;
  stackable?: boolean;
  maxSnackbars?: number;
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

export const SnackbarProvider = ({ children, stackable = false, maxSnackbars = 5 }: SnackbarProviderProps) => {
  // If stackable, manage an array of snackbars
  const [snackbars, setSnackbars] = useState<
    Array<SnackbarOptions & { id: number; visible: boolean; timeoutId?: NodeJS.Timeout }>
  >([]);
  // If not stackable, manage a single snackbar
  const [snackbar, setSnackbar] = useState<(SnackbarOptions & { visible: boolean; timeoutId?: NodeJS.Timeout }) | null>(
    null
  );
  const snackbarId = React.useRef(0);

  const showSnackbar = useCallback(
    (options: SnackbarOptions) => {
      if (stackable) {
        // Add new snackbar to the stack
        setSnackbars((prev) => {
          // Remove excess snackbars if over max
          const next = prev.length >= maxSnackbars ? prev.slice(1) : prev;
          const id = ++snackbarId.current;
          const timeoutId = setTimeout(() => {
            setSnackbars((snacks) => snacks.filter((s) => s.id !== id));
          }, options.duration || 3000);
          return [
            ...next,
            {
              ...options,
              type: options.type || 'default',
              duration: options.duration || 3000,
              visible: true,
              id,
              timeoutId
            }
          ];
        });
      } else {
        // Single snackbar logic (old behavior)
        if (snackbar && snackbar.timeoutId) clearTimeout(snackbar.timeoutId);
        const timeoutId = setTimeout(() => {
          setSnackbar((prev) => prev && { ...prev, visible: false });
        }, options.duration || 3000);
        setSnackbar({
          ...options,
          type: options.type || 'default',
          duration: options.duration || 3000,
          visible: true,
          timeoutId
        });
      }
    },
    [stackable, maxSnackbars, snackbar]
  );

  const handleClose = (id?: number) => {
    if (stackable && typeof id === 'number') {
      setSnackbars((prev) => prev.filter((s) => s.id !== id));
    } else if (!stackable && snackbar) {
      setSnackbar((prev) => prev && { ...prev, visible: false });
      if (snackbar && snackbar.timeoutId) clearTimeout(snackbar.timeoutId);
    }
  };

  return (
    <SnackbarContext.Provider value={{ showSnackbar }}>
      {children}
      {stackable ? (
        <div className="fixed bottom-5 right-5 z-50 flex flex-col gap-2 items-end">
          {snackbars.map(
            (snack) =>
              snack.visible && (
                <div
                  key={snack.id}
                  className={`self-end flex items-center max-w-xs p-4 rounded-lg shadow-sm ${typeStyles[snack.type || 'default']}`}
                  role="alert">
                  <div className="ms-3 text-sm font-normal flex-1">{snack.message}</div>
                  <button
                    type="button"
                    className="ms-auto -mx-1.5 -my-1.5 bg-white text-gray-400 hover:text-gray-900 rounded-lg focus:ring-2 focus:ring-gray-300 p-1.5 hover:bg-gray-100 inline-flex items-center justify-center h-8 w-8 dark:text-gray-500 dark:hover:text-white dark:bg-gray-800 dark:hover:bg-gray-700"
                    aria-label="Close"
                    onClick={() => handleClose(snack.id)}>
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
              )
          )}
        </div>
      ) : (
        snackbar &&
        snackbar.visible && (
          <div
            className={`fixed bottom-5 right-5 z-50 flex items-center max-w-xs p-4 rounded-lg shadow-sm ${typeStyles[snackbar.type || 'default']}`}
            role="alert">
            <div className="ms-3 text-sm font-normal flex-1">{snackbar.message}</div>
            <button
              type="button"
              className="ms-auto -mx-1.5 -my-1.5 bg-white text-gray-400 hover:text-gray-900 rounded-lg focus:ring-2 focus:ring-gray-300 p-1.5 hover:bg-gray-100 inline-flex items-center justify-center h-8 w-8 dark:text-gray-500 dark:hover:text-white dark:bg-gray-800 dark:hover:bg-gray-700"
              aria-label="Close"
              onClick={() => handleClose()}>
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
        )
      )}
    </SnackbarContext.Provider>
  );
};
