import React from 'react';
import { useTranslation } from 'react-i18next';
import { detectUserLanguage, getSelectedLanguage, setSelectedLanguage } from '../i18n';

const LanguageSwitcher: React.FC = () => {
  const { i18n } = useTranslation();

  const changeLanguage = (lng: string) => {
    i18n.changeLanguage(lng);
    setSelectedLanguage(lng);
  };

  React.useEffect(() => {
    // On mount, set language from localStorage if available
    const savedLang = getSelectedLanguage();
    if (!savedLang) {
      // If no saved language, detect user language
      detectUserLanguage().then((detectedLang) => {
        if (detectedLang && detectedLang !== i18n.language) {
          i18n.changeLanguage(detectedLang);
          setSelectedLanguage(detectedLang);
        }
      });
    } else if (savedLang && savedLang !== i18n.language) {
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
