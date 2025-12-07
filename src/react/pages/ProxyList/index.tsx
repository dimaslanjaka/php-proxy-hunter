import React from 'react';
import ServerSide from './ServerSide';
import { useTranslation } from 'react-i18next';
import RecaptchaV2 from '../../components/RecaptchaV2';
import ProxySubmission from './ProxySubmission';
import LogViewer from './LogViewer';

export default function ProxyList() {
  const { t } = useTranslation();
  const [recaptchaOpen, setRecaptchaOpen] = React.useState(false);

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

      <ServerSide />

      <ProxySubmission />

      <LogViewer />
    </div>
  );
}
