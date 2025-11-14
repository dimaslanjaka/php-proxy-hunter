/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly [key: `VITE_${string}`]: string | undefined;
  readonly VITE_HOSTNAME?: string;
  readonly VITE_PORT?: string | number;
  readonly VITE_BACKEND_HOSTNAME_DEV?: string;
  readonly VITE_BACKEND_HOSTNAME_PROD?: string;
  readonly VITE_G_RECAPTCHA_SITE_KEY?: string;
  readonly VITE_G_RECAPTCHA_V2_SITE_KEY?: string;
  readonly DEV?: boolean;
  readonly BASE_URL?: string;
}

interface ImportMeta {
  readonly env: ImportMetaEnv;
}
