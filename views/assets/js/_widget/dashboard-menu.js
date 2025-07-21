import menu from '../../json/dashboard-menu.json' with { type: 'json' };

const ul = document.getElementById('dashboard-menu');
if (ul) {
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
}
