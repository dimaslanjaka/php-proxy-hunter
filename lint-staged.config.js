const ignoreUserscripts = (files) => files.filter((file) => !file.startsWith('userscripts/'));
/** @type {import('lint-staged').Configuration} */
export default {
  '*.{js,cjs,mjs,ts,jsx,tsx}': (files) => {
    const filtered = ignoreUserscripts(files);
    return filtered.length ? [`eslint --fix ${filtered.join(' ')}`] : [];
  },

  '*.{json,css,scss,less,yml,yaml,sql}': (files) => {
    const filtered = ignoreUserscripts(files);
    return filtered.length ? [`prettier --list-different --write ${filtered.join(' ')}`] : [];
  }
};
