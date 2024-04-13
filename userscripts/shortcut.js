// ==UserScript==
// @name         Jquery HVT
// @namespace    https://tranghv.blogspot.com/
// @version      0.1
// @description  Add jquery turbolink
// @author       Tráng Hà Viết
// @match        http://*/*
// @match        https://*/*
// @exclude        *mail.google.com/*
// @exclude        http://localhost:*
// @exclude        http://127.0.0.1:*
// @exclude        *www.facebook.com*
// @exclude        *github.com*
// @exclude        *www.baogiaothong.vn*
// ==/UserScript==

document.addEventListener(
	"DOMContentLoaded",
	function () {
		var e = document.createElement("script");
		e.setAttribute(
			"src",
			"https://cdnjs.cloudflare.com/ajax/libs/turbolinks/5.0.3/turbolinks.js"
		);
		document
			.getElementsByTagName("head")
			.item(0)
			.insertBefore(e, document.getElementById("hvt-script"));
	},
	!1
);

(() => {
	// Define the array of URLs
	const urls = [
		"https://www.webmanajemen.com/2024/04/install-markdown-on-vite-esm-typescript.html",
		"https://whatsmyreferer.com/",
	];

	// Get the <ul> element from the HTML
	const ul = document.createElement("ul");
	ul.setAttribute("style", "color: white;margin:0px;padding:0px;");

	// Loop through the urls array and create <li> elements for each URL
	urls.forEach((url) => {
		const li = document.createElement("li");
		const a = document.createElement("a");
		a.href = url;
		a.textContent = url.substring(0, 50);
		a.setAttribute("style", "color: white;margin:0px;padding:0px;");
		li.appendChild(a);
		ul.appendChild(li);
	});

	const closeBtn = document.createElement("button");
	closeBtn.setAttribute("style", "color: black;margin:3px;padding:0px;");
	closeBtn.innerHTML = "X";
	closeBtn.onclick = () => {
		document.getElementById("floatMyWidgetTool").remove();
	};

	const div = document.createElement("div");
	div.id = "floatMyWidgetTool";
	div.setAttribute(
		"style",
		"position: fixed;top: 50%;transform: translateY(-50%);right: 0;z-index: 990;background: black;color: white;padding: 5px;"
	);
	div.appendChild(ul);
	div.appendChild(closeBtn);
	document.body.appendChild(div);
})();
