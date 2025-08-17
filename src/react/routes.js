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

// Compose the routes array by merging metadata with components
const routes = routesMeta.map((meta) => {
  let component;
  switch (meta.path instanceof Array ? meta.path[0] : meta.path) {
    case '/':
      component = Home;
      break;
    case '/admin':
      component = Admin;
      break;
    case '/outbound':
      component = Outbound;
      break;
    case '/login':
      component = Login;
      break;
    case '/changelog':
      component = Changelog;
      break;
    case '/settings':
      component = Settings;
      break;
    case '/dashboard':
      component = Dashboard;
      break;
    case '/about':
      component = About;
      break;
    case '/contact':
      component = Contact;
      break;
    case '/logout':
      component = Logout;
      break;
    case '/oauth':
    case '/oauth/google':
      component = OauthHandler;
      break;
    default:
      component = undefined;
  }
  return { ...meta, component };
});

export default routes;
