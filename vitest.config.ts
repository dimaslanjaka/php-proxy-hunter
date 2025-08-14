import { defineConfig } from 'vitest/config';
import viteConfig from './vite.config';

export default defineConfig({
  root: viteConfig.root,
  base: viteConfig.base,
  resolve: viteConfig.resolve,
  plugins: viteConfig.plugins as any,
  test: {
    globals: true,
    environment: 'jsdom',
    include: ['tests/**/*.vitest.{cjs,ts,mjs,js}', 'tests/**/*.test.jsx', 'tests/**/*.test.tsx']
  }
});
