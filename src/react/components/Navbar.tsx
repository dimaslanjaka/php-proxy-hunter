import ThemeSwitcher from './ThemeSwitcher';

const Navbar = () => (
  <nav className="w-full bg-white dark:bg-gray-900 shadow-md">
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-16">
      <div className="flex items-center">
        <a href="/" className="text-2xl font-bold text-blue-600 dark:text-white">
          DX
        </a>
      </div>
      <div className="flex items-center space-x-4">
        <a
          href="/proxyManager.html"
          className="text-gray-700 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400 font-medium">
          Proxy
        </a>
        <a
          href="https://github.com/dimaslanjaka/php-proxy-hunter"
          className="text-gray-700 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400 font-medium"
          aria-label="GitHub Repository"
          target="_blank"
          rel="noopener noreferrer">
          <i className="fab fa-github text-2xl" aria-hidden="true"></i>
          <span className="sr-only">GitHub</span>
        </a>
        <ThemeSwitcher />
      </div>
    </div>
  </nav>
);

export default Navbar;
