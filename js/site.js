/* eslint-disable no-undef */
if (location.host == "23.94.85.180") {
  location.href = "https://sh.webmanajemen.com/proxyManager.html";
}

if (location.host.includes("webmanajemen.com")) {
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
