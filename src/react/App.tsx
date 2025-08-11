import React from 'react';
import { Route, BrowserRouter as Router, Routes } from 'react-router-dom';
import { ThemeProvider } from './components/ThemeContext';
import Home from './pages/Home';

const App: React.FC = () => {
  return (
    <ThemeProvider>
      <Router>
        <React.Suspense fallback={<div>Loading...</div>}>
          <Routes>
            <Route path="/" element={<Home />} />
          </Routes>
        </React.Suspense>
      </Router>
    </ThemeProvider>
  );
};

export default App;
