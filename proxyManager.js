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

function refreshIframes() {
  for (let i = 0; i < iframes.length; i++) {
    const iframe = iframes[i];
    const a = document.createElement('a');
    a.href = iframe.getAttribute('src');
    fetch(a.href)
      .then((res) => res.text())
      .then((data) => {
        // only apply result when user not dragging texts
        if (!dragging[iframe.getAttribute('src')]) {
          const pre = document.createElement('pre');
          const code = document.createElement('code');
          code.innerHTML = data;
          pre.appendChild(code);
          if (iframe.children.length > 0) {
            iframe.replaceChild(pre, iframe.firstChild);
          } else {
            iframe.appendChild(pre);
          }
        }
      });
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
  refreshIframes();

  const refreshBtn = document.getElementById('refresh');
  if (refreshBtn) {
    const rfcb = () => {
      // remove dragging indicators
      dragging = [];
      // refresh the frames
      refreshIframes();
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
      refreshIframes();
    };
    setEventRecursive(addProxyBtn, 'click', addProxyFun);
  }

  const cekBtn = document.getElementById('checkProxy');
  if (cekBtn) {
    setEventRecursive(cekBtn, 'click', () => {
      const userId = document.getElementById('uid').textContent.trim();
      fetch('proxyCheckerBackground.php?uid=' + userId)
        // fetch("proxyChecker.php")
        .catch(() => {
          //
        })
        .finally(() => {
          setTimeout(() => {
            refreshIframes();
          }, 3000);
        });
    });
  }

  let intervalFrame = setInterval(() => {
    refreshIframes();
  }, 1000);

  const checkRuns = () =>
    fetch('proxyChecker.lock').then((res) => {
      if (res.ok) {
        if (!intervalFrame) {
          console.log('start refreshing');
          intervalFrame = setInterval(() => {
            refreshIframes();
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
    });

  checkRuns().finally(() => setInterval(checkRuns, 10000));

  document.getElementById('saveConfig').addEventListener('click', () => {
    fetch(location.href, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        config: {
          headers: document.getElementById('headers').value.trim().split(/\r?\n/),
          endpoint: document.getElementById('endpoint').value.trim()
        }
      })
    });
  });
})();
