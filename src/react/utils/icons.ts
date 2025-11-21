import { createUrl } from './url';

export const statusIcons = {
  success: '✔️',
  error: '❌',
  warning: '⚠️',
  info: 'ℹ️'
};
export const brandIcons = {
  im3: createUrl(
    '/php_backend/img.php?url=' +
      encodeURIComponent('https://im3-img.indosatooredoo.com/indosatassets/images/GeraiOnline.jpg')
  ),
  axis: createUrl(
    '/php_backend/img.php?url=' +
      encodeURIComponent(
        'https://upload.wikimedia.org/wikipedia/commons/thumb/8/83/Axis_logo_2015.svg/1200px-Axis_logo_2015.svg.png'
      )
  ),
  unknown: createUrl(
    '/php_backend/img.php?url=' + encodeURIComponent('https://www.pikpng.com/pngl/b/597-5973888_unknown-png.png')
  )
};
