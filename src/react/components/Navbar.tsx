import Link from './Link';
import ThemeSwitcher from './ThemeSwitcher';

const Navbar = () => (
  <nav className="w-full bg-white dark:bg-gray-900 shadow-md">
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-16">
      <div className="flex items-center">
        <Link href="/" className="text-2xl font-bold text-blue-600 dark:text-white">
          DX
        </Link>
      </div>
      <div className="flex items-center space-x-4">
        <Link
          href="/proxyManager.html"
          className="text-gray-700 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400 font-medium">
          Proxy
        </Link>
        <Link
          href="/login"
          className="text-gray-700 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400 font-medium"
          aria-label="Login">
          <i className="fas fa-sign-in-alt text-2xl" aria-hidden="true"></i>
          <span className="sr-only">Login</span>
        </Link>
        <Link
          href="https://github.com/dimaslanjaka/php-proxy-hunter"
          className="text-gray-700 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400 font-medium"
          aria-label="GitHub Repository"
          target="_blank"
          rel="noopener noreferrer">
          <i className="fab fa-github text-2xl" aria-hidden="true"></i>
          <span className="sr-only">GitHub</span>
        </Link>
        <ThemeSwitcher />
      </div>
    </div>
  </nav>
);

export default Navbar;
