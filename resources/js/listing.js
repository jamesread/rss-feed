/**
 * Time filter options for post listings, in display order.
 */
export const SINCE_OPTIONS = [
    {value: '', label: 'All'},
    {value: '24h', label: '24 h'},
    {value: '7d', label: '7 days'},
    {value: '30d', label: '30 days'},
];

/**
 * Get the display label for a time filter value, or an empty string for none.
 * @param {String} since
 */
export function sinceLabel(since) {
    return since ? (SINCE_OPTIONS.find(option => option.value === since)?.label ?? '') : '';
}

/**
 * Build the query data for a post listing visit, leaving out empty values.
 * @param {{page?: Number, query?: String, since?: String}} params
 */
export function listingData({page = 1, query = '', since = ''}) {
    const data = {};
    if (page !== 1) data.page = page;
    if (query) data.query = query;
    if (since) data.since = since;
    return data;
}
