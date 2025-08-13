import React from 'react';
import { useTheme } from './ThemeContext';

const ThemeSwitcher: React.FC = () => {
  const { theme, toggleTheme } = useTheme();
  // Update <html> class for Tailwind dark mode and log theme
  React.useEffect(() => {
    const root = document.documentElement;
    if (theme === 'dark') {
      root.classList.add('dark');
    } else {
      root.classList.remove('dark');
    }
    // console.log('Current theme:', theme);
  }, [theme]);
  return (
    <button
      type="button"
      onClick={() => {
        // console.log('Button clicked');
        toggleTheme();
      }}
      aria-label="Toggle dark mode"
      className="relative w-14 h-8 flex items-center bg-gray-200 dark:bg-gray-700 rounded-full p-1 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500">
      <span className="sr-only">Toggle dark mode</span>
      <span className="absolute left-2 text-lg text-gray-500 dark:text-gray-300">
        <i className="fa-duotone fa-moon"></i>
      </span>
      <span className="absolute right-2 text-lg text-yellow-400 dark:text-yellow-300">
        <i className="fa-duotone fa-sun"></i>
      </span>
      <span
        key={theme} // force re-render for animation
        className={`absolute top-1 left-1 w-6 h-6 rounded-full bg-white dark:bg-gray-900 shadow-md transform transition-transform duration-300 ${
          theme === 'dark' ? 'translate-x-6' : ''
        }`}
      />
    </button>
  );
};

export default ThemeSwitcher;
