import Home from './pages/Home.tsx';
import Login from './pages/Login.tsx';
import Outbound from './pages/Outbound.tsx';
import OauthHandler from './pages/OauthHandler.tsx';
import Dashboard from './pages/Dashboard.tsx';
import Settings from './pages/Settings.tsx';
import About from './pages/About.tsx';
import Contact from './pages/Contact.tsx';
import Changelog from './pages/Changelog.tsx';
import Admin from './pages/Admin.tsx';
import Logout from './pages/Logout.tsx';
import routesMeta from './routes.json' assert { type: 'json' };
import ProxyList from './pages/ProxyList/index.tsx';

// Compose the routes array by merging metadata with components
const routes = routesMeta.map((meta) => {
  let Component;
  switch (meta.path instanceof Array ? meta.path[0] : meta.path) {
    case '/':
      Component = Home;
      break;
    case '/admin':
      Component = Admin;
      break;
    case '/outbound':
      Component = Outbound;
      break;
    case '/login':
      Component = Login;
      break;
    case '/changelog':
      Component = Changelog;
      break;
    case '/settings':
      Component = Settings;
      break;
    case '/dashboard':
      Component = Dashboard;
      break;
    case '/about':
      Component = About;
      break;
    case '/contact':
      Component = Contact;
      break;
    case '/logout':
      Component = Logout;
      break;
    case '/oauth':
    case '/oauth/google':
      Component = OauthHandler;
      break;
    case '/proxy-list':
    case '/proxyManager.html':
      Component = ProxyList; // Deprecated route, no component assigned
      break;
    default:
      Component = undefined;
  }
  return { ...meta, Component };
});

export default routes;
