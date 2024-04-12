/**
 * set event recursive
 * @param {HTMLElement} element
 * @param {string} eventName
 * @param {(...args: any[])=>any} eventFunc
 */
function setEventRecursive(element, eventName, eventFunc) {
	element.addEventListener(eventName, eventFunc);
	element
		.querySelectorAll("*")
		.forEach((el) => el.addEventListener(eventName, eventFunc));
}

// Get all iframes on the page
const iframes = document.getElementsByTagName("iframe");

function getCurrentUrlWithoutQueryAndHash() {
	var url = window.location.href;
	var index = url.indexOf("?"); // Find the index of the query parameter
	if (index !== -1) {
		url = url.substring(0, index); // Remove the query parameter
	}
	index = url.indexOf("#"); // Find the index of the hash
	if (index !== -1) {
		url = url.substring(0, index); // Remove the hash
	}
	return url;
}

// Function to refresh iframes
function refreshIframes() {
	// Loop through each iframe
	for (var i = 0; i < iframes.length; i++) {
		const iframe = iframes[i];
		iframe.contentWindow.location.reload();
	}
}

function showSnackbar(message, duration = 3000) {
	var snackbar = document.getElementById("snackbar");
	snackbar.textContent = message;
	snackbar.classList.add("show");
	setTimeout(function () {
		snackbar.classList.remove("show");
	}, duration);
}

const refreshBtn = document.getElementById("refresh");
if (refreshBtn) {
	const rfcb = () => {
		refreshIframes();
		showSnackbar("data refreshed");
	};
	refreshBtn.addEventListener("click", rfcb);
	refreshBtn
		.querySelectorAll("*")
		.forEach((el) => el.addEventListener("click", rfcb));
}

/**
 * @param {string} text
 */
function parseProxies(text) {
	const ipPortRegex = /\b(?:\d{1,3}\.){3}\d{1,3}:\d+\b/g;
	const ipPortArray = text.match(ipPortRegex);
	return ipPortArray;
}

const addProxyBtn = document.getElementById("addProxy");
if (addProxyBtn) {
	const addProxyFun = () => {
		const proxies = document.getElementById("proxiesData");
		const ipPortArray = parseProxies(proxies.value);
		const dataToSend = ipPortArray.join("\n");
		proxies.value = dataToSend;
		const url = "./proxyAdd.php";

		fetch(url, {
			method: "POST",
			headers: {
				"Content-Type": "application/x-www-form-urlencoded", // Sending form-urlencoded data
			},
			body: `proxies=${encodeURIComponent(dataToSend)}`, // Encode the string for safe transmission
		})
			.then((response) => {
				if (!response.ok) {
					throw new Error("Network response was not ok");
				}
				return response.text(); // assuming you want to read response as text
			})
			.then((data) => {
				showSnackbar(data);
			})
			.catch((error) => {
				showSnackbar(
					"There was a problem with your fetch operation: " + error.message
				);
			});
		refreshIframes();
	};
	setEventRecursive(addProxyBtn, "click", addProxyFun);
}

const cekBtn = document.getElementById("checkProxy");
if (cekBtn) {
	setEventRecursive(cekBtn, "click", () => {
		const userId = document.getElementById("uid").textContent.trim();
		fetch("proxyCheckerBackground.php?uid=" + userId)
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

// setInterval(() => {
// 	fetch("proxyChecker.php?isRunning")
// 		.then((res) => res.text())
// 		.then((res) => {
// 			if (res.includes("is running: false")) {
// 				clearInterval(intervalFrame);
// 				intervalFrame = null;
// 			} else {
// 				if (!intervalFrame) {
// 					intervalFrame = setInterval(() => {
// 						refreshIframes();
// 					}, 2000);
// 				}
// 			}
// 		})
// 		.catch(() => {
// 			//
// 		});
// }, 10000);

document.getElementById("saveConfig").addEventListener("click", () => {
	fetch(location.href, {
		method: "POST",
		headers: {
			"Content-Type": "application/json",
		},
		body: JSON.stringify({
			config: {
				headers: document.getElementById("headers").value.trim().split(/\r?\n/),
				endpoint: document.getElementById("endpoint").value.trim(),
			},
		}),
	});
});
