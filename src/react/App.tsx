import React from 'react';
import { Route, BrowserRouter as Router, Routes } from 'react-router-dom';
import { ThemeProvider } from './components/ThemeContext';
const Home = React.lazy(() => import('./pages/Home'));
const Login = React.lazy(() => import('./pages/Login'));
const Outbound = React.lazy(() => import('./pages/Outbound'));
const OauthHandler = React.lazy(() => import('./pages/OauthHandler'));
const Dashboard = React.lazy(() => import('./pages/Dashboard'));
const Settings = React.lazy(() => import('./pages/Settings'));
const About = React.lazy(() => import('./pages/About'));
const Contact = React.lazy(() => import('./pages/Contact'));
const Changelog = React.lazy(() => import('./pages/Changelog'));

const App: React.FC = () => {
  return (
    <ThemeProvider>
      <Router basename={import.meta.env.BASE_URL}>
        <React.Suspense
          fallback={
            <div className="flex items-center justify-center min-h-screen">
              <i className="fa-duotone fa-spinner-third animate-spin text-2xl text-blue-600 dark:text-blue-400 mr-2"></i>
              <span className="text-gray-500 dark:text-gray-300">Loading...</span>
            </div>
          }>
          <Routes>
            <Route path="/" element={<Home />} />
            <Route path="/login" element={<Login />} />
            <Route path="/outbound" element={<Outbound />} />
            <Route path="/oauth" element={<OauthHandler />} />
            <Route path="/oauth/google" element={<OauthHandler />} />
            <Route path="/dashboard" element={<Dashboard />} />
            <Route path="/settings" element={<Settings />} />
            <Route path="/about" element={<About />} />
            <Route path="/contact" element={<Contact />} />
            <Route path="/changelog" element={<Changelog />} />
          </Routes>
        </React.Suspense>
      </Router>
    </ThemeProvider>
  );
};

export default App;
