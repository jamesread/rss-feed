import {afterEach, beforeEach, describe, expect, test, vi} from 'vitest';
import {feedFetchErrorLabel} from '../../resources/js/util.js';

describe('feedFetchErrorLabel', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-10-20T12:00:00Z'));
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    test('includes the reason and the relative last success', () => {
        const twoDaysAgo = Date.now() / 1000 - 2 * 86400;
        expect(feedFetchErrorLabel('HTTP 404', twoDaysAgo)).toBe('Last fetch failed: HTTP 404 · last success 2 days ago');
    });

    test('says never when the feed never succeeded', () => {
        expect(feedFetchErrorLabel('Invalid feed content', 0)).toBe('Last fetch failed: Invalid feed content · last success never');
    });
});
