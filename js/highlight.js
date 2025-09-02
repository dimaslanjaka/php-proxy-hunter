/* global hljs */

/**
 * start highlight pre code
 * @param {Element|HTMLElement} block
 * @returns
 */
function startHighlighter(block) {
  // validate hljs
  if ('hljs' in window === false) {
    console.log('highlight.js not loaded');
    return loadHljs();
  }
  // fix mysql highlight
  if (block.classList.contains('language-mysql')) {
    block.classList.remove('language-mysql');
    block.classList.add('language-sql');
  }
  // start highlight pre code[data-highlight]
  if (block.hasAttribute('data-highlight')) {
    if (block.getAttribute('data-highlight') != 'false') {
      // highlight on data-highlight="true"
      // @ts-expect-error: highlightElement may not be defined in some hljs versions
      if (hljs.highlightElement) {
        // @ts-expect-error: highlightElement may not be defined in some hljs versions
        hljs.highlightElement(block);
      } else {
        hljs.highlightBlock(block);
      }
    }
  } else {
    // highlight no attribute data-highlight
    // @ts-expect-error: highlightElement may not be defined in some hljs versions
    if (hljs.highlightElement) {
      // @ts-expect-error: highlightElement may not be defined in some hljs versions
      hljs.highlightElement(block);
    } else {
      hljs.highlightBlock(block);
    }
  }
}

/**
 * Dynamically loads a JavaScript file and executes a callback when loaded.
 * @param {string} url - The URL of the script to load.
 * @param {(...args: any[]) => any} callback - The function to call once the script is loaded.
 */
function loadScript(url, callback) {
  const script = document.createElement('script');
  script.src = url;
  script.onload = callback;

  const referenceNode = document.querySelectorAll('script').item(0);
  referenceNode.parentNode.insertBefore(script, referenceNode.nextSibling);
}

/**
 * Loads multiple JavaScript files sequentially and executes a final callback when all are loaded.
 * @param {string[]} urls - Array of script URLs to load.
 * @param {Function} finalCallback - Function to call after all scripts are loaded.
 */
function loadScriptsSequentially(urls, finalCallback) {
  function loadNext(index) {
    if (index >= urls.length) {
      // All scripts loaded
      if (typeof finalCallback === 'function') {
        finalCallback();
      }
      return;
    }

    loadScript(urls[index], function () {
      // Load the next script in the array
      loadNext(index + 1);
    });
  }

  // Start loading the first script
  loadNext(0);
}

/**
 * Loads one or more CSS files and executes a callback when all are loaded.
 * @param {string|string[]} urls - URL or array of URLs of CSS files to load.
 * @param {(...args: any[]) => any} [callback] - Function to call after all CSS files are loaded.
 */
function loadStyles(urls, callback) {
  if (typeof urls === 'string') {
    urls = [urls];
  }

  /**
   * @param {number} index
   */
  function loadNext(index) {
    if (index >= urls.length) {
      // All CSS loaded
      if (typeof callback === 'function') {
        callback();
      }
      return;
    }

    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = urls[index] + '?v=' + new Date().getTime();
    link.onload = () => {
      // Load the next CSS in the array
      loadNext(index + 1);
    };

    document.head.appendChild(link);
  }

  // Start loading the first CSS
  loadNext(0);
}

/**
 * Loads highlight.js and its required language modules and styles if not already loaded.
 */
function loadHljs() {
  // validate hljs already imported
  if ('hljs' in window === true) return;
  console.log('loading module highlight.js');
  // otherwise create one
  loadStyles('//cdnjs.cloudflare.com/ajax/libs/highlight.js/11.10.0/styles/androidstudio.min.css');
  loadScriptsSequentially(['//cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/highlight.min.js'], function () {
    loadScriptsSequentially(
      [
        '//cdnjs.cloudflare.com/ajax/libs/highlight.js/9.13.1/languages/bash.min.js',
        '//cdnjs.cloudflare.com/ajax/libs/highlight.js/9.13.1/languages/shell.min.js'
      ],
      initHljs
    );
  });
}

/**
 * Initializes highlight.js on all <pre><code> blocks in the document.
 */
function initHljs() {
  // highlight pre code
  document.querySelectorAll('pre code').forEach(startHighlighter);

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

// function loadCss(url) {
//   const link = document.createElement('link');
//   link.rel = 'stylesheet';
//   link.href = url + '?v=' + new Date().getTime();
//   document.head.appendChild(link);
// }

// init highlight.js after page loaded
// document.addEventListener('DOMContentLoaded', initHljs);
initHljs();
