import { apiUrl } from './config';

let tokenRequest = null;

/**
 * The CSRF token comes from a no-store endpoint (pages are HTTP-cached per user context, so it cannot be
 * embedded in the markup). Fetched once, lazily, and reused.
 */
export function getCsrfToken() {
    if (!tokenRequest) {
        tokenRequest = fetch(apiUrl('csrf'), { credentials: 'same-origin' })
            .then((response) => {
                if (!response.ok) throw new Error('Unable to get a CSRF token');
                return response.json();
            })
            .then((data) => data.token)
            .catch((error) => {
                tokenRequest = null;
                throw error;
            });
    }

    return tokenRequest;
}

/**
 * fetch() that adds the CSRF header to state-changing requests.
 */
export async function apiFetch(url, options = {}) {
    const method = (options.method || 'GET').toUpperCase();
    if (method === 'GET' || method === 'HEAD') {
        return fetch(url, options);
    }

    const token = await getCsrfToken();

    return fetch(url, { ...options, headers: { ...(options.headers || {}), 'X-CSRF-Token': token } });
}

/**
 * True when a postMessage event comes from the origin of the iframe URL we opened (the admin siteaccess),
 * so other windows or frames cannot trigger reloads or draft discards.
 */
export function isTrustedMessage(event, iframeUrl) {
    if (!iframeUrl) return false;

    try {
        return event.origin === new URL(iframeUrl, window.location.href).origin;
    } catch {
        return false;
    }
}
