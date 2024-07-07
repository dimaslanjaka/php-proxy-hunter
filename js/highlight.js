/* global hljs */
// init highlight.js after page loaded
document.addEventListener("DOMContentLoaded", initHljs);

// start highlight pre code
function startHighlighter(block) {
  // validate hljs
  if ("hljs" in window === false) return loadHljs();
  // fix mysql highlight
  if (block.classList.contains("language-mysql")) {
    block.classList.remove("language-mysql");
    block.classList.add("language-sql");
  }
  // start highlight pre code[data-highlight]
  if (block.hasAttribute("data-highlight")) {
    if (block.getAttribute("data-highlight") != "false") {
      // highlight on data-highlight="true"
      hljs.highlightBlock(block);
    }
  } else {
    // highlight no attribute data-highlight
    hljs.highlightBlock(block);
  }
}

function loadScript(url, callback) {
  const script = document.createElement("script");
  script.src = url;
  if (typeof callback == "function") script.onload = callback;

  const referenceNode = document.querySelectorAll("script").item(0);
  referenceNode.parentNode.insertBefore(script, referenceNode.nextSibling);
}

function loadHljs() {
  // validate hljs already imported
  if ("hljs" in window === true) return;
  // otherwise create one
  loadScript("//cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/highlight.min.js", initHljs);
  loadScript("//cdnjs.cloudflare.com/ajax/libs/highlight.js/9.13.1/languages/bash.min.js");
  loadScript("//cdnjs.cloudflare.com/ajax/libs/highlight.js/9.13.1/languages/shell.min.js");
  loadCss("//cdnjs.cloudflare.com/ajax/libs/highlight.js/11.10.0/styles/androidstudio.min.css");
}

function initHljs() {
  // highlight pre code
  document.querySelectorAll("pre code").forEach(startHighlighter);

  // highlight all pre code elements
  // when use below syntax, please remove above syntax
  /*
  if ("initHighlightingOnLoad" in hljs) {
    hljs.initHighlightingOnLoad();
  } else if ("highlightAll" in hljs) {
    hljs.highlightAll();
  }
  */
}

function loadCss(url) {
  const link = document.createElement("link");
  link.rel = "stylesheet";
  link.href = url + "?v=" + new Date().getTime();
  document.head.appendChild(link);
}
