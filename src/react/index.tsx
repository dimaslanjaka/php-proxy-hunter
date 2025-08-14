import React from 'react';
import ReactDOM from 'react-dom/client';
import App from './App';
import './components/theme.css';

const _react = typeof React;
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

// root.render(
//   <React.StrictMode>
//     <App />
//   </React.StrictMode>
// );

root.render(<App />);
