/**
 * Layout for the flip view: splits a server page of posts into full-screen
 * "screen pages" built from fixed templates. Pure functions only, so the
 * result is deterministic for the same posts, server page and device class.
 */

export const POSTS_PER_SERVER_PAGE = 100;

export const GRIDS = {
    'phone-portrait': {cols: 2, rows: 4},
    'phone-landscape': {cols: 4, rows: 2},
    'tablet-portrait': {cols: 4, rows: 6},
    'tablet-landscape': {cols: 6, rows: 4},
};

// Each letter is one slot; slots must be rectangles covering the whole grid.
export const TEMPLATES = {
    'tablet-portrait': [
        ['AABB', 'AABB', 'CCDE', 'CCDE', 'FFGG', 'FFGG'],
        ['AAAA', 'AAAA', 'AAAA', 'BBCC', 'BBCC', 'DDEF'],
        ['AAAB', 'AAAB', 'AAAC', 'DDEC', 'FFEG', 'FFHG'],
        ['AABC', 'AABC', 'DDDD', 'DDDD', 'EFGG', 'EFGG'],
    ],
    'tablet-landscape': [
        ['AAABBC', 'AAABBC', 'AAADEE', 'FFGDEE'],
        ['AABBCC', 'AABBCC', 'DEEFGG', 'DEEHGG'],
        ['AAAABB', 'AAAABB', 'AAAACC', 'AAAADE'],
        ['AABCDD', 'AAEEDD', 'FGEEHI', 'FGJJHI'],
    ],
    'phone-portrait': [
        ['AA', 'AA', 'BC', 'BC'],
        ['AA', 'BC', 'BD', 'EE'],
        ['AA', 'AA', 'BB', 'CD'],
        ['AB', 'AB', 'CC', 'CC'],
    ],
    'phone-landscape': [
        ['AABC', 'AADC'],
        ['ABBC', 'ABBD'],
        ['AAAB', 'AAAC'],
        ['ABCC', 'DBCC'],
    ],
};

// Minimum share of the page area for each tile size class.
export const SIZE_THRESHOLDS = {xl: 0.30, l: 0.15, m: 0.07};

/**
 * Turn a character map into slots with 1-based CSS grid positions.
 * Slots are returned in reading order of their top-left cell.
 */
export function parseTemplate(map) {
    const rows = map.length;
    const cols = rows > 0 ? map[0].length : 0;
    if (rows === 0 || cols === 0 || map.some(row => row.length !== cols)) {
        throw new Error('Template rows must be non-empty and of equal length');
    }

    const bounds = new Map();
    for (let r = 0; r < rows; r++) {
        for (let c = 0; c < cols; c++) {
            const key = map[r][c];
            if (key === ' ' || key === '.') {
                throw new Error(`Template has an empty cell at row ${r + 1}, column ${c + 1}`);
            }
            const b = bounds.get(key);
            if (b) {
                b.minR = Math.min(b.minR, r);
                b.maxR = Math.max(b.maxR, r);
                b.minC = Math.min(b.minC, c);
                b.maxC = Math.max(b.maxC, c);
                b.cells++;
            } else {
                bounds.set(key, {minR: r, maxR: r, minC: c, maxC: c, cells: 1});
            }
        }
    }

    const slots = [];
    for (const [key, b] of bounds) {
        const colSpan = b.maxC - b.minC + 1;
        const rowSpan = b.maxR - b.minR + 1;
        if (colSpan * rowSpan !== b.cells) {
            throw new Error(`Template slot "${key}" is not a rectangle`);
        }
        slots.push({key, col: b.minC + 1, row: b.minR + 1, colSpan, rowSpan, area: b.cells});
    }

    slots.sort((a, b) => (a.row - b.row) || (a.col - b.col));
    return {cols, rows, slots};
}

const PARSED = Object.fromEntries(
    Object.entries(TEMPLATES).map(([cls, maps]) => [cls, maps.map(parseTemplate)])
);

/**
 * Device class from the available viewport size (CSS px).
 */
export function classify(width, height) {
    const size = Math.min(width, height) < 600 ? 'phone' : 'tablet';
    const orientation = width > height ? 'landscape' : 'portrait';
    return `${size}-${orientation}`;
}

export function sizeClass(area, totalArea) {
    const share = area / totalArea;
    if (share >= SIZE_THRESHOLDS.xl) return 'xl';
    if (share >= SIZE_THRESHOLDS.l) return 'l';
    if (share >= SIZE_THRESHOLDS.m) return 'm';
    return 's';
}

/**
 * Pick which template each full screen page uses and how many posts it takes.
 * Screen page i of server page p uses template (i + p - 1) mod n.
 * Returns the full pages and the number of posts left over for the end page.
 */
