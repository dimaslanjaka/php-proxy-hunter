import Home from './pages/Home';
import Login from './pages/Login';
import Outbound from './pages/Outbound';
import OauthHandler from './pages/OauthHandler';
import Dashboard from './pages/Dashboard';
import Settings from './pages/Settings';
import About from './pages/About';
import Contact from './pages/Contact';
import Changelog from './pages/Changelog';

export default [
  {
    path: '/',
    component: Home,
    title: 'Home | PHP Proxy Hunter',
    description: 'Welcome to PHP Proxy Hunter - the ultimate tool for managing and checking proxies.',
    thumbnail:
      'https://rawcdn.githack.com/dimaslanjaka/public-source/a74b24c2a5ff43e98d8409407b147e38c1b6a5a3/assets/img/favicon.jpg',
    canonical: 'https://www.webmanajemen.com/php-proxy-hunter/'
  },
  {
    path: '/outbound',
    component: Outbound,
    title: 'Outbound | PHP Proxy Hunter',
    description: 'View and manage outbound proxy connections in PHP Proxy Hunter.',
    thumbnail:
      'https://rawcdn.githack.com/dimaslanjaka/public-source/a74b24c2a5ff43e98d8409407b147e38c1b6a5a3/assets/img/favicon.jpg',
    canonical: 'https://www.webmanajemen.com/php-proxy-hunter/outbound'
  },
  {
    path: '/login',
    component: Login,
    title: 'Login | PHP Proxy Hunter',
    description: 'Login to your PHP Proxy Hunter account to access proxy management features.',
    thumbnail:
      'https://rawcdn.githack.com/dimaslanjaka/public-source/a74b24c2a5ff43e98d8409407b147e38c1b6a5a3/assets/img/favicon.jpg',
    canonical: 'https://www.webmanajemen.com/php-proxy-hunter/login'
  },
  {
    path: '/changelog',
    component: Changelog,
    title: 'Changelog | PHP Proxy Hunter',
    description: 'See the latest updates and changes to PHP Proxy Hunter.',
    thumbnail:
      'https://rawcdn.githack.com/dimaslanjaka/public-source/a74b24c2a5ff43e98d8409407b147e38c1b6a5a3/assets/img/favicon.jpg',
    canonical: 'https://www.webmanajemen.com/php-proxy-hunter/changelog'
  },
  {
    path: '/oauth',
    component: OauthHandler,
    title: 'OAuth Handler | PHP Proxy Hunter',
    description: 'Handle OAuth authentication for PHP Proxy Hunter.',
    thumbnail:
      'https://rawcdn.githack.com/dimaslanjaka/public-source/a74b24c2a5ff43e98d8409407b147e38c1b6a5a3/assets/img/favicon.jpg',
    canonical: 'https://www.webmanajemen.com/php-proxy-hunter/oauth'
  },
  {
    path: '/settings',
    component: Settings,
    title: 'Settings | PHP Proxy Hunter',
    description: 'Configure your PHP Proxy Hunter preferences and settings.',
    thumbnail:
      'https://rawcdn.githack.com/dimaslanjaka/public-source/a74b24c2a5ff43e98d8409407b147e38c1b6a5a3/assets/img/favicon.jpg',
    canonical: 'https://www.webmanajemen.com/php-proxy-hunter/settings'
  },
  {
    path: '/dashboard',
    component: Dashboard,
    title: 'Dashboard | PHP Proxy Hunter',
    description: 'View your dashboard and proxy statistics in PHP Proxy Hunter.',
    thumbnail:
      'https://rawcdn.githack.com/dimaslanjaka/public-source/a74b24c2a5ff43e98d8409407b147e38c1b6a5a3/assets/img/favicon.jpg',
    canonical: 'https://www.webmanajemen.com/php-proxy-hunter/dashboard'
  },
  {
    path: '/about',
    component: About,
    title: 'About | PHP Proxy Hunter',
    description: 'Learn more about PHP Proxy Hunter and its features.',
    thumbnail:
      'https://rawcdn.githack.com/dimaslanjaka/public-source/a74b24c2a5ff43e98d8409407b147e38c1b6a5a3/assets/img/favicon.jpg',
    canonical: 'https://www.webmanajemen.com/php-proxy-hunter/about'
  },
  {
    path: '/contact',
    component: Contact,
    title: 'Contact | PHP Proxy Hunter',
    description: 'Contact the PHP Proxy Hunter team for support or inquiries.',
    thumbnail:
      'https://rawcdn.githack.com/dimaslanjaka/public-source/a74b24c2a5ff43e98d8409407b147e38c1b6a5a3/assets/img/favicon.jpg',
    canonical: 'https://www.webmanajemen.com/php-proxy-hunter/contact'
  }
];
