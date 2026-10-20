import {describe, expect, test} from 'vitest';
import {
    GRIDS,
    TEMPLATES,
    assignSlots,
    buildPages,
    classify,
    findPageForPost,
    firstPostOnPage,
    parseTemplate,
    sizeClass,
    splitPages,
    uniformLayout,
} from '../../resources/js/flip/layout.js';

const CLASSES = Object.keys(GRIDS);

const VIEWPORTS = {
    'phone-portrait': [390, 760],
    'phone-landscape': [750, 370],
    'tablet-portrait': [820, 1180],
    'tablet-landscape': [1180, 820],
};

function makePosts(count, thumbnailEvery = 2) {
    return Array.from({length: count}, (_, i) => ({
        url: `https://example.com/${i}`,
        title: `Post ${i}`,
        thumbnail: i % thumbnailEvery === 0 ? `thumbs/1/${i}.png` : null,
    }));
}

function assertExactCover(cols, rows, slots) {
    const cells = new Array(cols * rows).fill(0);
    for (const s of slots) {
        expect(s.col).toBeGreaterThanOrEqual(1);
        expect(s.row).toBeGreaterThanOrEqual(1);
        expect(s.col + s.colSpan - 1).toBeLessThanOrEqual(cols);
        expect(s.row + s.rowSpan - 1).toBeLessThanOrEqual(rows);
        for (let r = s.row; r < s.row + s.rowSpan; r++) {
            for (let c = s.col; c < s.col + s.colSpan; c++) {
                cells[(r - 1) * cols + (c - 1)]++;
            }
        }
    }
    expect(cells.every(n => n === 1)).toBe(true);
}

describe('parseTemplate', () => {
    test('parses a valid map into rectangles', () => {
        const t = parseTemplate(['AAB', 'AAC']);
        expect(t.cols).toBe(3);
        expect(t.rows).toBe(2);
        expect(t.slots).toEqual([
            {key: 'A', col: 1, row: 1, colSpan: 2, rowSpan: 2, area: 4},
            {key: 'B', col: 3, row: 1, colSpan: 1, rowSpan: 1, area: 1},
            {key: 'C', col: 3, row: 2, colSpan: 1, rowSpan: 1, area: 1},
        ]);
    });

    test('throws on an L-shaped slot', () => {
        expect(() => parseTemplate(['AA', 'AB'])).toThrow(/not a rectangle/);
    });

    test('throws on a ragged row', () => {
        expect(() => parseTemplate(['AA', 'A'])).toThrow(/equal length/);
    });

    test('throws on an empty cell', () => {
        expect(() => parseTemplate(['A.', 'AA'])).toThrow(/empty cell/);
    });
});

describe('templates', () => {
    test.each(CLASSES)('%s templates parse and cover the grid exactly', (cls) => {
        const {cols, rows} = GRIDS[cls];
        expect(TEMPLATES[cls].length).toBeGreaterThanOrEqual(4);
        for (const map of TEMPLATES[cls]) {
            const t = parseTemplate(map);
            expect(t.cols).toBe(cols);
            expect(t.rows).toBe(rows);
            assertExactCover(cols, rows, t.slots);
        }
    });

    test.each(CLASSES)('%s adjacent templates in the rotation differ', (cls) => {
        const maps = TEMPLATES[cls].map(m => m.join('|'));
        for (let i = 0; i < maps.length; i++) {
            expect(maps[i]).not.toBe(maps[(i + 1) % maps.length]);
        }
    });

    test.each(CLASSES)('%s templates have at most one xl and at least two size classes', (cls) => {
        for (const map of TEMPLATES[cls]) {
            const t = parseTemplate(map);
            const sizes = t.slots.map(s => sizeClass(s.area, t.cols * t.rows));
            expect(sizes.filter(s => s === 'xl').length).toBeLessThanOrEqual(1);
            expect(new Set(sizes).size).toBeGreaterThanOrEqual(2);
        }
    });
});

