/**
 * Proxy type to Tailwind color class mappings
 * These strings must be kept as static strings for Tailwind purge/scanning to work
 */
export const proxyTypeColorClasses: Record<string, string> = {
  http: 'bg-blue-200 text-blue-900 dark:bg-blue-400/20 dark:text-blue-100 border-blue-300 dark:border-blue-500',
  https: 'bg-cyan-200 text-cyan-900 dark:bg-cyan-400/20 dark:text-cyan-100 border-cyan-300 dark:border-cyan-500',
  socks4:
    'bg-purple-200 text-purple-900 dark:bg-purple-400/20 dark:text-purple-100 border-purple-300 dark:border-purple-500',
  socks4a: 'bg-pink-200 text-pink-900 dark:bg-pink-400/20 dark:text-pink-100 border-pink-300 dark:border-pink-500',
  socks5:
    'bg-orange-200 text-orange-900 dark:bg-orange-400/20 dark:text-orange-100 border-orange-300 dark:border-orange-500',
  socks5h:
    'bg-amber-200 text-amber-900 dark:bg-amber-400/20 dark:text-amber-100 border-amber-300 dark:border-amber-500',
  ssl: 'bg-green-200 text-green-900 dark:bg-green-400/20 dark:text-green-100 border-green-300 dark:border-green-500',
  default: 'bg-gray-200 text-gray-900 dark:bg-gray-700 dark:text-gray-100 border-gray-300 dark:border-gray-600'
};

export function getProxyTypeColorClass(type: string): string {
  const normalizedType = type.toLowerCase();
  return proxyTypeColorClasses[normalizedType] || proxyTypeColorClasses.default;
}
