import { parse } from 'jsonc-parser';

fetch('/public/php/json/dashboard-menu.jsonc')
  .then((response) => response.text())
  .then((text) => {
    let menu;
    try {
      menu = parse(text);
    } catch (e) {
      menu = null;
      console.error('JSONC parse error:', e);
    }
    const ul = document.getElementById('dashboard-menu');
    if (!ul) {
      console.error('Dashboard menu element not found');
      return;
    }
    if (Array.isArray(menu)) {
      ul.innerHTML = menu
        .map(
          (item) => `
            <li>
              <a href="${item.url}" class="flex items-center p-2 text-gray-900 rounded-lg hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700">
                <i class="${item.icon} mr-2 text-gray-500"></i>
                ${item.label}
              </a>
            </li>
          `
        )
        .join('');
    } else {
      ul.innerHTML = `<li class='text-red-500'>Menu data invalid</li>`;
      console.error('Menu data is not an array:', menu);
    }
  })
  .catch((error) => {
    const ul = document.getElementById('dashboard-menu');
    if (!ul) {
      console.error('Dashboard menu element not found');
      return;
    }
    ul.innerHTML = `<li class='text-red-500'>Menu failed to load</li>`;
    console.error('Menu load error:', error);
  });