export function splitPages(postCount, deviceClass, serverPage) {
    const templates = PARSED[deviceClass];
    const pages = [];
    let start = 0;

    for (let i = 0; ; i++) {
        const templateIndex = (i + serverPage - 1) % templates.length;
        const count = templates[templateIndex].slots.length;
        if (postCount - start < count) {
            break;
        }
        pages.push({templateIndex, start, count});
        start += count;
    }

    return {pages, rest: postCount - start};
}

/**
 * Assign posts (indices into the server page) to slots: posts with a thumbnail
 * take the largest slots first. Returns tiles in slot reading order.
 */
export function assignSlots(slots, postIndices, posts) {
    const bySize = slots
        .map((slot, order) => ({slot, order}))
        .sort((a, b) => (b.slot.area - a.slot.area) || (a.order - b.order));

    const withThumb = postIndices.filter(i => posts[i].thumbnail);
    const withoutThumb = postIndices.filter(i => !posts[i].thumbnail);
    const ordered = withThumb.concat(withoutThumb);

    const assigned = new Array(slots.length);
    bySize.forEach(({order}, n) => {
        assigned[order] = ordered[n];
    });

    return slots.map((slot, order) => ({slot, postIndex: assigned[order]}));
}

/**
 * Uniform grid for n tiles, shaped to the viewport. The last tile spans any
 * columns left over in the last row so there are no gaps.
 */
export function uniformLayout(n, width, height) {
    const cols = Math.min(n, Math.max(1, Math.round(Math.sqrt(n * width / height))));
    const rows = Math.ceil(n / cols);
    const slots = [];
    for (let i = 0; i < n; i++) {
        const row = Math.floor(i / cols) + 1;
        const col = (i % cols) + 1;
        const colSpan = i === n - 1 ? cols - col + 1 : 1;
        slots.push({key: String(i), col, row, colSpan, rowSpan: 1, area: colSpan});
    }
    return {cols, rows, slots};
}

/**
 * Build all screen pages for one server page of posts.
 *
 * Page shape: {type: 'template'|'end'|'empty', cols, rows, tiles}
 * Tile shape: {kind: 'post'|'prev'|'next', postIndex?, col, row, colSpan, rowSpan, size}
 */
export function buildPages({posts, page, width, height, hasMore = posts.length === POSTS_PER_SERVER_PAGE}) {
    const deviceClass = classify(width, height);
    const grid = GRIDS[deviceClass];
    const hasPrev = page > 1;
    const hasNext = hasMore;
    const navTiles = [];
    if (hasPrev) navTiles.push({kind: 'prev'});
    if (hasNext) navTiles.push({kind: 'next'});

    if (posts.length === 0) {
        return {deviceClass, grid, pages: [{type: 'empty', cols: 1, rows: 1, tiles: [], nav: {hasPrev, hasNext}}]};
    }

    const {pages: split, rest} = splitPages(posts.length, deviceClass, page);
    const pages = split.map(({templateIndex, start, count}) => {
        const template = PARSED[deviceClass][templateIndex];
        const total = template.cols * template.rows;
        const indices = Array.from({length: count}, (_, k) => start + k);
        const tiles = assignSlots(template.slots, indices, posts).map(({slot, postIndex}) => ({
            kind: 'post',
            postIndex,
            col: slot.col,
            row: slot.row,
            colSpan: slot.colSpan,
            rowSpan: slot.rowSpan,
            size: sizeClass(slot.area, total),
        }));
        return {type: 'template', templateIndex, cols: template.cols, rows: template.rows, tiles};
    });

    const endItems = [];
    for (let i = posts.length - rest; i < posts.length; i++) {
        endItems.push({kind: 'post', postIndex: i});
    }
    endItems.push(...navTiles);

    if (endItems.length > 0) {
        const layout = uniformLayout(endItems.length, width, height);
        const total = layout.cols * layout.rows;
        const tiles = endItems.map((item, i) => {
            const slot = layout.slots[i];
            return {
                ...item,
                col: slot.col,
                row: slot.row,
                colSpan: slot.colSpan,
                rowSpan: slot.rowSpan,
                size: sizeClass(slot.area, total),
            };
        });
        pages.push({type: 'end', cols: layout.cols, rows: layout.rows, tiles});
    }

    return {deviceClass, grid, pages};
}

/**
 * Index of the screen page that shows the given post, or 0 if none does.
 */
export function findPageForPost(pages, postIndex) {
    const found = pages.findIndex(p => p.tiles.some(t => t.kind === 'post' && t.postIndex === postIndex));
    return found === -1 ? 0 : found;
}

/**
 * Index of the first post shown on a screen page, used as the anchor when re-laying out.
 */
export function firstPostOnPage(page) {
    const indices = page.tiles.filter(t => t.kind === 'post').map(t => t.postIndex);
    return indices.length ? Math.min(...indices) : null;
}
