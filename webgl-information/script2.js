function getWebGLInfo() {
  const canvas = document.createElement('canvas');
  const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
  if (!gl) {
    return 'WebGL not supported';
  }

  const parameters = [
    'VENDOR',
    'RENDERER',
    'SHADING_LANGUAGE_VERSION',
    'VERSION',
    'MAX_TEXTURE_SIZE',
    'MAX_RENDERBUFFER_SIZE',
    'ALPHA_BITS',
    'BLUE_BITS',
    'GREEN_BITS',
    'RED_BITS',
    'STENCIL_BITS',
    'MAX_VIEWPORT_DIMS',
    'ALIASED_LINE_WIDTH_RANGE',
    'ALIASED_POINT_SIZE_RANGE',
    'MAX_TEXTURE_IMAGE_UNITS',
    'MAX_CUBE_MAP_TEXTURE_SIZE',
    'MAX_FRAGMENT_UNIFORM_VECTORS',
    'MAX_VERTEX_ATTRIBS',
    'MAX_VERTEX_UNIFORM_VECTORS',
    'MAX_VERTEX_TEXTURE_IMAGE_UNITS',
    'MAX_VARYING_VECTORS'
  ];

  const webGLInfo = {};
  parameters.forEach((param) => {
    const paramName = gl[param] || param;
    webGLInfo[param] = gl.getParameter(gl[param]);
  });

  return webGLInfo;
}

// Get WebGL info and display it
document.getElementById('webgl-info').textContent = JSON.stringify(getWebGLInfo(), null, 2);

// Bot Sanitysoft

// User-Agent Test
const userAgentElement = document.getElementById('user-agent-result');
userAgentElement.innerHTML = navigator.userAgent;
if (/HeadlessChrome/.test(navigator.userAgent)) {
  userAgentElement.classList.add('failed');
  userAgentElement.classList.remove('passed');
} else {
  userAgentElement.classList.add('passed');
  userAgentElement.classList.remove('failed');
}

// Webdriver Test
const webdriverElement = document.getElementById('webdriver-result');
if (navigator.webdriver || _.has(navigator, 'webdriver')) {
  webdriverElement.classList.add('failed');
  webdriverElement.classList.remove('passed');
  webdriverElement.innerHTML = 'present (failed)';
} else {
  webdriverElement.classList.add('passed');
  webdriverElement.classList.remove('failed');
  webdriverElement.innerHTML = 'missing (passed)';
}

// Chrome Test
const chromeElement = document.getElementById('chrome-result');
if (!window.chrome) {
  chromeElement.classList.add('failed');
  chromeElement.classList.remove('passed');
  chromeElement.innerHTML = 'missing (failed)';
} else {
  chromeElement.classList.add('passed');
  chromeElement.classList.remove('failed');
  chromeElement.innerHTML = 'present (passed)';
}

// Permissions Test
const permissionsElement = document.getElementById('permissions-result');
(async () => {
  const permissionStatus = await navigator.permissions.query({
    name: 'notifications'
  });
  permissionsElement.innerHTML = permissionStatus.state;
  if (Notification.permission === 'denied' && permissionStatus.state === 'prompt') {
    permissionsElement.classList.add('failed');
    permissionsElement.classList.remove('passed');
  } else {
    permissionsElement.classList.add('passed');
    permissionsElement.classList.remove('failed');
  }
})();

// Plugins Length Test
const pluginsLengthElement = document.getElementById('plugins-length-result');
pluginsLengthElement.innerHTML = navigator.plugins.length;
if (navigator.plugins.length === 0) {
  pluginsLengthElement.classList.add('failed');
  pluginsLengthElement.classList.remove('passed');
} else {
  pluginsLengthElement.classList.add('passed');
  pluginsLengthElement.classList.remove('failed');
}

// Plugins type Test
const pluginsTypeElement = document.getElementById('plugins-type-result');
if (
  !(navigator.plugins instanceof PluginArray) ||
  navigator.plugins.length === 0 ||
  window.navigator.plugins[0].toString() !== '[object Plugin]'
) {
  pluginsTypeElement.classList.add('failed');
  pluginsTypeElement.classList.remove('passed');
  pluginsTypeElement.innerText = 'failed';
} else {
  pluginsTypeElement.classList.add('passed');
  pluginsTypeElement.classList.remove('failed');
  pluginsTypeElement.innerText = 'passed';
}

// Languages Test
const languagesElement = document.getElementById('languages-result');
languagesElement.innerHTML = navigator.languages;
if (!navigator.languages || navigator.languages.length === 0) {
  languagesElement.classList.add('failed');
  languagesElement.classList.remove('passed');
} else {
  languagesElement.classList.add('passed');
  languagesElement.classList.remove('failed');
}

// WebGL Tests
const webGLVendorElement = document.getElementById('webgl-vendor');
const webGLRendererElement = document.getElementById('webgl-renderer');

