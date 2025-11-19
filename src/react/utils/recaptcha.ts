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
export async function verifyRecaptcha(token?: Partial<string | null>): Promise<boolean> {
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
 * Check whether the current reCAPTCHA session is expired/needs a new token.
 *
 * The backend endpoint accepts a urlencoded `status=1` field and returns JSON
 * that includes a `success` boolean. Historically this helper returned
 * `true` when reCAPTCHA checks were required; after renaming it returns
 * `true` when the session is expired (i.e. a new token should be requested).
 *
 * Behavior:
 * - Sends a POST request with Content-Type: application/x-www-form-urlencoded
 * - Parses the JSON response and returns true when the session is expired/needs
 *   verification (the backend indicates that checks are required), otherwise
 *   returns false.
 * - Logs errors and returns false on network or parsing failures.
 *
 * @returns A Promise that resolves to `true` when the reCAPTCHA session is
 * expired (caller should request a new token), otherwise `false`.
 *
 * @example
 * const expired = await checkRecaptchaSessionExpired();
 * if (expired) {
 *   // request a new token / show captcha UI
 * }
 */
export async function checkRecaptchaSessionExpired(): Promise<boolean> {
  try {
    const response = await fetch(createUrl('/php_backend/recaptcha.php'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      body: 'status=1'
    });
    const data = await response.json();
    // Backend's `success` field means the captcha/session is already verified
    // (e.g. `{"message":"Captcha already verified","success":true}`).
    // We should return `true` only when the session is expired / a new
    // verification is required. That means we invert the backend `success`
    // semantics here: when `data.success` is truthy the session is NOT expired
    // (return false). When `data.success` is falsy we return true to indicate
    // a new captcha token is required.
    if (data && typeof data.success === 'boolean') {
      return !data.success;
    }
    // If response shape is unexpected, conservatively treat it as not expired
    // (do not show the modal) to avoid blocking users on malformed responses.
    return false;
  } catch (e) {
    console.error('[ProxyList] checkRecaptchaSessionExpired error', e);
    return false;
  }
}
