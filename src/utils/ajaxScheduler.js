import axios from 'axios';

/**
 * @typedef {import('./ajaxScheduler_').AjaxScheduleEntry} AjaxScheduleEntry
 * @typedef {import('./ajaxScheduler_').AjaxScheduleOptions} AjaxScheduleOptions
 */

/**
 * list of scheduled requests to be executed. Each item can be either a string URL
 * (legacy) or an object: { url, method, data, includeCredentials }
 * @type {(string|AjaxScheduleEntry)[]}
 */
const ajax_schedule_queue = [];

/**
 * ajax schedule runner indicator
 * @type {boolean}
 */
let ajax_schedule_running = false;

/**
 * Runs the AJAX schedule.
 * @param {AjaxScheduleOptions} [options] - Optional settings for the runner.
 * @param {boolean} [options.includeCredentials=false] - When true, send cookies/credentials with requests (maps to axios `withCredentials`).
 * @param {'GET'|'POST_JSON'|'POST_FORM'} [options.method='GET'] - Request method to use for each scheduled URL.
 *   - 'GET' will perform a GET request.
 *   - 'POST_JSON' will POST JSON with Content-Type: application/json.
 *   - 'POST_FORM' will POST form-urlencoded data with Content-Type: application/x-www-form-urlencoded.
 * @param {Object|URLSearchParams|string} [options.data] - Payload to send for POST requests. If an object is provided for 'POST_FORM', it will be converted using URLSearchParams.
 */
export function run_ajax_schedule(options = {}) {
  if (!ajax_schedule_running) {
    ajax_schedule_running = true;
    const entry = ajax_schedule_queue.shift();

    // if nothing scheduled, stop running
    if (!entry) {
      ajax_schedule_running = false;
      return;
    }

    // support legacy string entries
    const item = typeof entry === 'string' ? { url: entry } : entry;

    // merge runner options with item-specific options; item takes precedence
    const requestOpts = Object.assign({}, options, item);

    const axiosConfig = {
      timeout: 5000,
      // treat any HTTP status as success to mirror fetch behavior
      validateStatus: () => true,
      // map includeCredentials to axios withCredentials
      withCredentials: Boolean(requestOpts.includeCredentials)
    };

    // choose request based on method
    const method = (requestOpts.method || 'GET').toString().toUpperCase();
    let requestPromise;

    if (method === 'POST_JSON') {
      axiosConfig.headers = Object.assign({}, axiosConfig.headers, {
        'Content-Type': 'application/json'
      });
      requestPromise = axios.post(item.url, requestOpts.data || {}, axiosConfig);
    } else if (method === 'POST_FORM') {
      axiosConfig.headers = Object.assign({}, axiosConfig.headers, {
        'Content-Type': 'application/x-www-form-urlencoded'
      });
      let body = requestOpts.data || '';
      if (body && typeof body === 'object' && !(body instanceof URLSearchParams)) {
        body = new URLSearchParams(body).toString();
      }
      requestPromise = axios.post(item.url, body, axiosConfig);
    } else {
      requestPromise = axios.get(item.url, axiosConfig);
    }

    requestPromise
      .catch(() => {
        // re-push the same entry when error (network/timeout/etc)
        add_ajax_schedule(item);
      })
      .finally(() => {
        ajax_schedule_running = false;
        // repeat, preserving runner-level options
        if (ajax_schedule_queue.length > 0) run_ajax_schedule(options);
      });
  }
}

/**
 * Adds a request entry to the AJAX schedule if it's not already present.
 *
 * Supports either a string URL or a full entry object. If a string is passed,
 * `opts` may provide per-entry settings.
 *
 * Examples:
 *   add_ajax_schedule('https://example.com/path');
 *   add_ajax_schedule('https://example.com/path', { method: 'POST_JSON', data: { a:1 }, includeCredentials: true });
 *   add_ajax_schedule({ url: 'https://example.com/path', method: 'POST_FORM', data: { a:1 } });
 *
 * Duplicate detection: entries are considered duplicates when their computed key
 * (url|METHOD|JSON(data)|includeCredentials) matches an existing queued entry.
 *
 * @param {string|AjaxScheduleEntry} entryOrUrl - URL string or an `AjaxScheduleEntry` object.
 * @param {AjaxScheduleOptions} [opts] - optional options when first arg is a string: { method, data, includeCredentials }
 * @returns {void}
 */
export function add_ajax_schedule(entryOrUrl, opts = {}) {
  const entry = typeof entryOrUrl === 'string' ? Object.assign({ url: entryOrUrl }, opts) : entryOrUrl || {};

  // create a simple key to detect duplicates: url|method|data-json|credentials
  const keyFor = (e) => {
    try {
      return `${e.url}|${(e.method || 'GET').toString().toUpperCase()}|${JSON.stringify(e.data || '')}|${Boolean(e.includeCredentials)}`;
    } catch (_err) {
      return `${e.url}|${(e.method || 'GET').toString().toUpperCase()}|[unserializable]|${Boolean(e.includeCredentials)}`;
    }
  };

  const newKey = keyFor(entry);
  const exists = ajax_schedule_queue.some((e) => keyFor(typeof e === 'string' ? { url: e } : e) === newKey);

  if (!exists) {
    ajax_schedule_queue.push(entry);
  }
}