const canvas = document.createElement('canvas');
const gl = canvas.getContext('webgl') || canvas.getContext('webgl-experimental');
if (gl) {
  const debugInfo = gl.getExtension('WEBGL_debug_renderer_info');

  try {
    // WebGL Vendor Test
    const vendor = gl.getParameter(debugInfo.UNMASKED_VENDOR_WEBGL);
    webGLVendorElement.innerHTML = vendor;
    if (vendor === 'Brian Paul' || vendor === 'Google Inc.') {
      webGLVendorElement.classList.add('failed');
    } else {
      webGLVendorElement.classList.add('passed');
    }
  } catch (e) {
    webGLVendorElement.innerHTML = 'Error: ' + e;
  }

  try {
    // WebGL Renderer Test
    const renderer = gl.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL);
    webGLRendererElement.innerHTML = renderer;
    if (renderer === 'Mesa OffScreen' || renderer.indexOf('Swift') !== -1) {
      webGLRendererElement.classList.add('failed');
    } else webGLRendererElement.classList.add('passed');
  } catch (e) {
    webGLRendererElement.innerHTML = 'Error: ' + e;
  }
} else {
  webGLVendorElement.innerHTML = 'Canvas has no webgl context';
  webGLRendererElement.innerHTML = 'Canvas has no webgl context';
  webGLVendorElement.classList.add('failed');
  webGLRendererElement.classList.add('failed');
}

// Broken Image Dimensions Test
const brokenImageDimensionsElement = document.getElementById('broken-image-dimensions');
const body = document.body;
const image = document.createElement('img');
image.onerror = function () {
  brokenImageDimensionsElement.innerHTML = `${image.width}x${image.height}`;
  if (image.width == 0 && image.height == 0) {
    brokenImageDimensionsElement.classList.add('failed');
  } else {
    brokenImageDimensionsElement.classList.add('passed');
  }
};
body.appendChild(image);
image.src = 'https://intoli.com/nonexistent-image.png';

let drawCanvas2 = function (num, useIframe = false) {
  var canvas2d;

  /** @type {boolean} */
  var isOkCanvas = true;

  /** @type {string} */
  var canvasText = 'Bot test <canvas> 1.1';

  let canvasContainer = document.getElementById('canvas' + num);
  let iframe = document.getElementById('canvas' + num + '-iframe');
  //canvasContainer.appendChild(iframe);

  var canvasElement = useIframe ? iframe.contentDocument.createElement('canvas') : document.createElement('canvas');

  if (canvasElement.getContext) {
    canvas2d = canvasElement.getContext('2d');

    try {
      canvasElement.setAttribute('width', 220);
      canvasElement.setAttribute('height', 30);

      canvas2d.textBaseline = 'top';
      canvas2d.font = "14px 'Arial'";
      canvas2d.textBaseline = 'alphabetic';
      canvas2d.fillStyle = '#f60';
      canvas2d.fillRect(53, 1, 62, 20);
      canvas2d.fillStyle = '#069';
      canvas2d.fillText(canvasText, 2, 15);
      canvas2d.fillStyle = 'rgba(102, 204, 0, 0.7)';
      canvas2d.fillText(canvasText, 4, 17);
    } catch (b) {
      /** @type {!Element} */
      canvasElement = document.createElement('canvas');
      canvas2d = canvasElement.getContext('2d');
      if (void 0 === canvas2d || 'function' != typeof canvasElement.getContext('2d').fillText) {
        isOkCanvas = false;
      } else {
        canvasElement.setAttribute('width', 220);
        canvasElement.setAttribute('height', 30);
        /** @type {string} */
        canvas2d.textBaseline = 'top';
        /** @type {string} */
        canvas2d.font = "14px 'Arial'";
        /** @type {string} */
        canvas2d.textBaseline = 'alphabetic';
        /** @type {string} */
        canvas2d.fillStyle = '#f60';
        canvas2d.fillRect(125, 1, 62, 20);
        /** @type {string} */
        canvas2d.fillStyle = '#069';
        canvas2d.fillText(canvasText, 2, 15);
        /** @type {string} */
        canvas2d.fillStyle = 'rgba(102, 204, 0, 0.7)';
        canvas2d.fillText(canvasText, 4, 17);
      }
    }

    if (isOkCanvas && 'function' == typeof canvasElement.toDataURL) {
      var datUrl = canvasElement.toDataURL('image/png');
      try {
        if ('boolean' == typeof datUrl || void 0 === datUrl) {
          throw e;
        }
      } catch (a) {
        /** @type {string} */
        datUrl = '';
      }
      if (0 === datUrl.indexOf('data:image/png')) {
      } else {
        /** @type {boolean} */
        isOkCanvas = false;
      }
    } else {
      /** @type {boolean} */
      isOkCanvas = false;
    }
  } else {
    /** @type {boolean} */
    isOkCanvas = false;
  }

  if (isOkCanvas) {
    let newDiv = document.createElement('div');
    if (typeof datUrl != 'undefined') {
      if (typeof datUrl.hashCode == 'function') {
        newDiv.innerHTML = 'Hash: ' + datUrl.hashCode();
      } else {
        newDiv.innerHTML = 'Hash: datUrl.hashCode() is not function';
      }
    }
    canvasContainer.appendChild(canvasElement);
    canvasContainer.appendChild(newDiv);
  } else {
    let newDiv = document.createElement('div');
    newDiv.innerHTML = 'Canvas failed';
    canvasContainer.appendChild(newDiv);
  }
};

window.canvasCount = 0;

drawCanvas2('1');
drawCanvas2('2');

drawCanvas2('3', true);
drawCanvas2('4', true);
drawCanvas2('5', true);
