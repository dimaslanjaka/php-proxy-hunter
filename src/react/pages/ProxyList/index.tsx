import React from 'react';
import ServerSide from './ServerSide';
import ApiUsage from './ApiUsage';
import { useTranslation } from 'react-i18next';
import RecaptchaV2 from '../../components/RecaptchaV2';
import AdSense from '../../components/AdSense';
import { NavLink } from 'react-router-dom';
import UniqueIpList from './UniqueIpList';
import Summary from './Summary';
import WorkingJson from './WorkingJson';

export default function ProxyList() {
  const { t } = useTranslation();
  const [recaptchaOpen, setRecaptchaOpen] = React.useState(false);

  const [listTab, setListTab] = React.useState<'server' | 'unique-ip' | 'working-json'>(() => {
    const saved = localStorage.getItem('proxyList_listTab');
    return saved === 'server' || saved === 'unique-ip' || saved === 'working-json' ? saved : 'working-json';
  });

  React.useEffect(() => {
    localStorage.setItem('proxyList_listTab', listTab);
  }, [listTab]);

  let listTabContent: React.ReactNode;
  if (listTab === 'server') {
    listTabContent = <ServerSide />;
  } else if (listTab === 'unique-ip') {
    listTabContent = <UniqueIpList />;
  } else {
    listTabContent = <WorkingJson />;
  }

  return (
    <div className="mx-2">
      <section className="my-4">
        <div className="w-full">
          <h2 id="recaptcha-heading">
            <button
              type="button"
              onClick={() => setRecaptchaOpen((v) => !v)}
              className="flex items-center justify-between w-full p-4 font-medium text-left text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-900 rounded-md border border-gray-200 dark:border-gray-700 focus:outline-none"
              aria-expanded={recaptchaOpen}
              aria-controls="recaptcha-body">
              <span className="text-sm font-semibold">{t('extend_session')}</span>
              <i
                className={`fa-duotone fa-chevron-down w-6 h-6 shrink-0 transform transition-transform text-gray-700 dark:text-gray-200 ${
                  recaptchaOpen ? 'rotate-180' : ''
                }`}
                aria-hidden="true"
              />
            </button>
          </h2>
          <div
            id="recaptcha-body"
            className={`${recaptchaOpen ? 'block' : 'hidden'}`}
            aria-labelledby="recaptcha-heading">
            <div className="p-4 border border-t-0 border-gray-200 dark:border-gray-700 rounded-b-md bg-white dark:bg-gray-900">
              <p className="text-sm text-gray-700 dark:text-gray-300 mb-2 text-center">
                {t('recaptcha_session_expired_note')}
              </p>
              <div className="flex justify-center">
                <RecaptchaV2 />
              </div>
            </div>
          </div>
        </div>
      </section>

      <div className="flex justify-center my-4">
        <AdSense
          client="ca-pub-2188063137129806"
          slot="6233018586"
          style={{ display: 'block' }}
          fullWidthResponsive={true}
        />
      </div>

      <div className="my-4">
        <div className="inline-flex items-center rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-1 gap-1">
          <button
            type="button"
            onClick={() => setListTab('working-json')}
            className={`px-3 py-2 rounded-md text-sm font-medium transition-colors ${
              listTab === 'working-json'
                ? 'bg-emerald-700 text-white'
                : 'text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800'
            }`}>
            <i className="fa-duotone fa-file-code mr-2" aria-hidden="true" />
            Working JSON
          </button>

          <button
            type="button"
            onClick={() => setListTab('server')}
            className={`px-3 py-2 rounded-md text-sm font-medium transition-colors ${
              listTab === 'server'
                ? 'bg-indigo-600 text-white'
                : 'text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800'
            }`}>
            <i className="fa-duotone fa-table-list mr-2" aria-hidden="true" />
            Standard list
          </button>

          <button
            type="button"
            onClick={() => setListTab('unique-ip')}
            className={`px-3 py-2 rounded-md text-sm font-medium transition-colors ${
              listTab === 'unique-ip'
                ? 'bg-cyan-700 text-white'
                : 'text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800'
            }`}>
            <i className="fa-duotone fa-network-wired mr-2" aria-hidden="true" />
            Unique IP list
          </button>
        </div>
      </div>

      {listTabContent}

      {/* Proxy counters summary */}
      <div className="my-4">
        <Summary />
      </div>

      <div className="my-4">
        <div className="border-b border-gray-200 dark:border-gray-700">
          <ul className="grid grid-cols-2 w-full divide-x divide-gray-200 dark:divide-gray-700" role="tablist">
            <li>
              <NavLink
                to="/proxy-tools/submit"
                className={({ isActive }) =>
                  `w-full inline-flex items-center justify-center p-4 text-sm font-medium ${
                    isActive
                      ? 'text-indigo-600 border-b-2 border-indigo-600 dark:text-indigo-400 dark:border-indigo-500'
                      : 'text-gray-700 dark:text-gray-200 hover:text-indigo-600 dark:hover:text-indigo-400'
                  }`
                }>
                <i
                  className="fa-duotone fa-magnifying-glass mr-2 text-gray-500 dark:text-gray-300"
                  aria-hidden="true"
                />
                Proxy checker
              </NavLink>
            </li>

            <li>
              <NavLink
                to="/proxy-tools/extract"
                className={({ isActive }) =>
                  `w-full inline-flex items-center justify-center p-4 text-sm font-medium ${
                    isActive
                      ? 'text-indigo-600 border-b-2 border-indigo-600 dark:text-indigo-400 dark:border-indigo-500'
                      : 'text-gray-700 dark:text-gray-200 hover:text-indigo-600 dark:hover:text-indigo-400'
                  }`
                }>
                <i className="fa-duotone fa-solid fa-filter mr-2 text-gray-500 dark:text-gray-300" aria-hidden="true" />
                Proxy extractor
              </NavLink>
            </li>
          </ul>
        </div>
      </div>

      <ApiUsage />
    </div>
  );
}
