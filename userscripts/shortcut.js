// ==UserScript==
// @name         Jquery HVT
// @namespace    https://tranghv.blogspot.com/
// @version      0.2
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
// @downloadURL https://raw.githubusercontent.com/dimaslanjaka/php-proxy-hunter/master/userscripts/shortcut.js
// @updateURL https://raw.githubusercontent.com/dimaslanjaka/php-proxy-hunter/master/userscripts/shortcut.js
// @noframes
// @run-at document-end
// ==/UserScript==

document.addEventListener(
  'DOMContentLoaded',
  function () {
    var e = document.createElement('script');
    e.setAttribute('src', 'https://cdnjs.cloudflare.com/ajax/libs/turbolinks/5.0.3/turbolinks.js');
    document.getElementsByTagName('head').item(0).insertBefore(e, document.getElementById('hvt-script'));
  },
  !1
);

(() => {
  // Define the array of URLs
  const urls = [
    'https://www.webmanajemen.com/2024/02/setup-vmoption-intellij-idea-for-8gb-ram-processor-intel-i3.html',
    'https://www.webmanajemen.com/2024/02/how-to-use-kotlin-bom.html',
    'https://www.webmanajemen.com/2024/02/hexo-cannot-get.html',
    'https://www.webmanajemen.com/2024/02/detect-if-called-via-request-or-directly-by-command-line.html',
    'https://www.webmanajemen.com/2024/02/how-to-disable-webrtc-in-chrome-firefox-safari-opera-edge.html',
    'https://www.webmanajemen.com/2024/02/how-to-exclude-anoying-tags-from-logcat-intellij-android-studio.html',
    'https://www.webmanajemen.com/2021/04/fix-uncaught-error-call-to-.html',
    'https://www.webmanajemen.com/2021/03/nodejs-windows-visual-studio.html',
    'https://www.webmanajemen.com/2021/03/git-login-via-command-line.html',
    'https://www.webmanajemen.com/2020/12/nodejs-common-fix-command-on-linux-or.html',
    'https://www.webmanajemen.com/2020/10/php-detect-user-client-ip-xampp-or.html',
    'https://www.webmanajemen.com/2024/02/the-inferred-type-of-x-cannot-be-named-without-a-reference-to.html',
    'https://www.webmanajemen.com/2024/02/hexo-list-loaded-posts.html',
    'https://www.webmanajemen.com/GitHub/toggle-enable-disable-github-hooks-event.html',
    'https://www.webmanajemen.com/2024/02/vscode-run-task-on-save.html',
    'https://www.webmanajemen.com/2024/02/boostrap-5-color-theme-switcher.html',
    'https://www.webmanajemen.com/2024/02/spring-livereload-javascript.html',
    'https://www.webmanajemen.com/2024/02/spring-content-type-applicationx-www-form-urlencodedcharsetutf-8-is-not-supported.html',
    'https://www.webmanajemen.com/2024/02/flowbite-react-dynamic-toast.html',
    'https://www.webmanajemen.com/2024/02/typescript-error-private-name.html',
    'https://www.webmanajemen.com/2024/02/spring-boot-enable-cors-globally.html',
    'https://www.webmanajemen.com/2024/01/hexo-render-single-post.html',
    'https://www.webmanajemen.com/2024/01/eclipse-gradle-plugin-sync-build-with-spring-boot.html',
    'https://www.webmanajemen.com/2024/01/auto-configure-classpath-config-using-eclipse-gradle-plugin.html',
    'https://www.webmanajemen.com/2024/01/classpath-eclipse-within-vscode-redhat-java-for-gradle.html',
    'https://www.webmanajemen.com/2022/05/use-import-meta-cjs.html',
    'https://www.webmanajemen.com/GitHub/github-actions-overwrite-cache.html',
    'https://www.webmanajemen.com/2017/04/instal-php-cli-pada-android-instalasi.html',
    'https://www.webmanajemen.com/2024/01/spring-boot-custom-passwordEncoder.html',
    'https://www.webmanajemen.com/NodeJS/npm-yarn-package-agent-for-china.html',
    'https://www.webmanajemen.com/GitHub/git-detach-subfolder-to-their-own-repository.html',
    'https://www.webmanajemen.com/2024/04/install-markdown-on-vite-esm-typescript.html',
    'https://whatsmyreferer.com/',
    'https://proxy6.net/en/privacy',
    'https://start.adspower.net/?id=jgcs78i&host=127.0.0.1:20725',
    'https://www.browserscan.net/'
  ];

  // Get the <ul> element from the HTML
  const ul = document.createElement('ul');
  ul.setAttribute('style', 'color: white;margin:0px;padding:0px;list-style-type: none;');

  // dont write div indicator
  let stop = false;

  // Loop through the urls array and create <li> elements for each URL
  urls.forEach((url) => {
    // dont show current domain
    if (url.includes(location.host)) {
      stop = true;
      return;
    }
    const li = document.createElement('li');
    const a = document.createElement('a');
    a.href = url;
    a.textContent = url.substring(0, 50);
    a.setAttribute('style', 'color: white;margin:0px;padding:0px;');
    li.appendChild(a);
    ul.appendChild(li);
  });

  const div = document.createElement('div');
  div.id = 'floatMyWidgetTool';
  div.setAttribute(
    'style',
    'position: fixed;top: 50%;transform: translateY(-50%);z-index: 990;background: black;color: white;padding: 5px;max-height: 250px;overflow: auto;'
  );
  div.appendChild(ul);

  const closeBtn = document.createElement('button');
  closeBtn.setAttribute('style', 'color: white;background-color: black;margin:3px;padding:0px;');
  closeBtn.innerHTML = 'X';
  const remover = () => {
    if (document.getElementById('floatMyWidgetTool')) document.getElementById('floatMyWidgetTool').remove();
    div.remove();
  };
  closeBtn.addEventListener('click', remover);
  div.appendChild(closeBtn);
  if (!stop) document.body.appendChild(div);

  // Variables to store mouse position
  var mouseX, mouseY;
  var offsetX = 0,
    offsetY = 0;

  // Function to handle mouse down event
  function handleMouseDown(e) {
    e.preventDefault();
    mouseX = e.clientX;
    mouseY = e.clientY;
    offsetX = div.offsetLeft;
    offsetY = div.offsetTop;
    document.addEventListener('mousemove', handleMouseMove);
    document.addEventListener('mouseup', handleMouseUp);
  }

  // Function to handle mouse move event
  function handleMouseMove(e) {
    var dx = e.clientX - mouseX;
    var dy = e.clientY - mouseY;
    div.style.left = offsetX + dx + 'px';
    div.style.top = offsetY + dy + 'px';
  }

  // Function to handle mouse up event
  function handleMouseUp() {
    document.removeEventListener('mousemove', handleMouseMove);
    document.removeEventListener('mouseup', handleMouseUp);
  }

  // Add event listener for mouse down event
  div.addEventListener('mousedown', handleMouseDown);
})();
