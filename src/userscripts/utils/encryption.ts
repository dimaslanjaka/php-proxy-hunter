export function encryptStr(text: string): string {
  return text
    .split('')
    .map((c, i) => String.fromCharCode(c.charCodeAt(0) + i + 1))
    .join('');
}
export function decryptStr(text: string): string {
  return text
    .split('')
    .map((c, i) => String.fromCharCode(c.charCodeAt(0) - i - 1))
    .join('');
}
export function isValidEncryptStr(str: string): boolean {
  // Regular expression to check if a string is a valid MD5 hash
  const re = /^[a-f0-9]{32}$/i;
  return re.test(str);
}
