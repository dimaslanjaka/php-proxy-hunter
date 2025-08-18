import React from 'react';
import { useTranslation } from 'react-i18next';

/**
 * Get the currently selected language from localStorage, or 'en' if not set.
 * @returns {string} The selected language code (e.g., 'en', 'id').
 */
export function getSelectedLanguage(): string {
  return localStorage.getItem('i18nextLng') || 'en';
}

/**
 * Set the selected language in localStorage.
 * @param lang - The language code to save (e.g., 'en', 'id').
 */
export function setSelectedLanguage(lang: string): void {
  localStorage.setItem('i18nextLng', lang);
}

const LanguageSwitcher: React.FC = () => {
  const { i18n } = useTranslation();

  const changeLanguage = (lng: string) => {
    i18n.changeLanguage(lng);
    setSelectedLanguage(lng);
  };

  React.useEffect(() => {
    // On mount, set language from localStorage if available
    const savedLang = getSelectedLanguage();
    if (savedLang && savedLang !== i18n.language) {
      i18n.changeLanguage(savedLang);
    }
  }, [i18n]);

  return (
    <div className="flex items-center my-4">
      <i className="fa-solid fa-language mr-2 text-lg"></i>
      <select
        className="block px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-600 dark:focus:bg-gray-700 transition"
        value={i18n.language}
        onChange={(e) => changeLanguage(e.target.value)}
        aria-label="Select language">
        <option value="en">English</option>
        <option value="id">Indonesia</option>
      </select>
    </div>
  );
};

export default LanguageSwitcher;
