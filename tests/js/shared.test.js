import {describe, expect, test} from 'vitest';
import {createSharedStore} from '../../resources/js/shared.js';

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

describe('shared store', () => {
    test('load, count and isShared', () => {
        const store = createSharedStore({request: recordingRequest().request});
        store.load(['https://example.com/1', 'https://example.com/2']);

        expect(store.count()).toBe(2);
        expect(store.isShared('https://example.com/1')).toBe(true);
        expect(store.isShared('https://example.com/3')).toBe(false);

        store.load(null);
        expect(store.count()).toBe(0);
    });

    test('toggle adds a post and a second toggle removes it', async () => {
        const {request, calls} = recordingRequest();
        const store = createSharedStore({request});

        expect(await store.toggle({url: 'https://example.com/1', title: 'Post'})).toBe(true);
        expect(store.isShared('https://example.com/1')).toBe(true);

        expect(await store.toggle({url: 'https://example.com/1'})).toBe(false);
        expect(store.isShared('https://example.com/1')).toBe(false);

        expect(calls).toEqual([
            {method: 'POST', path: 'share', body: {url: 'https://example.com/1'}},
            {method: 'DELETE', path: 'share', body: {url: 'https://example.com/1'}},
        ]);
    });

    test('failed toggle is reverted', async () => {
        for (const result of [false, new Error('offline')]) {
            const store = createSharedStore({request: recordingRequest(result).request});

            expect(await store.toggle({url: 'https://example.com/1'})).toBe(false);
            expect(store.isShared('https://example.com/1')).toBe(false);

            store.load(['https://example.com/2']);
            expect(await store.toggle({url: 'https://example.com/2'})).toBe(true);
            expect(store.isShared('https://example.com/2')).toBe(true);
        }
    });
});
