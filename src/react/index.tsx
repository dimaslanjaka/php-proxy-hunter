import './i18n'; // Ensure i18n is initialized before rendering components

// Import necessary React and ReactDOM libraries
import React from 'react';
import ReactDOM from 'react-dom/client';
import { BrowserRouter, Route, Routes, useLocation } from 'react-router-dom';

// Import fonts
import './fonts.scss';
// Import global styles
import './components/theme.css';

// Import all components and pages
import ReactGA from 'react-ga4';
import ReCAPTCHA from 'react-google-recaptcha';
import Footer from './components/Footer';
import Navbar from './components/Navbar';
import { SnackbarProvider } from './components/Snackbar';
import { ThemeProvider } from './components/ThemeContext';
import SimpleFormSaverDemo from './pages/examples/SimpleFormSaverDemo';
import SnackBarSample from './pages/examples/SnackBarSample';
import HighlightJS from './pages/examples/HighlightJS';
import DataTablesExamples from './pages/examples/tables';
import DefaultDataTable from './pages/examples/tables/DefaultDataTable';
import SortingDataTable from './pages/examples/tables/SortingDataTable';
import SearchDataTable from './pages/examples/tables/SearchDataTable';
import AdvancedDataTable from './pages/examples/tables/AdvancedDataTable';
import NotFound from './pages/NotFound';
import routes from './routes.js';
import { checkRecaptchaSessionExpired, verifyRecaptcha } from './utils/recaptcha';
import AdSense from './components/AdSense';

const root = ReactDOM.createRoot(document.getElementById('root')!);

// Add Backspace navigation handler
window.addEventListener('keydown', function (e) {
  // Only trigger on Backspace, not in input/textarea/contenteditable
  if (
    e.key === 'Backspace' &&
    !e.repeat &&
    !(
      document.activeElement &&
      (document.activeElement.tagName === 'INPUT' ||
        document.activeElement.tagName === 'TEXTAREA' ||
        (document.activeElement as any).isContentEditable)
    )
  ) {
    // Only go back if previous page is in same domain
    if (window.history.length > 1) {
      const prevUrl = document.referrer;
      if (prevUrl && prevUrl.startsWith(window.location.origin)) {
        e.preventDefault();
        window.history.back();
      }
    }
  }
});

const MainApp = function () {
  const recaptchaRef = React.useRef<ReCAPTCHA>(null);
  const location = useLocation();

  ReactGA.initialize([
    {
      trackingId: 'G-BG75CLNJZ1',
      gaOptions: {},
      gtagOptions: {}
    }
  ]);

  React.useEffect(() => {
    // Send pageview with a custom path when route changes
    const currentMetaRoute = routes.find((data) => {
      if (Array.isArray(data.path)) return data.path.includes(location.pathname);
      return data.path === location.pathname;
    });
    const title = currentMetaRoute?.title || 'PHP Proxy Hunter';
    document.title = title;
    ReactGA.send({ hitType: 'pageview', page: location.pathname, title });

    checkRecaptchaSessionExpired().then((expired) => {
      if (expired) {
        recaptchaRef.current?.executeAsync().then(verifyRecaptcha);
      }
    });
  }, [location.pathname]);

  return (
    <>
      <Navbar />
      <Routes>
        {routes.flatMap((CustomRoute) => {
          if (!CustomRoute.Component) return null;
          if (Array.isArray(CustomRoute.path)) {
            return CustomRoute.path.map((p) =>
              CustomRoute.Component ? <Route key={p} path={p} element={<CustomRoute.Component />} /> : null
            );
          }
          return (
            <Route
              key={CustomRoute.path}
              path={CustomRoute.path}
              element={CustomRoute.Component ? <CustomRoute.Component /> : null}
            />
          );
        })}
        <Route path="/examples/snackbar" element={<SnackBarSample />} />
        <Route path="/examples/form-saver" element={<SimpleFormSaverDemo />} />
        <Route path="/examples/highlightjs" element={<HighlightJS />} />
        <Route path="/examples/tables" element={<DataTablesExamples />} />
        <Route path="/examples/tables/default" element={<DefaultDataTable />} />
        <Route path="/examples/tables/sorting" element={<SortingDataTable />} />
        <Route path="/examples/tables/search" element={<SearchDataTable />} />
        <Route path="/examples/tables/advanced" element={<AdvancedDataTable />} />
        <Route path="*" element={<NotFound />} />
      </Routes>
      <div>
        <AdSense
          client="ca-pub-2188063137129806"
          slot="5041245242"
          style={{ display: 'block' }}
          format="autorelaxed"
          fullWidthResponsive={true}
        />
      </div>
      <Footer />
      <ReCAPTCHA
        ref={recaptchaRef}
        size="invisible"
        sitekey={import.meta.env.VITE_G_RECAPTCHA_SITE_KEY || import.meta.env.VITE_G_RECAPTCHA_V3_SITE_KEY || ''}
      />
    </>
  );
};

root.render(
  <React.StrictMode>
    <ThemeProvider>
      <SnackbarProvider stackable={true}>
        <BrowserRouter basename={import.meta.env.BASE_URL}>
          <MainApp />
        </BrowserRouter>
      </SnackbarProvider>
    </ThemeProvider>
  </React.StrictMode>
);
