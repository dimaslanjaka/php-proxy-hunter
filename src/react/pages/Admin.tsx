import React, { useState } from 'react';

const users = [
  { id: 1, name: 'User One' },
  { id: 2, name: 'User Two' },
  { id: 3, name: 'User Three' }
];

export default function Admin() {
  const [selectedUser, setSelectedUser] = React.useState<number>(users[0].id);
  const [saldo, setSaldo] = useState('');

  const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    // TODO: Implement saldo addition logic
    alert(`Added saldo ${saldo} to user ${selectedUser}`);
  };

  return (
    <div className="min-h-screen bg-gray-50 dark:bg-gray-900 flex flex-col items-center justify-center p-4 transition-colors">
      <div className="w-full max-w-md bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8 transition-colors">
        <h1 className="text-2xl font-bold mb-6 text-center flex items-center justify-center gap-2 text-blue-700 dark:text-blue-300">
          <i className="text-green-500 dark:text-green-400 fa fa-plus"></i>
          Add Saldo
        </h1>
        <form onSubmit={handleSubmit} className="space-y-6">
          <div>
            <label htmlFor="user" className="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-200">
              Select User
            </label>
            <select
              id="user"
              className="block w-full rounded-md border-gray-300 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 dark:focus:border-blue-400 dark:focus:ring-blue-900 focus:ring-opacity-50 transition-colors"
              value={selectedUser}
              onChange={(e) => setSelectedUser(Number(e.target.value))}>
              {users.map((user) => (
                <option key={user.id} value={user.id}>
                  {user.name}
                </option>
              ))}
            </select>
          </div>
          <div>
            <label htmlFor="saldo" className="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-200">
              Saldo Amount
            </label>
            <input
              id="saldo"
              type="number"
              className="block w-full rounded-md border-gray-300 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 dark:focus:border-blue-400 dark:focus:ring-blue-900 focus:ring-opacity-50 transition-colors"
              value={saldo}
              onChange={(e) => setSaldo(e.target.value)}
              required
              min="0"
            />
          </div>
          <button
            type="submit"
            className="w-full flex items-center justify-center gap-2 px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-md hover:bg-blue-700 dark:hover:bg-blue-800 transition-colors font-semibold">
            <i className="fa fa-plus"></i> Add Saldo
          </button>
        </form>
      </div>
    </div>
  );
}
