import {describe, expect, test} from 'vitest';
import {createStarredStore} from '../../resources/js/starred.js';

function memoryStorage() {
    const data = {};
    return {
        getItem: key => (key in data ? data[key] : null),
        setItem: (key, value) => { data[key] = String(value); },
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

describe('starred store', () => {
    test('toggle stars and unstars a post', () => {
        const store = createStarredStore(memoryStorage(), () => 1);
        expect(store.toggle(makePost(1))).toBe(true);
        expect(store.isStarred('https://example.com/1')).toBe(true);
        expect(store.count()).toBe(1);

        expect(store.toggle(makePost(1))).toBe(false);
        expect(store.isStarred('https://example.com/1')).toBe(false);
        expect(store.count()).toBe(0);
    });

    test('lists most recently starred first', () => {
        let time = 100;
        const store = createStarredStore(memoryStorage(), () => time++);
        store.toggle(makePost(1));
        store.toggle(makePost(2));
        store.toggle(makePost(3));
        expect(store.list().map(post => post.title)).toEqual(['Post 3', 'Post 2', 'Post 1']);
    });

    test('keeps a snapshot that renders after reload, without the server post', () => {
        const storage = memoryStorage();
        createStarredStore(storage, () => 5).toggle(makePost(7));

        const [post] = createStarredStore(storage).list();
        expect(post).toMatchObject({
            url: 'https://example.com/7',
            title: 'Post 7',
            published_at: 1007,
            starred_at: 5,
            source: 'Example News',
            feed: {name: 'Feed A', color: '#f00', url: 'https://example.com/feed.xml', tags: ['#a']},
            feeds: [{name: 'Feed A', color: '#f00', url: 'https://example.com/feed.xml'}],
        });
        expect(post.description).toHaveLength(500);
    });

    test('works without errors when storage throws', () => {
        const throwing = {
            getItem() { throw new Error('SecurityError'); },
            setItem() { throw new Error('SecurityError'); },
        };
        const store = createStarredStore(throwing);
        expect(store.count()).toBe(0);
        expect(() => store.toggle(makePost(1))).not.toThrow();
        expect(store.isStarred('https://example.com/1')).toBe(true);
    });

    test('works without any storage', () => {
        const store = createStarredStore(null);
        store.toggle(makePost(1));
        expect(store.count()).toBe(1);
    });

    test('ignores corrupt stored data', () => {
        const storage = memoryStorage();
        storage.setItem('starred-posts', '{not json');
        expect(createStarredStore(storage).count()).toBe(0);
    });
});
