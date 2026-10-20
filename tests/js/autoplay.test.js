import {describe, expect, test} from 'vitest';
import {createAutoplayTimer, parseAutoplayParam} from '../../resources/js/flip/autoplay.js';

describe('parseAutoplayParam', () => {
    test.each([
        ['', null],
        ['?page=2', null],
        ['?autoplay', 30],
        ['?autoplay=', 30],
        ['?autoplay=45', 45],
        ['?autoplay=5', 5],
        ['?autoplay=3600', 3600],
        ['?autoplay=2', 30],
        ['?autoplay=4000', 30],
        ['?autoplay=abc', 30],
        ['?autoplay=12.5', 30],
        ['?since=24h&autoplay=60', 60],
    ])('%s -> %s', (search, expected) => {
        expect(parseAutoplayParam(search)).toBe(expected);
    });
});

describe('createAutoplayTimer', () => {
    test('advances after each interval and reports progress', () => {
        const timer = createAutoplayTimer(30000, 0);
        expect(timer.tick(15000)).toEqual({advance: false, progress: 0.5});
        expect(timer.tick(30000)).toEqual({advance: true, progress: 0});
        expect(timer.tick(45000).progress).toBe(0.5);
        expect(timer.tick(60000).advance).toBe(true);
    });

    test('does not advance while externally paused and restarts the interval after', () => {
        const timer = createAutoplayTimer(30000, 0);
        expect(timer.tick(40000, true)).toEqual({advance: false, progress: 0});
        expect(timer.tick(50000).advance).toBe(false);
        expect(timer.tick(70000).advance).toBe(true);
    });

    test('pauses for 60 s after interaction, then runs a full interval', () => {
        const timer = createAutoplayTimer(30000, 0);
        timer.pauseForInteraction(10000);
        expect(timer.tick(40000).advance).toBe(false);
        expect(timer.tick(69999).progress).toBe(0);
        // Pause ends at 70 s; the next full interval ends at 100 s
        expect(timer.tick(70000).advance).toBe(false);
        expect(timer.tick(99999).advance).toBe(false);
        expect(timer.tick(100000).advance).toBe(true);
    });

    test('restart resets progress', () => {
        const timer = createAutoplayTimer(30000, 0);
        timer.restart(20000);
        expect(timer.tick(35000).progress).toBe(0.5);
    });
});
