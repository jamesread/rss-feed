import {reactive} from 'vue';
import {requestJson} from './http.js';

/**
 * Create a share-list store. The list lives on the server; the store holds the shared URLs
 * for the current page and sends add and remove requests.
 * @param {Object} options
 * @param {Function} options.request (method, path, body) => Promise<Boolean>
 */
export function createSharedStore({request = async (method, path, body) => (await requestJson(method, path, body)).ok} = {}) {
    const state = reactive({urls: []});

    function setShared(url, isShared) {
        const urls = state.urls.filter(sharedUrl => sharedUrl !== url);
        state.urls = isShared ? [...urls, url] : urls;
    }

    return {
        state,
        /** Replace the shared URLs with the list the server sent with the page. */
        load(urls) {
            state.urls = Array.isArray(urls) ? [...urls] : [];
        },
        count() {
            return state.urls.length;
        },
        isShared(url) {
            return state.urls.includes(url);
        },
        /**
         * Add the post to the share list if it is not listed, otherwise remove it (with its text).
         * The button changes at once and goes back if the server does not accept the change.
         * Resolves to whether the post is in the list afterwards.
         */
        async toggle(post) {
            const wasShared = state.urls.includes(post.url);
            setShared(post.url, !wasShared);

            let ok;
            try {
                ok = wasShared
                    ? await request('DELETE', 'share', {url: post.url})
                    : await request('POST', 'share', {url: post.url});
            } catch {
                ok = false;
            }
            if (!ok) {
                setShared(post.url, wasShared);
                return wasShared;
            }
            return !wasShared;
        },
    };
}

export const shared = createSharedStore();
