import React, { useEffect } from 'react';
import { DataTable } from 'simple-datatables';
import tableData from './defaultTableData.json';

const DefaultDataTable: React.FC = () => {
  useEffect(() => {
    const selectionTable = document.getElementById('selection-table') as HTMLTableElement;
    if (selectionTable) {
      new DataTable(selectionTable, {
        data: tableData,
        perPageSelect: [5, 10, 15, 20],
        perPage: 10,
        sortable: true,
        searchable: true
      });
    }
  }, []);

  return (
    <div className="p-6">
      <div className="mb-4">
        <h2 className="text-2xl font-bold text-gray-900 dark:text-white mb-2">Default DataTable</h2>
        <p className="text-gray-600 dark:text-gray-400">
          Basic datatable with sorting, pagination, and search functionality
        </p>
      </div>

      <div className="overflow-x-auto">
        <table id="selection-table" className="w-full text-sm text-left text-gray-500 dark:text-gray-400" />
      </div>
    </div>
  );
};

export default DefaultDataTable;
