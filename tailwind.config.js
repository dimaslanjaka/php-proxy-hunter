import flowbitePlugin from 'flowbite/plugin';

/** @type {import('tailwindcss').Config} */
export default {
  content: ['./src/**/*.{js,ts,jsx,tsx}', './*.html', './node_modules/flowbite/**/*.js'],
  theme: {
    extend: {}
  },
  darkMode: 'class',
  plugins: [flowbitePlugin]
};
