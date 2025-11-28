import React, { useEffect } from 'react';
import { DataTable } from 'simple-datatables';
import tableData from './advancedTableData.json';

interface elementNodeType {
  nodeName: string;
  attributes?: { [key: string]: string };
  childNodes?: nodeType[];
  checked?: boolean;
  value?: string | number;
  selected?: boolean;
}

interface textNodeType {
  nodeName: '#text' | '#comment';
  data: string;
  childNodes?: never;
}

type nodeType = elementNodeType | textNodeType;

const AdvancedDataTable: React.FC = () => {
  useEffect(() => {
    const dataTable = document.getElementById('advanced-data-table') as HTMLTableElement;
    if (dataTable) {
      new DataTable(dataTable, {
        data: tableData,
        tableRender: (_data: any, table: elementNodeType, type: string) => {
          if (type === 'print') {
            return table;
          }
          const tHead = table.childNodes?.[0];
          const filterHeaders = {
            nodeName: 'TR',
            attributes: { class: 'search-filtering-row' },
            childNodes: tHead?.childNodes?.[0].childNodes?.map((_th: any, index: number) => ({
              nodeName: 'TH',
              childNodes: [
                {
                  nodeName: 'INPUT',
                  attributes: {
                    class: 'datatable-input',
                    type: 'search',
                    'data-columns': '[' + index + ']'
                  }
                }
              ]
            }))
          };
          tHead?.childNodes?.push(filterHeaders);
          return table;
        },
        columns: [
          {
            select: 6,
            render: (data: any, td: any) => {
              if (td && td.attributes) {
                td.attributes.class = (td.attributes.class || '') + ' min-w-[400px]';
              }
              return td;
            }
          }
        ],
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
      <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
        <h1 className="text-4xl font-bold text-gray-900 dark:text-white mb-2">Advanced DataTable</h1>
        <p className="text-lg text-gray-600 dark:text-gray-400">
          Responsive table with comprehensive data - search, sort, and paginate information
        </p>

        <div className="mt-8 bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
          <div className="p-6">
            <div className="mb-4">
              <h2 className="text-2xl font-bold text-gray-900 dark:text-white mb-2">Financial Data</h2>
              <p className="text-gray-600 dark:text-gray-400">
                Stock market data with comprehensive company information
              </p>
            </div>

            <div className="overflow-x-auto">
              <table id="advanced-data-table" className="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                <thead className="text-xs text-gray-700 uppercase bg-gray-100 dark:bg-gray-700 dark:text-gray-400">
                  <tr>
                    <th>
                      <span className="flex items-center">
                        Company Name
                        <i className="fa-solid fa-sort ms-1 text-xs opacity-60"></i>
                      </span>
                    </th>
                    <th>
                      <span className="flex items-center">
                        Ticker
                        <i className="fa-solid fa-sort ms-1 text-xs opacity-60"></i>
                      </span>
                    </th>
                    <th>
                      <span className="flex items-center">
                        Stock Price
                        <i className="fa-solid fa-sort ms-1 text-xs opacity-60"></i>
                      </span>
                    </th>
                    <th>
                      <span className="flex items-center">
                        Market Cap
                        <i className="fa-solid fa-sort ms-1 text-xs opacity-60"></i>
                      </span>
                    </th>
                    <th>
                      <span className="flex items-center">
                        PE Ratio
                        <i className="fa-solid fa-sort ms-1 text-xs opacity-60"></i>
                      </span>
                    </th>
                    <th>
                      <span className="flex items-center">
                        Sector
                        <i className="fa-solid fa-sort ms-1 text-xs opacity-60"></i>
                      </span>
                    </th>
                    <th>
                      <span className="flex items-center">
                        Description
                        <i className="fa-solid fa-sort ms-1 text-xs opacity-60"></i>
                      </span>
                    </th>
                  </tr>
                </thead>
                <tbody />
              </table>
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

export default AdvancedDataTable;
