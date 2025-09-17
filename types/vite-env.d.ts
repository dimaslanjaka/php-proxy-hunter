/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly [key: `VITE_${string}`]: string | undefined;
  readonly VITE_HOSTNAME?: string;
  readonly VITE_PORT?: string | number;
  readonly VITE_BACKEND_HOSTNAME_DEV?: string;
  readonly VITE_BACKEND_HOSTNAME_PROD?: string;
}

interface ImportMeta {
  readonly env: ImportMetaEnv;
}
