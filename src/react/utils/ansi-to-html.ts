import Convert from 'ansi-to-html';
import DOMPurify from 'dompurify';

// Reuse a single converter instance for performance
const ansiConverter = new Convert({ newline: true });

export function convertAnsiToHtml(ansiText: string): string {
  if (!ansiText) return '';
  try {
    let html = ansiConverter.toHtml(ansiText);
    // Normalize dark-blue variants to a brighter blue for dark backgrounds
    html = html.replace(/style="color:\s*#00A\b/gi, 'style="color:#0099ff');
    html = html.replace(/style="color:\s*#0000AA\b/gi, 'style="color:#0099ff');
    return DOMPurify.sanitize(html);
  } catch (_e) {
    return ansiText;
  }
}