describe('classify', () => {
    test.each([
        ['iPhone portrait', 390, 844, 'phone-portrait'],
        ['iPhone landscape', 844, 390, 'phone-landscape'],
        ['iPad mini portrait', 744, 1133, 'tablet-portrait'],
        ['iPad mini landscape', 1133, 744, 'tablet-landscape'],
        ['iPad Air portrait', 820, 1180, 'tablet-portrait'],
        ['iPad Air landscape', 1180, 820, 'tablet-landscape'],
        ['iPad Pro portrait', 1024, 1366, 'tablet-portrait'],
        ['iPad Pro landscape', 1366, 1024, 'tablet-landscape'],
        ['desktop', 1920, 1080, 'tablet-landscape'],
        ['599 short side', 599, 900, 'phone-portrait'],
        ['600 short side', 600, 900, 'tablet-portrait'],
        ['square', 800, 800, 'tablet-portrait'],
    ])('%s', (_, w, h, expected) => {
        expect(classify(w, h)).toBe(expected);
    });
});

describe('splitPages', () => {
    test.each(CLASSES)('%s keeps every post once and in order', (cls) => {
        const {pages, rest} = splitPages(100, cls, 1);
        let next = 0;
        for (const p of pages) {
            expect(p.start).toBe(next);
            next += p.count;
        }
        expect(next + rest).toBe(100);
    });

    test.each(CLASSES)('%s consecutive pages use different templates', (cls) => {
        const {pages} = splitPages(100, cls, 1);
        for (let i = 1; i < pages.length; i++) {
            expect(pages[i].templateIndex).not.toBe(pages[i - 1].templateIndex);
        }
    });

    test('server page 2 starts on a different template than page 1', () => {
        for (const cls of CLASSES) {
            expect(splitPages(100, cls, 2).pages[0].templateIndex)
                .not.toBe(splitPages(100, cls, 1).pages[0].templateIndex);
        }
    });
});

describe('assignSlots', () => {
    test('thumbnail posts take the largest slots, order kept within groups', () => {
        const slots = parseTemplate(['AAB', 'AAC']).slots; // A=4, B=1, C=1
        const posts = [
            {thumbnail: null},
            {thumbnail: null},
            {thumbnail: 'x.png'},
        ];
        const tiles = assignSlots(slots, [0, 1, 2], posts);
        expect(tiles.map(t => [t.slot.key, t.postIndex])).toEqual([
            ['A', 2],
            ['B', 0],
            ['C', 1],
        ]);
    });

    test('without thumbnails, order follows slot size then reading order', () => {
        const slots = parseTemplate(['ABB', 'CBB']).slots; // A=1, B=4, C=1
        const posts = [{}, {}, {}];
        const tiles = assignSlots(slots, [0, 1, 2], posts);
        expect(tiles.map(t => [t.slot.key, t.postIndex])).toEqual([
            ['A', 1],
            ['B', 0],
            ['C', 2],
        ]);
    });
});

describe('uniformLayout', () => {
    test.each([1, 2, 3, 4, 5, 7])('%i tiles cover the area with no gaps', (n) => {
        for (const [w, h] of Object.values(VIEWPORTS)) {
            const layout = uniformLayout(n, w, h);
            expect(layout.slots.length).toBe(n);
            assertExactCover(layout.cols, layout.rows, layout.slots);
        }
    });
});

