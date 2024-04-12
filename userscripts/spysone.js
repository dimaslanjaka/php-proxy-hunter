// ==UserScript==
// @name         spys.one proxy parser
// @namespace    iquaridys:hideme-parser-proxy
// @version      0.1
// @description  parse proxy from site page
// @author       iquaridys
// @match        http://spys.one/*/
// @downloadURL https://raw.githack.com/dimaslanjaka/php-proxy-hunter/master/userscripts/spysone.js
// @updateURL   https://raw.githack.com/dimaslanjaka/php-proxy-hunter/master/userscripts/spysone.js
// ==/UserScript==

(function () {
	"use strict";
	var resultText = "";
	var a = document.getElementsByClassName("spy14");
	for (var i = 0; i < a.length; i++) {
		if (a[i].innerText.includes(":")) {
			resultText += a[i].innerText + "<br>";
		}
	}

	const btn = document.createElement("button");
	btn.setAttribute("style", "position: fixed;bottom: 2em;right: 2em;");
	btn.innerText = "PARSE PROXIES";
	btn.classList.add("btn", "button", "btn-primary");
	btn.onclick = () => {
		window.open(
			URL.createObjectURL(new Blob([resultText], { type: "text/html" })),
			"width=800,height=600"
		);
	};
	document.body.appendChild(btn);
})();
