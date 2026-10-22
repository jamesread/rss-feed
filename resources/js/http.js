function readCookie(name) {
    const match = document.cookie.split('; ').find(cookie => cookie.startsWith(name + '='));
    return match ? decodeURIComponent(match.slice(name.length + 1)) : '';
}

/**
 * Send a JSON request to the app, with the CSRF token from the XSRF-TOKEN cookie.
 * Resolves to {ok, status, data}, where data is the parsed JSON response body or null.
 * A network failure resolves to {ok: false, status: 0, data: null}.
 * Paths are relative so they follow the page's <base> when the app runs on a sub-path.
 * @param {String} method
 * @param {String} path
 * @param {Object} body
 */
export async function requestJson(method, path, body) {
    let response;
    try {
        response = await fetch(path, {
            method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-XSRF-TOKEN': readCookie('XSRF-TOKEN'),
            },
            body: JSON.stringify(body),
        });
    } catch {
        return {ok: false, status: 0, data: null};
    }

    let data = null;
    try {
        data = await response.json();
    } catch {
        // Empty (204) or non-JSON body
    }
    return {ok: response.ok, status: response.status, data};
}
