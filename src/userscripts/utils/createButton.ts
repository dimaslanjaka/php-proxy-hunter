import { addProxyFun } from '../parser/addProxyFun';
import { parse_all } from '../parser/parse_all';

export default function createButton() {
  const btn = document.createElement('button');
  btn.id = 'php-proxy-hunter-grab-proxy';
  btn.setAttribute(
    'style',
    'position: fixed; top: 50%; left: 0; transform: translateY(-50%); opacity: 0.6; margin-left: 1.2em; color: white; background-color: black; z-index: 9999; border: none; padding: 0.5em 1em; cursor: pointer; font-size: 14px; border-radius: 4px; transition: opacity 0.3s ease;'
  );
  btn.innerText = 'PARSE PROXIES';
  btn.classList.add('btn', 'button', 'btn-primary');
  btn.onclick = function () {
    parse_all()
      .then(function (result) {
        console.log('Parsed Proxies:', result);
        addProxyFun(result);
        const htmlContent = `
<!DOCTYPE html>
<html>
<head>
  <title>JSON Data</title>
  <style>
    body {
      background-color: #121212;
      color: #ffffff;
      font-family: Arial, sans-serif;
      padding: 20px;
    }
    pre {
      white-space: pre-wrap; /* Ensures long lines wrap */
      word-wrap: break-word; /* Prevents overflowing */
    }
  </style>
</head>
<body><pre>${result.trim()}</pre></body>
</html>`;

        window.open(URL.createObjectURL(new Blob([htmlContent], { type: 'text/html' })), 'width=800,height=600');
      })
      .catch(function (error) {
        console.error(error);
      });
  };
  document.body.appendChild(btn);
}
