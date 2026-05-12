import { addProxyFun } from './parser/addProxyFun';
import { parse_all } from './parser/parse_all';
import createButton from './utils/createButton';

(function () {
  'use strict';

  /**
   * Sanitizes HTML by removing specified tags and the style attribute.
   * @param html - The HTML content to sanitize.
   * @returns The sanitized HTML content.
   */
  const sanitizeHtml = function (html: string): string {
    const doc = new DOMParser().parseFromString(html, 'text/html');

    // Tags to remove
    const tagsToRemove = ['img', 'script', 'iframe', 'link', 'ins'];

    tagsToRemove.forEach(function (tagName) {
      const tags = doc.getElementsByTagName(tagName);
      for (let i = tags.length - 1; i >= 0; i--) {
        tags[i].parentNode?.removeChild(tags[i]);
      }
    });

    // Remove 'style' attribute from all tags
    const allTags = doc.getElementsByTagName('*');
    for (let i = 0; i < allTags.length; i++) {
      allTags[i].removeAttribute('style');
    }

    const filteredHtml = doc.documentElement.outerHTML;

    const doc2 = new DOMParser().parseFromString(filteredHtml, 'text/html');
    const elements: string[] = [];
    doc2.querySelectorAll('textarea,table,.list').forEach(function (el) {
      elements.push(el.outerHTML);
    });
    return elements.join('\n');
  };

  /**
   * Monitors changes to the body's HTML content and performs actions when changes are detected.
   */
  const monitorBodyChanges = function () {
    let lastHtml = '';

    setInterval(function () {
      const currentHtml = document.body.innerHTML;
      const sanitizedHtml = sanitizeHtml(currentHtml);
      if (sanitizedHtml !== lastHtml) {
        lastHtml = sanitizedHtml;
        console.log('body changed');
        parse_all().then(addProxyFun);
      }
    }, 3000); // Check every 3 seconds
  };

  setTimeout(monitorBodyChanges, 3000);
  setTimeout(createButton, 5000);
})();
