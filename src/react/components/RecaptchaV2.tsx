import React from 'react';
import ReCAPTCHA from 'react-google-recaptcha';
import { useTranslation } from 'react-i18next';
import { verifyRecaptcha } from '../utils/recaptcha';

export default function RecaptchaV2() {
  const { t } = useTranslation();
  const recaptchaRef = React.useRef<ReCAPTCHA | null>(null);
  const [verifying, setVerifying] = React.useState(false);
  const [message, setMessage] = React.useState<string | null>(null);

  const onChange = React.useCallback(
    async (token: string | null) => {
      setMessage(null);
      if (!token) return;
      setVerifying(true);
      try {
        const ok = await verifyRecaptcha(token);
        setMessage(ok ? (t('Captcha verified') as string) : (t('Captcha verification failed') as string));
      } catch (e) {
        setMessage(String(e));
      } finally {
        setVerifying(false);
      }
    },
    [t]
  );

  return (
    <div className="p-2 text-center">
      <div className="mb-3 flex justify-center">
        {import.meta.env.VITE_G_RECAPTCHA_V2_SITE_KEY ? (
          <ReCAPTCHA
            ref={recaptchaRef}
            sitekey={import.meta.env.VITE_G_RECAPTCHA_V2_SITE_KEY || ''}
            onChange={onChange}
          />
        ) : (
          <div className="text-red-600">{t('recaptcha_v2_site_key_missing')}</div>
        )}
      </div>

      <div className="text-sm text-gray-700 dark:text-gray-300 mb-3">
        {verifying ? t('verifying') : message || t('please_complete_captcha')}
      </div>

      <div className="flex justify-center">
        <button
          className="px-3 py-1 text-sm rounded border bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 border-gray-300 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700"
          onClick={() => {
            recaptchaRef.current?.reset();
            setMessage(null);
          }}>
          {t('reset')}
        </button>
      </div>
    </div>
  );
}
