import React, { useEffect } from 'react';
import { DataTable } from 'simple-datatables';
import tableData from './defaultTableData.json';

const SortingDataTable: React.FC = () => {
  useEffect(() => {
    const sortingTable = document.getElementById('sorting-table') as HTMLTableElement;
    if (sortingTable) {
      new DataTable(sortingTable, {
        data: tableData,
        perPageSelect: [5, 10, 15, 20],
        perPage: 10,
        sortable: true,
        searchable: false,
        labels: {
          placeholder: 'Search data...',
          perPage: 'entries per page',
          noRows: 'No entries found',
          info: 'Showing {start} to {end} of {rows} entries'
        }
      });
    }
  }, []);

  return (
    <div className="p-6">
      <div className="mb-4">
        <h2 className="text-2xl font-bold text-gray-900 dark:text-white mb-2">Sorting DataTable</h2>
        <p className="text-gray-600 dark:text-gray-400">
          Click on column headers to sort data in ascending or descending order
        </p>
      </div>

      <div className="overflow-x-auto">
        <table id="sorting-table" className="w-full text-sm text-left text-gray-500 dark:text-gray-400" />
      </div>
    </div>
  );
};

export default SortingDataTable;
