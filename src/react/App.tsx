import React from 'react';
import { Route, BrowserRouter as Router, Routes } from 'react-router-dom';
import { ThemeProvider } from './components/ThemeContext';
import Home from './pages/Home';
import Login from './pages/Login';
import Outbound from './pages/Outbound';
import OauthHandler from './pages/OauthHandler';
import Dashboard from './pages/Dashboard';
import Settings from './pages/Settings';
import About from './pages/About';
import Contact from './pages/Contact';
import GitHistory from './pages/GitHistory';

const App: React.FC = () => {
  return (
    <ThemeProvider>
      <Router>
        <React.Suspense fallback={<div>Loading...</div>}>
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
            <Route path="/changelog" element={<GitHistory />} />
          </Routes>
        </React.Suspense>
      </Router>
    </ThemeProvider>
  );
};

export default App;
