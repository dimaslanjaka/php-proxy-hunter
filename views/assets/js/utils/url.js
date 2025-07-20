export function getUrlParameter(name) {
  const params = new URLSearchParams(window.location.search);
  const value = params.get(name);
  return value && value.trim() !== '' ? value : null;
}
