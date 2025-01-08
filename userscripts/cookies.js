// ==UserScript==
// @name     Powerful Cookies Manager
// @version  1
// @grant    none
// @match    *://*/*
// ==/UserScript==

(function () {
  // const thirdPartyCookies = getAllCookies();
  // console.log('cookies', thirdPartyCookies);
  console.log(chrome);
})();

function getAllCookies() {
  var cookies = document.cookie.split(';'); // Split cookies into an array

  var cookiesArray = [];

  // Loop through each cookie
  cookies.forEach(function (cookie) {
    var cookieParts = cookie.split('='); // Split cookie into name and value

    var cookieName = cookieParts[0].trim(); // Get cookie name
    var cookieValue = cookieParts.slice(1).join('=').trim(); // Get cookie value

    // Get additional cookie attributes if available
    var cookieAttributes = {};
    var cookieAttributesString = cookie.substring(cookie.indexOf('=') + 1);
    cookieAttributesString.split(';').forEach(function (attr) {
      var attrParts = attr.trim().split('=');
      var attrName = attrParts[0].trim();
      var attrValue = attrParts[1] ? attrParts[1].trim() : true; // Set value to true if no value provided

      cookieAttributes[attrName] = attrValue;
    });

    // Create cookie object
    var cookieObj = {
      name: cookieName,
      value: cookieValue,
      domain: cookieAttributes.domain || document.location.hostname,
      path: cookieAttributes.path || '/',
      expires: cookieAttributes.expires ? new Date(cookieAttributes.expires * 1000) : undefined,
      httpOnly: cookieAttributes.httpOnly || false,
      secure: cookieAttributes.secure || false // Note: JavaScript does not allow direct access to the "secure" attribute
    };

    cookiesArray.push(cookieObj);
  });

  return cookiesArray;
}
