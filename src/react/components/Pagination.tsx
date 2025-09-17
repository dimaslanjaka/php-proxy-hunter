import React from 'react';

interface PaginationProps {
  page: number;
  perPage: number;
  count: number;
  onPageChange: (page: number) => void;
}

export const Pagination: React.FC<PaginationProps> = ({ page, perPage, count, onPageChange }) => {
  const totalPages = Math.max(1, Math.ceil(count / perPage));

  const handlePrev = () => {
    if (page > 1) onPageChange(page - 1);
  };
  const handleNext = () => {
    if (page < totalPages) onPageChange(page + 1);
  };

  return (
    <div className="flex items-center justify-center gap-2 mt-4">
      <button
        className="px-3 py-1 rounded bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 disabled:opacity-50"
        onClick={handlePrev}
        disabled={page === 1}>
        Prev
      </button>
      <span className="mx-2 text-sm text-gray-700 dark:text-gray-200">
        Page {page} of {totalPages}
      </span>
      <button
        className="px-3 py-1 rounded bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 disabled:opacity-50"
        onClick={handleNext}
        disabled={page === totalPages}>
        Next
      </button>
    </div>
  );
};