describe('buildPages', () => {
    const [w, h] = VIEWPORTS['tablet-portrait'];

    test('every post appears exactly once', () => {
        for (const [cls, [vw, vh]] of Object.entries(VIEWPORTS)) {
            const {deviceClass, pages} = buildPages({posts: makePosts(100), page: 1, width: vw, height: vh});
            expect(deviceClass).toBe(cls);
            const seen = pages.flatMap(p => p.tiles.filter(t => t.kind === 'post').map(t => t.postIndex));
            expect(seen.sort((a, b) => a - b)).toEqual(Array.from({length: 100}, (_, i) => i));
        }
    });

    test('screen pages keep newest-first order', () => {
        const {pages} = buildPages({posts: makePosts(100), page: 1, width: w, height: h});
        for (let i = 1; i < pages.length; i++) {
            const prev = Math.max(...pages[i - 1].tiles.filter(t => t.kind === 'post').map(t => t.postIndex));
            const first = firstPostOnPage(pages[i]);
            if (first !== null) {
                expect(first).toBeGreaterThan(prev);
            }
        }
    });

    test('is deterministic', () => {
        const input = {posts: makePosts(100, 3), page: 2, width: w, height: h};
        expect(buildPages(input)).toEqual(buildPages(input));
    });

    test('tablet classes give 10-20 pages, phones more', () => {
        const posts = makePosts(100);
        const count = (cls) => buildPages({posts, page: 1, width: VIEWPORTS[cls][0], height: VIEWPORTS[cls][1]}).pages.length;
        for (const cls of ['tablet-portrait', 'tablet-landscape']) {
            expect(count(cls)).toBeGreaterThanOrEqual(10);
            expect(count(cls)).toBeLessThanOrEqual(20);
        }
        for (const cls of ['phone-portrait', 'phone-landscape']) {
            expect(count(cls)).toBeGreaterThan(count('tablet-portrait'));
        }
    });

    test('full server page ends with a next tile, page > 1 adds a prev tile', () => {
        const {pages} = buildPages({posts: makePosts(100), page: 2, width: w, height: h});
        const end = pages[pages.length - 1];
        expect(end.type).toBe('end');
        expect(end.tiles.map(t => t.kind).slice(-2)).toEqual(['prev', 'next']);
        assertExactCover(end.cols, end.rows, end.tiles);
    });

    test('next tile follows hasMore, not the post count', () => {
        const collapsed = buildPages({posts: makePosts(94), page: 1, width: w, height: h, hasMore: true}).pages;
        expect(collapsed[collapsed.length - 1].tiles.map(t => t.kind)).toContain('next');

        const last = buildPages({posts: makePosts(100), page: 1, width: w, height: h, hasMore: false}).pages;
        expect(last.flatMap(p => p.tiles).some(t => t.kind === 'next')).toBe(false);
    });

    test('no end page when nothing is left and there is no nav', () => {
        // tablet-portrait page 1 rotation: 7, 6, 8, 7 -> 28 posts fill four pages exactly
        const {pages} = buildPages({posts: makePosts(28), page: 1, width: w, height: h});
        expect(pages.length).toBe(4);
        expect(pages.every(p => p.type === 'template')).toBe(true);
    });

    test('end page with only nav tiles when nothing is left', () => {
        const {pages} = buildPages({posts: makePosts(28), page: 3, width: w, height: h});
        const end = pages[pages.length - 1];
        expect(end.type).toBe('end');
        expect(end.tiles.map(t => t.kind)).toEqual(['prev']);
    });

    test('leftover posts go to the end page with the nav tiles', () => {
        // server page 2 starts one template later: 6 + 8 = 14 posts fill two pages,
        // 3 left over (next template needs 7)
        const {pages} = buildPages({posts: makePosts(17), page: 2, width: w, height: h});
        const end = pages[pages.length - 1];
        expect(end.type).toBe('end');
        expect(end.tiles.map(t => t.kind)).toEqual(['post', 'post', 'post', 'prev']);
    });

    test('no posts gives a single empty page', () => {
        const {pages} = buildPages({posts: [], page: 1, width: w, height: h});
        expect(pages).toEqual([{type: 'empty', cols: 1, rows: 1, tiles: [], nav: {hasPrev: false, hasNext: false}}]);
    });
});

describe('findPageForPost', () => {
    test('anchor post stays visible after rotating the tablet', () => {
        const posts = makePosts(100);
        const portrait = buildPages({posts, page: 1, width: 820, height: 1180}).pages;
        const anchor = firstPostOnPage(portrait[4]);
        const landscape = buildPages({posts, page: 1, width: 1180, height: 820}).pages;
        const target = findPageForPost(landscape, anchor);
        expect(landscape[target].tiles.some(t => t.postIndex === anchor)).toBe(true);
    });

    test('unknown post falls back to the first page', () => {
        const {pages} = buildPages({posts: makePosts(10), page: 1, width: 820, height: 1180});
        expect(findPageForPost(pages, 999)).toBe(0);
    });
});
