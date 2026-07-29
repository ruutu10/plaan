/**
 * The plumbing the technical-plan wizard talks to the server with. The wizard
 * posts JSON outside Inertia — it is a public page that saves without a page
 * visit — so it needs the CSRF token and the JSON headers on its own.
 */

/** The session's CSRF token, as Laravel leaves it in a cookie. */
export function csrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

/**
 * Headers for a request the server should answer with JSON. `write` adds the
 * CSRF token, which a read does not need.
 */
export function jsonHeaders(write = false): Record<string, string> {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    if (write) {
        headers['X-XSRF-TOKEN'] = csrfToken();
    }

    return headers;
}

export type JsonResponse = {
    ok: boolean;
    status: number;
    data: Record<string, unknown>;
};

/**
 * Fetch JSON without ever rejecting. Every caller flips a busy flag off after
 * awaiting, and a throw would leave the wizard stuck mid-request, so a dropped
 * connection comes back as status 0 — "never arrived" — instead.
 */
export async function requestJson(
    url: string,
    method: 'GET' | 'POST' = 'GET',
    body?: unknown,
): Promise<JsonResponse> {
    let response: globalThis.Response;

    try {
        response = await fetch(url, {
            method,
            headers: {
                ...jsonHeaders(method !== 'GET'),
                ...(body ? { 'Content-Type': 'application/json' } : {}),
            },
            body: body ? JSON.stringify(body) : undefined,
        });
    } catch {
        return { ok: false, status: 0, data: {} };
    }

    let data: Record<string, unknown> = {};

    try {
        data = await response.json();
    } catch {
        data = {};
    }

    return { ok: response.ok, status: response.status, data };
}

/**
 * Turn a failed response into something the user can act on. A dropped
 * connection and an expired session each need their own fix, and a validation
 * failure carries the field errors that say what the server refused.
 */
export function failureMessage(
    status: number,
    data: Record<string, unknown>,
    fallback: string,
): string {
    if (status === 0) {
        return 'Ühendus serveriga katkes. Kontrolli internetiühendust ja proovi uuesti.';
    }

    if (status === 401 || status === 419) {
        return 'Sessioon aegus. Laadi leht uuesti ja logi sisse — plaan on siin mustandina alles.';
    }

    const message = (data.message as string) ?? fallback;
    const errors = Object.values(
        (data.errors as Record<string, string[]>) ?? {},
    ).flat();

    return errors.length ? `${message} ${errors.join(' ')}` : message;
}
