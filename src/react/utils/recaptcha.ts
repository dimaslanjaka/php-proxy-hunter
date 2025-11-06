import { createUrl } from './url';

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
  try {
    const response = await fetch(createUrl('/php_backend/recaptcha.php'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      body: `g-recaptcha-response=${encodeURIComponent(token)}`
    });
    const data = await response.json();
    return !!(data && data.success);
  } catch (e) {
    console.error('[ProxyList] verifyRecaptchaToken error', e);
    return false;
  }
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
