import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import en from './locales/en.json';
import id from './locales/id.json';
import { isViteDevServer } from './utils';
import axios from 'axios';

const resources = {
  en: { translation: en },
  id: { translation: id }
};

i18n
  // pass the i18n instance to react-i18next
  .use(initReactI18next)
  .init({
    resources,
    lng: getSelectedLanguage() || 'en', // use the selected language from localStorage
    fallbackLng: 'en',
    interpolation: {
      escapeValue: false // react already safes from xss
    },
    debug: isViteDevServer ?? false // enable debug mode in development
  });

export default i18n;

/**
 * Get the currently selected language from localStorage, or 'en' if not set.
 * @returns The selected language code (e.g., 'en', 'id').
 */
export function getSelectedLanguage() {
  return localStorage.getItem('i18nextLng');
}

/**
 * Set the selected language in localStorage.
 * @param lang - The language code to save (e.g., 'en', 'id').
 */
export function setSelectedLanguage(lang: string): void {
  localStorage.setItem('i18nextLng', lang);
}

/**
 * Detect the user's language preferences using browser and free geolocation APIs (ipapi, ipinfo, ipwho.is, ip-api).
 * Uses localStorage to cache the IP geolocation result for 1 hour.
 *
 * @returns The detected language code (e.g., 'en', 'id').
 */
export async function detectUserLanguage() {
  const CACHE_KEY = 'ipapiCache';
  const CACHE_TTL = 60 * 60 * 1000; // 1 hour in ms

  // browser language is always live
  const browserLang = navigator.language || 'en';

  // try cached ipapi response
  let geoLookupData: any = null;
  const cached = localStorage.getItem(CACHE_KEY);
  if (cached) {
    const parsed = JSON.parse(cached);
    if (Date.now() - parsed.timestamp < CACHE_TTL) {
      geoLookupData = parsed.value;
    }
  }

  // if no valid cache, fetch from ipapi, then fallback to ipinfo, ipwhois, ip-api
  if (!geoLookupData) {
    let success = false;
    // Try ipapi
    try {
      const res = await axios.get('https://ipapi.co/json/');
      geoLookupData = res.data;
      success = true;
    } catch (e) {
      console.warn('ipapi failed, trying ipinfo...', e);
    }
    // Try ipinfo.io
    if (!success) {
      try {
        const res = await axios.get('https://ipinfo.io/json');
        geoLookupData = { country_code: res.data.country };
        success = true;
      } catch (e) {
        console.warn('ipinfo failed, trying ipwho.is...', e);
      }
    }
    // Try ipwho.is
    if (!success) {
      try {
        const res = await axios.get('https://ipwho.is/');
        geoLookupData = { country_code: res.data.country_code };
        success = true;
      } catch (e) {
        console.warn('ipwho.is failed, trying ip-api...', e);
      }
    }
    // Try ip-api.com
    if (!success) {
      try {
        const res = await axios.get('http://ip-api.com/json/');
        geoLookupData = { country_code: res.data.countryCode };
        success = true;
      } catch (e) {
        console.error('All geo lookups failed', e);
      }
    }
    // cache only the geoLookupData response
    if (geoLookupData) {
      localStorage.setItem(
        CACHE_KEY,
        JSON.stringify({
          value: geoLookupData,
          timestamp: Date.now()
        })
      );
    }
  }

  // derive countryLang from ipapi response
  let countryLang: string | undefined | null = undefined;
  if (geoLookupData?.country_code) {
    countryLang = geoLookupData.country_code.toLowerCase();
    console.log('Detected country code:', countryLang);
  }
  if (!countryLang) {
    // fallback to browser language if no country code found
    countryLang = browserLang.split('-')[0]; // use only the language part
    console.warn('No country code found, using browser language:', countryLang);
  }
  if (countryLang.includes('-')) {
    // if countryLang has region, use only the language part
    countryLang = countryLang.split('-')[0];
    console.warn('Country code has region, using only language:', countryLang);
  }

  return countryLang;
}
