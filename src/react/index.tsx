import './i18n'; // Ensure i18n is initialized before rendering components

// Import necessary React and ReactDOM libraries
import React from 'react';
import ReactDOM from 'react-dom/client';
import { BrowserRouter, Route, Routes } from 'react-router-dom';

// Import fonts
import './fonts.scss';
// Import global styles
import './components/theme.css';

// Import all components and pages
import Footer from './components/Footer';
import Navbar from './components/Navbar';
import { ThemeProvider } from './components/ThemeContext';
import NotFound from './pages/NotFound';
import routes from './routes.js';
import { SnackbarProvider } from './components/Snackbar';
import SnackBarSample from './pages/samples/SnackBarSample';

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

root.render(
  <React.StrictMode>
    <ThemeProvider>
      <SnackbarProvider stackable={true}>
        <BrowserRouter basename={import.meta.env.BASE_URL}>
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
            <Route path="*" element={<NotFound />} />
          </Routes>
          <Footer />
        </BrowserRouter>
      </SnackbarProvider>
    </ThemeProvider>
  </React.StrictMode>
);
