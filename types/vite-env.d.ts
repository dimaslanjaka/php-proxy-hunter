/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly VITE_HOSTNAME?: string;
  readonly VITE_PORT?: string | number;
  readonly VITE_BACKEND_HOSTNAME?: string;
}

interface ImportMeta {
  readonly env: ImportMetaEnv;
}
