import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import en from './locales/en.json';
import id from './locales/id.json';
import LanguageDetector from 'i18next-browser-languagedetector';
import { isViteDevServer } from './utils';
import { getSelectedLanguage } from './components/LanguageSwitcher';

const resources = {
  en: { translation: en },
  id: { translation: id }
};

i18n
  // detect user language
  // learn more: https://github.com/i18next/i18next-browser-languageDetector
  .use(LanguageDetector)
  // pass the i18n instance to react-i18next
  .use(initReactI18next)
  .init({
    resources,
    lng: getSelectedLanguage(), // use the selected language from localStorage
    fallbackLng: 'en',
    interpolation: {
      escapeValue: false // react already safes from xss
    },
    debug: isViteDevServer ?? false // enable debug mode in development
  });

export default i18n;
