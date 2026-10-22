import {afterEach, describe, expect, test, vi} from 'vitest';
import {requestJson} from '../../resources/js/http.js';

describe('requestJson', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    test('sends JSON with the decoded XSRF-TOKEN cookie', async () => {
        vi.stubGlobal('document', {cookie: 'other=1; XSRF-TOKEN=abc%3D%3D; last=2'});
        const fetch = vi.fn(async () => ({ok: true, status: 204, json: async () => { throw new SyntaxError('empty'); }}));
        vi.stubGlobal('fetch', fetch);

        expect(await requestJson('PUT', 'settings', {theme: 'dark'})).toEqual({ok: true, status: 204, data: null});
        const [path, options] = fetch.mock.calls[0];
        expect(path).toBe('settings');
        expect(options.method).toBe('PUT');
        expect(options.headers['X-XSRF-TOKEN']).toBe('abc==');
        expect(options.headers.Accept).toBe('application/json');
        expect(JSON.parse(options.body)).toEqual({theme: 'dark'});
    });

    test('returns the parsed body of a validation error', async () => {
        vi.stubGlobal('document', {cookie: ''});
        const errors = {message: 'Invalid', errors: {'rows.0.tags': ['Tags are required.']}};
        vi.stubGlobal('fetch', vi.fn(async () => ({ok: false, status: 422, json: async () => errors})));

        expect(await requestJson('PUT', 'settings/feeds', {rows: []})).toEqual({ok: false, status: 422, data: errors});
    });

    test('reports a network failure', async () => {
        vi.stubGlobal('document', {cookie: ''});
        vi.stubGlobal('fetch', vi.fn(async () => { throw new TypeError('Failed to fetch'); }));

        expect(await requestJson('PUT', 'settings', {})).toEqual({ok: false, status: 0, data: null});
    });
});
