export const DEFAULT_INTERVAL_SECONDS = 30;
export const MIN_INTERVAL_SECONDS = 5;
export const MAX_INTERVAL_SECONDS = 3600;
export const INTERACTION_PAUSE_MS = 60000;

/**
 * Read the auto-advance interval from a URL query string.
 * Returns null without an `autoplay` parameter, the default for an empty or
 * invalid value, and the value in seconds when it is a whole number in range.
 * @param {String} search
 */
export function parseAutoplayParam(search) {
    const params = new URLSearchParams(search);
    if (!params.has('autoplay')) {
        return null;
    }

    const value = params.get('autoplay');
    if (!/^\d+$/.test(value)) {
        return DEFAULT_INTERVAL_SECONDS;
    }

    const seconds = Number(value);
    return seconds >= MIN_INTERVAL_SECONDS && seconds <= MAX_INTERVAL_SECONDS ? seconds : DEFAULT_INTERVAL_SECONDS;
}

/**
 * Timing state for auto-advance. Call tick() regularly; it reports when to turn
 * the page and how far the current interval has progressed (0 to 1).
 * While paused the interval keeps restarting, so it runs in full once the pause ends.
 * @param {Number} intervalMs
 * @param {Number} now
 */
export function createAutoplayTimer(intervalMs, now) {
    let startedAt = now;
    let pausedUntil = 0;

    return {
        /** Pause after user input, until INTERACTION_PAUSE_MS after the latest input. */
        pauseForInteraction(at) {
            pausedUntil = at + INTERACTION_PAUSE_MS;
            startedAt = at;
        },
        /** Start a new interval, e.g. after the page changed. */
        restart(at) {
            startedAt = at;
        },
        /**
         * @param {Number} at
         * @param {Boolean} externallyPaused e.g. drawer open or tab hidden
         * @returns {{advance: Boolean, progress: Number}}
         */
        tick(at, externallyPaused = false) {
            if (externallyPaused || at < pausedUntil) {
                // The next full interval starts when the pause ends
                startedAt = Math.max(at, pausedUntil);
                return {advance: false, progress: 0};
            }

            if (at - startedAt >= intervalMs) {
                startedAt = at;
                return {advance: true, progress: 0};
            }

            return {advance: false, progress: (at - startedAt) / intervalMs};
        },
    };
}
