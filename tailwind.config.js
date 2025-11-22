import flowbitePlugin from 'flowbite/plugin';

/** @type {import('tailwindcss').Config} */
export default {
  darkMode: 'class',
  content: [
    './src/**/*.{js,ts,jsx,tsx}',
    './*.html',
    './node_modules/flowbite/**/*.js',
    './node_browser/**/*.html',
    './node_browser/**/*.js',
    './node_browser/**/*.cjs'
  ],
  theme: {
    extend: {
      colors: {
        clifford: '#da373d',
        ocean: '#1ca9c9',
        forest: '#228b22',
        sunset: '#ff4500',
        sky: '#87ceeb',
        sand: '#c2b280',
        berry: '#cc66cc',
        cyan: '#00ffff',
        magenta: '#ff00ff',
        polkador: '#ff6347',
        skip: '#d3d3d3',
        silver: '#c0c0c0',
        mutedGray: '#b0b0b0',
        lightGray: '#d3d3d3',
        amber: '#f59e0b'
      }
    }
  },
  plugins: [flowbitePlugin]
};
