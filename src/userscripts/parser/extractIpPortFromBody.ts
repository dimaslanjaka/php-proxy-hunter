import { extractIpPortPairs } from '../utils/extractIpPortPairs';

/**
 * Extracts unique IP:PORT pairs from the body and specific elements in the DOM.
 *
 * @returns A promise that resolves with an array of unique IP:PORT objects.
 */
export const extractIpPortFromBody = function (): Promise<any[]> {
  const result: string[] = [];
  const area = document.querySelectorAll('textarea,td');

  result.push(...extractIpPortPairs(document.body.innerHTML));

  area.forEach(function (el) {
    result.push(...extractIpPortPairs((el as HTMLTextAreaElement).value || ''));
  });

  const divList = document.querySelectorAll('div.list');
  divList.forEach(function (el) {
    result.push(...extractIpPortPairs(el.innerHTML));
  });

  const unique = result.filter(function (str, index, self) {
    return (
      index ===
      self.findIndex(function (t) {
        return t === str;
      })
    );
  });

  const map = unique.map(function (str) {
    return { raw: str };
  });

  return Promise.resolve(map);
};
