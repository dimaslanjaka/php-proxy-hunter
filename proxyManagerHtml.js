main();

async function main() {
  const user_info = userInfo();
  if (!user_info) {
    console.log('user null');
    return main();
  }

  document.getElementById('recheck').addEventListener('click', () => {
    showSnackbar('proxy checking start...');
    fetch('./proxyCheckerBackground.php?uid=' + user_info.user_id, { signal: AbortSignal.timeout(5000) })
      .then(() => {
        check();
      })
      .catch(() => {
        //
      });
  });

  check();
  setInterval(() => {
    check();
  }, 3000);

  checkerInfo();
  setInterval(() => {
    checkerInfo();
  }, 1000);

  fetchWorkingProxies();
  setInterval(() => {
    fetchWorkingProxies();
  }, 5000);
}

async function checkerInfo() {
  const info = await fetch('./proxyChecker.txt?v=' + new Date(), { signal: AbortSignal.timeout(5000) }).then((res) =>
    res.text()
  );
  const filter = info.split(/\r?\n/).join('<br/>');
  document.getElementById('cpresult').innerHTML = filter;
}

fetch('./info.php?v=' + new Date(), { signal: AbortSignal.timeout(5000) });

function userInfo() {
  try {
    return JSON.parse(atob(decodeURIComponent(getCookie('user_config'))));
  } catch (_) {
    //
  }
}

function getCookie(name) {
  const cookieString = document.cookie;
  const cookies = cookieString.split('; ');

  for (let i = 0; i < cookies.length; i++) {
    const cookie = cookies[i].split('=');
    if (cookie[0] === name) {
      return cookie[1];
    }
  }

  return null;
}

async function check() {
  console.log('checking status');
  const status = document.querySelector('span#status');
  const cek = document.getElementById('recheck');
  await fetch('./status.txt?v=' + new Date(), { signal: AbortSignal.timeout(5000) })
    .then((res) => res.text())
    .then((data) => {
      if (data.trim().includes('running')) {
        if (!cek.classList.contains('disabled')) cek.classList.add('disabled');
        status.innerHTML = 'RUNNING';
        status.setAttribute(
          'class',
          'inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20'
        );
      } else {
        cek.classList.remove('disabled');
        status.setAttribute(
          'class',
          'inline-flex items-center rounded-md bg-red-50 px-2 py-1 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-600/10'
        );
        status.innerHTML = 'IDLE';
      }
    })
    .catch(() => {
      //
    });
}

async function fetchWorkingProxies() {
  const date = new Date();
  const http = await fetch('./working.txt?v=' + date, { signal: AbortSignal.timeout(5000) })
    .then((res) => res.text())
    .catch(() => '');
  const socks = await fetch('./socks-working.txt?v=' + date, { signal: AbortSignal.timeout(5000) })
    .then((res) => res.text())
    .catch(() => '');
  const proxies = (http + '\n' + socks)
    .split(/\r?\n/)
    .map((str) => str.trim())
    .filter((str) => str.length > 0);
  const tbody = document.getElementById('wproxy');
  tbody.innerHTML = '';
  proxies.forEach((str) => {
    const tr = document.createElement('tr');
    const split = str.split('|');
    if (split.length < 7) {
      const remainingLength = 7 - split.length;
      for (let i = 0; i < remainingLength; i++) {
        split.push('undefined');
      }
    }
    split.forEach((info, i) => {
      const td = document.createElement('td');
      td.setAttribute(
        'class',
        'border-b border-slate-100 dark:border-slate-700 p-4 text-slate-500 dark:text-slate-400'
      );
      td.innerText = info;
      if (i == 0) {
        td.innerHTML += `<button class="rounded-full ml-2 pcopy" data="${info}"><i class="fa-duotone fa-copy"></i></button>`;
      }
      tr.appendChild(td);
    });
    tbody.appendChild(tr);
  });
  document.querySelectorAll('.pcopy').forEach((el) => {
    if (el.hasAttribute('aria-copy')) return;
    el.addEventListener('click', () => {
      copyToClipboard(el.getAttribute('data').trim());
      showSnackbar('proxy copied');
    });
    el.setAttribute('aria-copy', el.getAttribute('data'));
  });
}

function showSnackbar(message) {
  // Get the snackbar element
  var snackbar = document.getElementById('snackbar');

  // Set the message
  snackbar.textContent = message;

  // Add the "show" class to DIV
  snackbar.className = 'show';

  // Hide the snackbar after 3 seconds
  setTimeout(function () {
    snackbar.className = snackbar.className.replace('show', '');
  }, 3000);
}

// Copies a string to the clipboard. Must be called from within an
// event handler such as click. May return false if it failed, but
// this is not always possible. Browser support for Chrome 43+,
// Firefox 42+, Safari 10+, Edge and Internet Explorer 10+.
// Internet Explorer: The clipboard feature may be disabled by
// an administrator. By default a prompt is shown the first
// time the clipboard is used (per session).
function copyToClipboard(text) {
  if (window.clipboardData && window.clipboardData.setData) {
    // Internet Explorer-specific code path to prevent textarea being shown while dialog is visible.
    return window.clipboardData.setData('Text', text);
  } else if (document.queryCommandSupported && document.queryCommandSupported('copy')) {
    var textarea = document.createElement('textarea');
    textarea.textContent = text;
    textarea.style.position = 'fixed'; // Prevent scrolling to bottom of page in Microsoft Edge.
    document.body.appendChild(textarea);
    textarea.select();
    try {
      return document.execCommand('copy'); // Security exception may be thrown by some browsers.
    } catch (ex) {
      console.warn('Copy to clipboard failed.', ex);
      return prompt('Copy to clipboard: Ctrl+C, Enter', text);
    } finally {
      document.body.removeChild(textarea);
    }
  }
}
