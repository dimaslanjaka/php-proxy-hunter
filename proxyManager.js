/**
 * set event recursive
 * @param {HTMLElement} element
 * @param {string} eventName
 * @param {(...args: any[])=>any} eventFunc
 */
function setEventRecursive(element, eventName, eventFunc) {
  element.addEventListener(eventName, eventFunc);
  element.querySelectorAll('*').forEach((el) => el.addEventListener(eventName, eventFunc));
}

// function getCurrentUrlWithoutQueryAndHash() {
//   var url = window.location.href;
//   var index = url.indexOf('?'); // Find the index of the query parameter
//   if (index !== -1) {
//     url = url.substring(0, index); // Remove the query parameter
//   }
//   index = url.indexOf('#'); // Find the index of the hash
//   if (index !== -1) {
//     url = url.substring(0, index); // Remove the hash
//   }
//   return url;
// }

const iframes = Array.from(document.querySelectorAll('div.iframe[src]'));
let dragging = [];
for (let i = 0; i < iframes.length; i++) {
  const iframe = iframes[i];
  // only apply result when user not dragging texts
  iframe.onmouseup = () => {
    const selectedText = String(document.all ? document.selection.createRange().text : document.getSelection());

    if (selectedText.length > 0) {
      // console.log('User is currently dragging text in the child element.');
      dragging[iframe.getAttribute('src')] = true;
    } else {
      // console.log('User is not dragging text in the child element.');
      dragging[iframe.getAttribute('src')] = false;
    }
  };
}

async function refreshResults() {
  for (let i = 0; i < iframes.length; i++) {
    const iframe = iframes[i];
    const srcs = iframe.getAttribute('src').split('|');
    let responses = '';
    for (let ii = 0; ii < srcs.length; ii++) {
      const src = srcs[ii].trim() + '?v=' + new Date();
      const a = document.createElement('a');
      a.href = src;
      responses +=
        '\n' +
        (await fetch(a.href, { signal: AbortSignal.timeout(5000) })
          .then((res) => res.text())
          .then((text) => {
            // Split the text into lines
            const lines = text.split(/\r?\n/);

            // Get the first 500 lines
            const filterLines = lines.slice(0, 500).join('\n');

            return filterLines + (lines.length > 500 ? '\nLIMIT 500 LINES' : '');
          })
          .catch(() => {
            return `failed obtain ${src}\n`;
          }));
    }
    // only apply result when user not dragging texts
    if (!dragging[iframe.getAttribute('src')]) {
      const pre = document.createElement('pre');
      const code = document.createElement('code');
      code.innerHTML = responses.trim();
      pre.appendChild(code);
      if (iframe.children.length > 0) {
        iframe.replaceChild(pre, iframe.firstChild);
      } else {
        iframe.appendChild(pre);
      }
    }
  }
}

function showSnackbar(message, duration = 3000) {
  var snackbar = document.getElementById('snackbar');
  snackbar.textContent = message;
  snackbar.classList.add('show');
  setTimeout(function () {
    snackbar.classList.remove('show');
  }, duration);
}

/**
 * @param {string} text
 */
function parseProxies(text) {
  const ipPortRegex = /\b(?:\d{1,3}\.){3}\d{1,3}:\d+\b/g;
  const ipPortArray = text.match(ipPortRegex);
  return ipPortArray;
}

(function () {
  refreshResults();

  const refreshBtn = document.getElementById('refresh');
  if (refreshBtn) {
    const rfcb = () => {
      // remove dragging indicators
      dragging = [];
      // refresh the frames
      refreshResults();
      // show toast
      showSnackbar('data refreshed');
    };
    setEventRecursive(refreshBtn, 'click', rfcb);
  }

  const addProxyBtn = document.getElementById('addProxy');
  if (addProxyBtn) {
    const addProxyFun = () => {
      const proxies = document.getElementById('proxiesData');
      const ipPortArray = parseProxies(proxies.value);
      const dataToSend = ipPortArray.join('\n');
      proxies.value = dataToSend;
      const url = './proxyAdd.php';

      fetch(url, {
        signal: AbortSignal.timeout(5000),
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded' // Sending form-urlencoded data
        },
        body: `proxies=${encodeURIComponent(dataToSend)}` // Encode the string for safe transmission
      })
        .then((response) => {
          if (!response.ok) {
            throw new Error('Network response was not ok');
          }
          return response.text(); // assuming you want to read response as text
        })
        .then((data) => {
          showSnackbar(data);
        })
        .catch((error) => {
          showSnackbar('There was a problem with your fetch operation: ' + error.message);
        });
      refreshResults();
    };
    setEventRecursive(addProxyBtn, 'click', addProxyFun);
  }

  const cekBtn = document.getElementById('checkProxy');
  if (cekBtn) {
    setEventRecursive(cekBtn, 'click', () => {
      const userId = document.getElementById('uid').textContent.trim();
      fetch('proxyCheckerBackground.php?uid=' + userId, { signal: AbortSignal.timeout(5000) })
        .catch(() => {
          //
        })
        .finally(() => {
          setTimeout(() => {
            refreshResults();
          }, 3000);
        });
    });
  }

  let intervalFrame;

  const checkRuns = () =>
    fetch('proxyChecker.lock', { signal: AbortSignal.timeout(5000) })
      .then((res) => {
        if (res.ok) {
          if (!intervalFrame) {
            console.log('start refreshing');
            intervalFrame = setInterval(() => {
              refreshResults();
            }, 2000);
            cekBtn.setAttribute('disabled', 'true');
          }
        } else if (intervalFrame) {
          console.log('stop refreshing');
          clearInterval(intervalFrame);
          intervalFrame = null;
          if (cekBtn.hasAttribute('disabled')) {
            cekBtn.removeAttribute('disabled');
          }
        }
      })
      .catch(() => {
        //
      });

  setInterval(checkRuns, 10000);

  document.getElementById('saveConfig').addEventListener('click', () => {
    let type = document.getElementById('typeHttp').checked ? 'http' : '';
    type += document.getElementById('typeSocks5').checked ? '|' + 'socks5' : '';
    type += document.getElementById('typeSocks4').checked ? '|' + 'socks4' : '';
    fetch(location.href, {
      signal: AbortSignal.timeout(5000),
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        config: {
          headers: document.getElementById('headers').value.trim().split(/\r?\n/),
          endpoint: document.getElementById('endpoint').value.trim(),
          type: type.trim()
        }
      })
    }).catch(() => {
      //
    });
  });
})();

fetch('./info.php?v=' + new Date(), { signal: AbortSignal.timeout(5000) });
