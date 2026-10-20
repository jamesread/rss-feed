import {describe, expect, test} from 'vitest';
import {listingData, sinceLabel} from '../../resources/js/listing.js';

describe('listingData', () => {
    test('keeps search and time filter when paging', () => {
        expect(listingData({page: 2, query: 'partnership', since: '7d'})).toEqual({page: 2, query: 'partnership', since: '7d'});
    });

    test('leaves out page 1 and empty values', () => {
        expect(listingData({page: 1, query: '', since: ''})).toEqual({});
        expect(listingData({since: '24h'})).toEqual({since: '24h'});
    });
});

describe('sinceLabel', () => {
    test('maps values to labels', () => {
        expect(sinceLabel('24h')).toBe('24 h');
        expect(sinceLabel('7d')).toBe('7 days');
        expect(sinceLabel('30d')).toBe('30 days');
        expect(sinceLabel('')).toBe('');
        expect(sinceLabel('1y')).toBe('');
    });
});
