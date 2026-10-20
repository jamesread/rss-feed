import {reactive} from 'vue';

const STORAGE_KEY = 'starred-posts';
const MAX_DESCRIPTION_LENGTH = 500;

/**
 * Copy the parts of a post needed to show it later, even after it is pruned on the server.
 * @param {Object} post
 * @param {Number} starredAt
 */
export function snapshotPost(post, starredAt) {
    const feed = post.feed || {};
    return {
        url: post.url,
        title: post.title,
        description: (post.description || '').slice(0, MAX_DESCRIPTION_LENGTH),
        published_at: post.published_at,
        thumbnail: post.thumbnail || '',
        source: post.source || '',
        starred_at: starredAt,
        feed: {name: feed.name, color: feed.color, url: feed.url, tags: feed.tags || [], favicon: feed.favicon || ''},
        feeds: (post.feeds || []).map(({name, color, url}) => ({name, color, url})),
    };
}

/**
 * Create a starred-posts store on top of the given storage (window.localStorage in the app).
 * Storage may be missing or throw; the store then works for the current page view only.
 * @param {Storage|null} storage
 * @param {() => Number} now
 */
export function createStarredStore(storage, now = () => Math.floor(Date.now() / 1000)) {
    const state = reactive({posts: read()});

    function read() {
        try {
            const posts = JSON.parse(storage?.getItem(STORAGE_KEY) || '[]');
            return Array.isArray(posts) ? posts.filter(post => post && post.url) : [];
        } catch {
            return [];
        }
    }

    function write() {
        try {
            storage?.setItem(STORAGE_KEY, JSON.stringify(state.posts));
        } catch {
            // Storage unavailable or full: stars only last for this page view
        }
    }

    return {
        state,
        /** Starred posts, most recently starred first. */
        list() {
            return [...state.posts].sort((a, b) => b.starred_at - a.starred_at);
        },
        count() {
            return state.posts.length;
        },
        isStarred(url) {
            return state.posts.some(post => post.url === url);
        },
        /** Star the post if it is not starred, otherwise unstar it. Returns the new starred state. */
        toggle(post) {
            const index = state.posts.findIndex(starred => starred.url === post.url);
            if (index === -1) {
                state.posts.push(snapshotPost(post, now()));
            } else {
                state.posts.splice(index, 1);
            }
            write();
            return index === -1;
        },
        /** Reload from storage, e.g. after another tab changed it. */
        reload() {
            state.posts = read();
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

export const starred = createStarredStore(typeof window === 'undefined' ? null : browserStorage());

if (typeof window !== 'undefined') {
    window.addEventListener('storage', event => {
        if (event.key === STORAGE_KEY) {
            starred.reload();
        }
    });
}
