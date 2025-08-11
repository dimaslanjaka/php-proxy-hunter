import flowbitePlugin from 'flowbite/plugin';

/** @type {import('tailwindcss').Config} */
export default {
  content: ['./src/**/*.tsx', './src/**/*.jsx', './*.html', './node_modules/flowbite/**/*.js'],
  theme: {
    extend: {}
  },
  darkMode: 'class',
  plugins: [flowbitePlugin]
};
