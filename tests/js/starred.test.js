import {afterEach, describe, expect, test, vi} from 'vitest';
import {createStarredStore, snapshotPost} from '../../resources/js/starred.js';

function memoryStorage() {
    const data = {};
    return {
        getItem: key => (key in data ? data[key] : null),
        setItem: (key, value) => { data[key] = String(value); },
        removeItem: key => { delete data[key]; },
        data,
    };
}

function makePost(n) {
    return {
        url: `https://example.com/${n}`,
        title: `Post ${n}`,
        description: 'x'.repeat(800),
        published_at: 1000 + n,
        thumbnail: `thumbs/1/${n}.jpeg`,
        source: 'Example News',
        feed: {name: 'Feed A', color: '#f00', url: 'https://example.com/feed.xml', tags: ['#a'], favicon: 'favicons/1.png'},
        feeds: [{name: 'Feed A', color: '#f00', url: 'https://example.com/feed.xml', tags: ['#a']}],
    };
}

function recordingRequest(result = true) {
    const calls = [];
    const request = async (method, path, body) => {
        calls.push({method, path, body});
        if (result instanceof Error) {
            throw result;
        }
        return result;
    };
    return {request, calls};
}

describe('starred store', () => {
    test('star sends a snapshot and unstar sends the URL', async () => {
        const {request, calls} = recordingRequest();
        const store = createStarredStore({request});

        expect(await store.toggle(makePost(1))).toBe(true);
        expect(store.isStarred('https://example.com/1')).toBe(true);
        expect(store.count()).toBe(1);
        expect(calls[0]).toMatchObject({method: 'POST', path: 'starred', body: {url: 'https://example.com/1', title: 'Post 1'}});

        expect(await store.toggle(makePost(1))).toBe(false);
        expect(store.isStarred('https://example.com/1')).toBe(false);
        expect(store.count()).toBe(0);
        expect(calls[1]).toEqual({method: 'DELETE', path: 'starred', body: {url: 'https://example.com/1'}});
    });

    test('updates the star before the server answers', () => {
        const store = createStarredStore({request: () => new Promise(() => {})});
        store.toggle(makePost(1));
        expect(store.isStarred('https://example.com/1')).toBe(true);
    });

    test('rolls back when the server rejects the change', async () => {
        const {request} = recordingRequest(false);
        const store = createStarredStore({request});
        store.load(['https://example.com/2']);

        expect(await store.toggle(makePost(1))).toBe(false);
        expect(store.isStarred('https://example.com/1')).toBe(false);

        expect(await store.toggle(makePost(2))).toBe(true);
        expect(store.isStarred('https://example.com/2')).toBe(true);
    });

    test('rolls back when the request throws', async () => {
        const store = createStarredStore({request: recordingRequest(new Error('offline')).request});
        expect(await store.toggle(makePost(1))).toBe(false);
        expect(store.isStarred('https://example.com/1')).toBe(false);
    });

    test('load replaces the starred URLs', () => {
        const store = createStarredStore({request: recordingRequest().request});
        store.load(['https://example.com/1', 'https://example.com/2']);
        expect(store.count()).toBe(2);
        store.load(['https://example.com/3']);
        expect(store.isStarred('https://example.com/1')).toBe(false);
        expect(store.count()).toBe(1);
        store.load(undefined);
        expect(store.count()).toBe(0);
    });

    test('snapshot keeps what is needed to show the post later', () => {
        const snapshot = snapshotPost(makePost(7));
        expect(snapshot).toMatchObject({
            url: 'https://example.com/7',
            title: 'Post 7',
            published_at: 1007,
            thumbnail: 'thumbs/1/7.jpeg',
            source: 'Example News',
            feed: {name: 'Feed A', color: '#f00', url: 'https://example.com/feed.xml', tags: ['#a'], favicon: 'favicons/1.png'},
            feeds: [{name: 'Feed A', color: '#f00', url: 'https://example.com/feed.xml'}],
        });
        expect(snapshot.description).toHaveLength(500);
    });
});

describe('import of browser stars', () => {
    const legacy = [{...snapshotPost(makePost(1)), starred_at: 5}, {...snapshotPost(makePost(2)), starred_at: 6}];

    test('sends local stars once and removes them after success', async () => {
        const storage = memoryStorage();
        storage.setItem('starred-posts', JSON.stringify(legacy));
        const {request, calls} = recordingRequest();
        const store = createStarredStore({request, storage});

        expect(await store.importLocal()).toBe(true);
        expect(calls).toEqual([{method: 'POST', path: 'starred/import', body: {posts: legacy}}]);
        expect(storage.getItem('starred-posts')).toBeNull();
        expect(store.count()).toBe(2);
        expect(store.isStarred('https://example.com/2')).toBe(true);

        expect(await store.importLocal()).toBe(false);
        expect(calls).toHaveLength(1);
    });

    test('keeps local stars when the import fails', async () => {
        const storage = memoryStorage();
        storage.setItem('starred-posts', JSON.stringify(legacy));
        const store = createStarredStore({request: recordingRequest(false).request, storage});

        expect(await store.importLocal()).toBe(false);
        expect(storage.getItem('starred-posts')).toBe(JSON.stringify(legacy));
        expect(store.count()).toBe(0);
    });

    test.each([
        ['nothing stored', null],
        ['an empty list', '[]'],
        ['corrupt data', '{not json'],
        ['entries without URL', '[{"title": "x"}, null]'],
    ])('sends nothing for %s', async (name, value) => {
        const storage = memoryStorage();
        if (value !== null) {
            storage.setItem('starred-posts', value);
        }
        const {request, calls} = recordingRequest();

        expect(await createStarredStore({request, storage}).importLocal()).toBe(false);
        expect(calls).toHaveLength(0);
    });

    test('works without errors when storage throws or is missing', async () => {
        const throwing = {
            getItem() { throw new Error('SecurityError'); },
            removeItem() { throw new Error('SecurityError'); },
        };
        const {request, calls} = recordingRequest();

        expect(await createStarredStore({request, storage: throwing}).importLocal()).toBe(false);
        expect(await createStarredStore({request, storage: null}).importLocal()).toBe(false);
        expect(calls).toHaveLength(0);
    });
});

describe('default request', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    test('sends the decoded XSRF-TOKEN cookie and reports failures', async () => {
        vi.stubGlobal('document', {cookie: 'other=1; XSRF-TOKEN=abc%3D%3D; last=2'});
        const fetch = vi.fn(async () => ({ok: true}));
        vi.stubGlobal('fetch', fetch);
        const store = createStarredStore();

        expect(await store.toggle(makePost(1))).toBe(true);
        const [path, options] = fetch.mock.calls[0];
        expect(path).toBe('starred');
        expect(options.method).toBe('POST');
        expect(options.headers['X-XSRF-TOKEN']).toBe('abc==');
        expect(JSON.parse(options.body).url).toBe('https://example.com/1');

        fetch.mockImplementation(async () => { throw new TypeError('Failed to fetch'); });
        expect(await store.toggle(makePost(1))).toBe(true);
        expect(store.isStarred('https://example.com/1')).toBe(true);
    });
});
