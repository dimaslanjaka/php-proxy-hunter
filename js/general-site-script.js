/* eslint-disable no-undef */
if (location.host == "23.94.85.180") {
  location.href = "https://sh.webmanajemen.com/proxyManager.html";
}
const port = location.port || "";

if (location.host.includes("webmanajemen.com")) {
  if (port.length === 0) {
    fetch("info.php")
      .then((res) => res.json())
      .then((data) => {
        if (!data.admin) {
          startAdsense();
        }
      })
      .catch((_e) => {
        console.log("failed get info.php");
      });
  } else if (port.length === 4) {
    // django server
    startAdsense();
  }
}

function startAdsense() {
  // Create script element
  const script = document.createElement("script");
  script.async = true;
  script.src = "//pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-2188063137129806";
  script.setAttribute("crossorigin", "anonymous");
  script.onerror = function () {
    // This function is called if the script fails to load
    console.log("Adblocker detected!");
    // Alert the user
    alert("Adblocker detected!");

    // Remove the entire content of the body
    document.body.innerHTML = "";

    // Set timeout to reload the page after 2 seconds
    setTimeout(function () {
      location.reload();
    }, 2000);
  };
  script.onload = () => {
    // const tables = Array.from(document.querySelectorAll('table'));
    // const ads = [
    //   {
    //     class: 'adsbygoogle',
    //     style: 'display:block',
    //     'data-ad-client': 'ca-pub-2188063137129806',
    //     'data-ad-slot': '5369043101',
    //     'data-ad-format': 'auto',
    //     'data-full-width-responsive': true
    //   },
    //   {
    //     class: 'adsbygoogle',
    //     style: 'display:block',
    //     'data-ad-client': 'ca-pub-2188063137129806',
    //     'data-ad-slot': '6873696468',
    //     'data-ad-format': 'auto',
    //     'data-full-width-responsive': true
    //   }
    // ];
    // for (let i = 0; i < tables.length; i++) {
    //   const table = tables[i];
    //   // Generate a random index for the <tr> element
    //   var randomIndex = Math.floor(Math.random() * table.rows.length);

    //   // Create a new <ins> element
    //   var insElement = document.createElement('ins');

    //   // Insert the <ins> element after the randomly selected <tr> element
    //   table.rows[randomIndex].insertAdjacentElement('afterend', insElement);
    // }

    const ins = Array.from(document.querySelectorAll("ins"));
    for (let i = 0; i < ins.length; i++) {
      const el = ins[i];
      if (el.children.length == 0) (adsbygoogle = window.adsbygoogle || []).push({});
    }
  };

  // Append script element to the document body
  document.body.appendChild(script);
}

function notificationShow() {
  const notification = document.getElementById("notification");
  const closeButton = document.getElementById("close-btn");
  const clearCookieButton = document.getElementById("clear-cookie-btn");

  function _site_set_cookie(name, value, hours) {
    const expires = new Date();
    expires.setTime(expires.getTime() + hours * 60 * 60 * 1000);
    document.cookie = `${name}=${value};expires=${expires.toUTCString()};path=/`;
  }

  function _site_get_cookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(";").shift();
  }

  function _site_delete_cookie(name) {
    document.cookie = `${name}=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/`;
  }

  function hideNotification() {
    if (notification) {
      notification.remove();
    }
  }

  function refreshPage() {
    history.go(0);
  }

  if (closeButton)
    closeButton.addEventListener("click", function () {
      hideNotification();
      _site_set_cookie("notificationDismissed", "true", 4);
    });
  if (clearCookieButton)
    clearCookieButton.addEventListener("click", function () {
      _site_delete_cookie("notificationDismissed");
      hideNotification(); // Optionally hide the notification immediately
      refreshPage();
    });

  if (_site_get_cookie("notificationDismissed")) {
    hideNotification();
  }
}

document.body.innerHTML += `<!-- Notification Div -->
<div id="notification" class="fixed top-4 right-4 bg-gray-800 text-white p-4 rounded-lg shadow-lg flex items-center space-x-4">
  <i class="fas fa-exclamation-triangle text-yellow-400 text-xl"></i> <!-- Warning Icon -->
  <p class="flex-1">Server will restart every 4 hours.</p>
  <button id="close-btn" class="ml-auto text-gray-400 hover:text-white focus:outline-none">
    <i class="fas fa-times"></i>
  </button>
</div>`;
setTimeout(() => {
  notificationShow();
}, 500);
