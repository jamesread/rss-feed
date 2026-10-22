import {reactive} from 'vue';
import {router} from '@inertiajs/vue3';
import {requestJson} from './http.js';

// Where stars were kept before they moved to the server; read once to import them
const LEGACY_STORAGE_KEY = 'starred-posts';
const MAX_DESCRIPTION_LENGTH = 500;

/**
 * Copy the parts of a post needed to show it later, even after it is pruned on the server.
 * @param {Object} post
 */
export function snapshotPost(post) {
    const feed = post.feed || {};
    return {
        url: post.url,
        title: post.title,
        description: (post.description || '').slice(0, MAX_DESCRIPTION_LENGTH),
        published_at: post.published_at,
        thumbnail: post.thumbnail || '',
        source: post.source || '',
        feed: {name: feed.name, color: feed.color, url: feed.url, tags: feed.tags || [], favicon: feed.favicon || ''},
        feeds: (post.feeds || []).map(({name, color, url}) => ({name, color, url})),
    };
}

/**
 * Send a JSON request to the app. Resolves to true for a 2xx response, false otherwise.
 * @param {String} method
 * @param {String} path
 * @param {Object} body
 */
async function sendRequest(method, path, body) {
    return (await requestJson(method, path, body)).ok;
}

/**
 * Create a starred-posts store. Stars live on the server; the store holds the starred URLs
 * for the current page and sends star and unstar requests.
 * @param {Object} options
 * @param {Function} options.request (method, path, body) => Promise<Boolean>
 * @param {Storage|null} options.storage Browser storage holding stars from before they moved to the server
 */
export function createStarredStore({request = sendRequest, storage = null} = {}) {
    const state = reactive({urls: []});

    async function send(method, path, body) {
        try {
            return await request(method, path, body);
        } catch {
            return false;
        }
    }

    function setStarred(url, isStarred) {
        const urls = state.urls.filter(starredUrl => starredUrl !== url);
        state.urls = isStarred ? [...urls, url] : urls;
    }

    return {
        state,
        /** Replace the starred URLs with the list the server sent with the page. */
        load(urls) {
            state.urls = Array.isArray(urls) ? [...urls] : [];
        },
        count() {
            return state.urls.length;
        },
        isStarred(url) {
            return state.urls.includes(url);
        },
        /**
         * Star the post if it is not starred, otherwise unstar it. The button changes at once
         * and goes back if the server does not accept the change. Resolves to the new starred state.
         */
        async toggle(post) {
            const wasStarred = state.urls.includes(post.url);
            setStarred(post.url, !wasStarred);

            const ok = wasStarred
                ? await send('DELETE', 'starred', {url: post.url})
                : await send('POST', 'starred', snapshotPost(post));

            if (!ok) {
                setStarred(post.url, wasStarred);
                return wasStarred;
            }
            return !wasStarred;
        },
        /**
         * Send stars kept in this browser by earlier versions to the server, once.
         * The local copy is removed only after the server accepted them.
         * Resolves to true when stars were imported.
         */
        async importLocal() {
            let posts;
            try {
                posts = JSON.parse(storage?.getItem(LEGACY_STORAGE_KEY) || '[]');
            } catch {
                return false;
            }
            posts = Array.isArray(posts) ? posts.filter(post => post && post.url) : [];
            if (posts.length === 0) {
                return false;
            }

            if (!await send('POST', 'starred/import', {posts})) {
                return false;
            }

            try {
                storage.removeItem(LEGACY_STORAGE_KEY);
            } catch {
                // Storage gone in the meantime: the server ignores stars it already has
            }
            state.urls = [...new Set([...state.urls, ...posts.map(post => post.url)])];
            return true;
        },
    };
}

function browserStorage() {
    try {
        return window.localStorage;
    } catch {
        return null;
    }
}

export const starred = createStarredStore({storage: typeof window === 'undefined' ? null : browserStorage()});

if (typeof window !== 'undefined') {
    starred.importLocal().then(imported => {
        // The starred view lists posts from the server, so fetch it again to show the imported ones
        if (imported && window.location.pathname.endsWith('/starred')) {
            router.reload();
        }
    });
}
