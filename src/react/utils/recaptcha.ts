import { createUrl } from './url';

// Module-level caches to avoid re-sending the same token frequently.
// - `verifiedTokenCache` stores boolean results for tokens that were already
//   verified (true/false).
// - `inflightVerifications` stores a Promise<boolean> for tokens that are
//   currently being verified so concurrent callers share the same network
//   request rather than issuing duplicates.
const verifiedTokenCache = new Map<string, boolean>();
const inflightVerifications = new Map<string, Promise<boolean>>();

/**
 * Verify a Google reCAPTCHA token by POSTing it to the backend verification endpoint.
 *
 * The backend endpoint is expected to accept a urlencoded body field named
 * `g-recaptcha-response` and return JSON that includes a `success` boolean.
 *
 * This function:
 * - Sends a POST request with Content-Type: application/x-www-form-urlencoded
 * - Encodes the token with encodeURIComponent
 * - Parses the JSON response and returns true if `data.success` is truthy
 * - Logs and returns false on network or parsing errors
 *
 * @param token - The client-side reCAPTCHA token provided by the reCAPTCHA widget.
 * @returns A Promise that resolves to true when verification succeeds, otherwise false.
 *
 * @example
 * const ok = await verifyRecaptcha(token);
 * if (!ok) {
 *   // handle failed verification
 * }
 */
export async function verifyRecaptcha(token: string): Promise<boolean> {
  if (!token) return false;

  // Return cached result when available.
  if (verifiedTokenCache.has(token)) {
    return verifiedTokenCache.get(token)!;
  }

  // If there's already a pending verification for this token, return the
  // existing promise so we don't POST the same token multiple times.
  if (inflightVerifications.has(token)) {
    return inflightVerifications.get(token)!;
  }

  const verification = (async (): Promise<boolean> => {
    try {
      const response = await fetch(createUrl('/php_backend/recaptcha.php'), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `g-recaptcha-response=${encodeURIComponent(token)}`
      });
      const data = await response.json();
      const ok = !!(data && data.success);

      // Cache the result (positive or negative) for future callers. Note:
      // tokens are single-use by design on some backends, so the cache lifetime
      // should be chosen carefully; this implementation keeps it until the
      // page is reloaded. If single-use tokens are used, consider caching only
      // positive results or not caching at all.
      verifiedTokenCache.set(token, ok);
      return ok;
    } catch (e) {
      console.error('[ProxyList] verifyRecaptchaToken error', e);
      // Cache the failure so callers don't repeatedly hammer the backend
      // during transient network errors. This can be adjusted if undesired.
      verifiedTokenCache.set(token, false);
      return false;
    } finally {
      // Remove from in-flight map so future calls will create a new request if
      // needed (or pick up cached result above).
      inflightVerifications.delete(token);
    }
  })();

  inflightVerifications.set(token, verification);
  return verification;
}

/**
 * Check whether reCAPTCHA protection is currently enabled/active on the backend.
 *
 * This helper posts a urlencoded `status=1` field to the same verification endpoint
 * used for token validation. The backend should respond with JSON containing a
 * `success` boolean indicating whether reCAPTCHA checks are required.
 *
 * Behavior:
 * - Sends a POST request with Content-Type: application/x-www-form-urlencoded
 * - Parses the JSON response and returns true if `data.success` is truthy
 * - Logs errors and returns false on network or parsing failures
 *
 * @returns A Promise that resolves to true when reCAPTCHA is enabled/active, otherwise false.
 *
 * @example
 * const enabled = await checkRecaptchaStatus();
 * if (enabled) {
 *   // show captcha UI or enforce validation
 * }
 */
export async function checkRecaptchaStatus(): Promise<boolean> {
  try {
    const response = await fetch(createUrl('/php_backend/recaptcha.php'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      body: 'status=1'
    });
    const data = await response.json();
    return !!(data && data.success);
  } catch (e) {
    console.error('[ProxyList] checkRecaptchaStatus error', e);
    return false;
  }
}
