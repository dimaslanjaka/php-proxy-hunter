import React from 'react';
import ServerSide from './ServerSide';
import ApiUsage from './ApiUsage';
import { useTranslation } from 'react-i18next';
import RecaptchaV2 from '../../components/RecaptchaV2';
import ProxySubmission from './ProxySubmission';
import LogViewer from './LogViewer';
import AdSense from '../../components/AdSense';
import ProxyExtractor from './ProxyExtractor';

export default function ProxyList() {
  const { t } = useTranslation();
  const [recaptchaOpen, setRecaptchaOpen] = React.useState(false);
  const [subTab, setSubTab] = React.useState('submit');

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

      <ServerSide />

      <div className="my-4">
        <div className="border-b border-gray-200 dark:border-gray-700">
          <ul
            className="grid grid-cols-2 w-full divide-x divide-gray-200 dark:divide-gray-700"
            role="tablist"
            aria-label="Proxy submission tabs">
            <li>
              <button
                type="button"
                id="tab-submit"
                aria-controls="panel-submit"
                aria-current={subTab === 'submit' ? 'page' : undefined}
                role="tab"
                onClick={() => setSubTab('submit')}
                className={`w-full inline-flex items-center justify-center p-4 text-sm font-medium ${
                  subTab === 'submit'
                    ? 'text-indigo-600 border-b-2 border-indigo-600 dark:text-indigo-400 dark:border-indigo-500'
                    : 'text-gray-700 dark:text-gray-200 hover:text-indigo-600 dark:hover:text-indigo-400'
                }`}>
                <i
                  className="fa-duotone fa-magnifying-glass mr-2 text-gray-500 dark:text-gray-300"
                  aria-hidden="true"
                />
                Proxy checker
              </button>
            </li>

            <li>
              <button
                type="button"
                id="tab-extract"
                aria-controls="panel-extract"
                aria-current={subTab === 'extract' ? 'page' : undefined}
                role="tab"
                onClick={() => setSubTab('extract')}
                className={`w-full inline-flex items-center justify-center p-4 text-sm font-medium ${
                  subTab === 'extract'
                    ? 'text-indigo-600 border-b-2 border-indigo-600 dark:text-indigo-400 dark:border-indigo-500'
                    : 'text-gray-700 dark:text-gray-200 hover:text-indigo-600 dark:hover:text-indigo-400'
                }`}>
                <i className="fa-duotone fa-solid fa-filter mr-2 text-gray-500 dark:text-gray-300" aria-hidden="true" />
                Proxy extractor
              </button>
            </li>
          </ul>
        </div>

        <div>
          <div
            id="panel-submit"
            role="tabpanel"
            aria-labelledby="tab-submit"
            className={`${subTab === 'submit' ? '' : 'hidden'} bg-white dark:bg-gray-900`}>
            <ProxySubmission />
          </div>

          <div
            id="panel-extract"
            role="tabpanel"
            aria-labelledby="tab-extract"
            className={`${subTab === 'extract' ? '' : 'hidden'} bg-white dark:bg-gray-900`}>
            <ProxyExtractor />
          </div>
        </div>
      </div>

      <LogViewer />

      <ApiUsage />
    </div>
  );
}
