import LogsSection from './admin/LogsSection';
import ManagerPoint from './admin/ManagerPoint';

export default function Admin() {
  return (
    <div className="min-h-screen bg-gray-50 dark:bg-gray-900 transition-colors">
      <ManagerPoint />
      <LogsSection />
    </div>
  );
}
