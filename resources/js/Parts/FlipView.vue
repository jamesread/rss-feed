<template>
    <div class="fixed inset-0 z-30 bg-gray-100 dark:bg-gray-950 text-gray-900 dark:text-gray-200">
        <!-- Measures the area inside the device safe areas -->
        <div ref="probe" class="absolute inset-0 pointer-events-none invisible" :style="safeAreaPadding"></div>

        <div ref="pager"
             @touchstart.passive="onInteraction"
             @pointerdown="onInteraction"
             class="flip-pager h-full flex overflow-x-auto overflow-y-hidden snap-x snap-mandatory overscroll-contain touch-pan-x touch-pinch-zoom"
             @scroll.passive="onScroll"
             @wheel="onWheel">
            <section v-for="(p, i) in pages" :key="i"
                     class="shrink-0 w-full h-full snap-start snap-always"
                     :style="safeAreaPadding"
                     :aria-label="`Page ${i + 1} of ${pages.length}`">
                <div v-if="p.type === 'empty'" class="h-full flex flex-col items-center justify-center gap-3 text-gray-600 dark:text-gray-400">
                    <p class="text-xl font-bold">{{ emptyText }}</p>
                    <Link v-if="p.nav.hasPrev" :href="pagePath" :data="pageData(page - 1)" class="font-bold hover:text-black dark:hover:text-gray-200">&laquo; Previous page</Link>
                </div>
                <div v-else class="grid h-full gap-0.5"
                     :style="{gridTemplateColumns: `repeat(${p.cols}, minmax(0, 1fr))`, gridTemplateRows: `repeat(${p.rows}, minmax(0, 1fr))`}">
                    <template v-for="tile in p.tiles" :key="tile.kind + (tile.postIndex ?? '')">
                        <FlipTile v-if="tile.kind === 'post'" :post="posts[tile.postIndex]" :size="tile.size" :style="gridArea(tile)" />
                        <Link v-else
                              :href="pagePath"
                              :data="pageData(tile.kind === 'next' ? page + 1 : page - 1)"
                              :style="gridArea(tile)"
                              class="flex items-center justify-center text-2xl font-bold bg-gray-200 hover:bg-gray-300 text-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700 dark:text-gray-300">
                            <template v-if="tile.kind === 'next'">Next page &raquo;</template>
                            <template v-else>&laquo; Previous page</template>
                        </Link>
                    </template>
                </div>
            </section>
        </div>

        <!-- Menu button with current filter -->
        <button type="button"
                class="absolute z-20 flex items-center gap-2 max-w-[70vw] rounded-full px-3 py-1.5 text-sm font-bold shadow
                       bg-white/85 text-gray-800 hover:bg-white dark:bg-gray-900/85 dark:text-gray-300 dark:hover:bg-gray-900 backdrop-blur"
                :style="{top: 'calc(env(safe-area-inset-top) + 0.5rem)', left: 'calc(env(safe-area-inset-left) + 0.5rem)'}"
                aria-label="Open filters and options"
                @click="openDrawer">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="shrink-0" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M2.5 12a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5z"/>
            </svg>
            <span class="truncate">
                Posts<span v-if="starred"> / Starred</span><span v-if="tag"> / #{{ tag }}</span><span v-if="feed && feeds.length === 1"> / <span :style="{color: feeds[0].color}">{{ feeds[0].name }}</span></span><span v-if="search"> ?= {{ search }}</span><span v-if="since"> · {{ sinceLabel(since) }}</span>
            </span>
        </button>

        <!-- Previous / next buttons for mouse users -->
        <button v-if="current > 0" type="button" aria-label="Previous page"
                class="flip-edge-button hidden absolute z-20 top-1/2 -translate-y-1/2 size-11 items-center justify-center rounded-full shadow
                       bg-white/70 text-gray-800 hover:bg-white dark:bg-gray-900/70 dark:text-gray-300 dark:hover:bg-gray-900 backdrop-blur"
                :style="{left: 'calc(env(safe-area-inset-left) + 0.5rem)'}"
                @click="goTo(current - 1)">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/></svg>
        </button>
        <button v-if="current < pages.length - 1" type="button" aria-label="Next page"
                class="flip-edge-button hidden absolute z-20 top-1/2 -translate-y-1/2 size-11 items-center justify-center rounded-full shadow
                       bg-white/70 text-gray-800 hover:bg-white dark:bg-gray-900/70 dark:text-gray-300 dark:hover:bg-gray-900 backdrop-blur"
                :style="{right: 'calc(env(safe-area-inset-right) + 0.5rem)'}"
                @click="goTo(current + 1)">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/></svg>
        </button>

        <!-- Page indicator -->
        <div v-if="pages.length > 1"
             class="absolute z-20 left-1/2 -translate-x-1/2 rounded-full px-2.5 py-0.5 text-xs font-bold tabular-nums pointer-events-none
                    bg-white/80 text-gray-700 dark:bg-gray-900/80 dark:text-gray-400 backdrop-blur"
             :style="{bottom: 'calc(env(safe-area-inset-bottom) + 0.5rem)'}">
            {{ current + 1 }} / {{ pages.length }}
        </div>

        <!-- Auto-advance progress -->
        <div v-if="autoplayEnabled"
             class="absolute z-20 inset-x-0 h-0.5 pointer-events-none"
             :style="{bottom: 'env(safe-area-inset-bottom)'}">
            <div class="h-full bg-gray-900/40 dark:bg-white/40" :style="{width: `${autoplayProgress * 100}%`}"></div>
        </div>

        <!-- Drawer; the posts page sidebar is teleported into #flip-drawer-body -->
        <div v-show="drawerOpen" class="absolute inset-0 z-40">
            <div class="absolute inset-0 bg-black/40" @click="closeDrawer"></div>
            <aside class="absolute inset-y-0 left-0 w-[min(22rem,85vw)] overflow-y-auto overscroll-contain shadow-xl bg-gray-50 dark:bg-gray-800"
                   role="dialog" aria-modal="true" aria-label="Filters and options"
                   :style="{paddingTop: 'env(safe-area-inset-top)', paddingBottom: 'env(safe-area-inset-bottom)', paddingLeft: 'env(safe-area-inset-left)'}">
                <div class="flex justify-end px-3 pt-3">
                    <button ref="closeButton" type="button" aria-label="Close"
                            class="rounded-sm p-1 text-gray-600 hover:bg-gray-200 dark:text-gray-400 dark:hover:bg-gray-700"
                            @click="closeDrawer">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16"><path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/></svg>
                    </button>
                </div>
                <div class="px-6 mb-8">
                    <label class="flex items-center justify-between gap-3 font-bold text-gray-900 dark:text-gray-400 cursor-pointer">
                        <span>Auto-advance <span class="font-normal text-sm text-gray-600 dark:text-gray-500">every {{ autoplaySecondsOrDefault }} s</span></span>
                        <input type="checkbox" class="peer sr-only" :checked="autoplayEnabled" @change="setAutoplay($event.target.checked)">
                        <span class="relative inline-block w-9 h-5 shrink-0 rounded-full bg-gray-300 dark:bg-gray-600 transition peer-checked:bg-gray-800 dark:peer-checked:bg-gray-300 peer-focus-visible:ring-2 peer-focus-visible:ring-gray-500
                                     after:absolute after:top-0.5 after:left-0.5 after:size-4 after:rounded-full after:bg-white dark:after:bg-gray-900 after:transition peer-checked:after:translate-x-4"
                              aria-hidden="true"></span>
                    </label>
                </div>
                <div id="flip-drawer-body" class="px-6 pb-6" @click="onDrawerClick"></div>
            </aside>
        </div>
    </div>
</template>
<script>
import FlipTile from "./FlipTile.vue";
import {buildPages, findPageForPost, firstPostOnPage} from "../flip/layout";
import {listingData, sinceLabel} from "../listing";
import {router} from "@inertiajs/vue3";
import {DEFAULT_INTERVAL_SECONDS, createAutoplayTimer} from "../flip/autoplay";

const AUTOPLAY_TICK_MS = 250;
const AUTOPLAY_STORAGE_KEY = 'flip-autoplay';

const WHEEL_THRESHOLD = 50;
const WHEEL_IDLE_MS = 200;

// Links marked with data-flip-keep-drawer reopen the drawer after the page visit
const KEEP_DRAWER_KEY = 'flip-keep-drawer-open';
const KEEP_DRAWER_MAX_AGE_MS = 10000;

function readStoredAutoplay() {
    try {
        return window.localStorage.getItem(AUTOPLAY_STORAGE_KEY) === '1';
    } catch (e) {
        return false;
    }
}

export default {
    components: {FlipTile},
    props: {
        posts: Array,
        hasMore: Boolean,
        page: Number,
        feeds: Array,
        tag: String,
        feed: String,
        search: String,
        since: {
            type: String,
            default: '',
        },
        starred: {
            type: Boolean,
            default: false,
        },
        emptyText: {
            type: String,
            default: 'No posts',
        },
        // Interval from the `autoplay` URL parameter, or null without one
        autoplaySeconds: {
            type: Number,
            default: null,
        },
    },
    data() {
        return {
            width: 0,
            height: 0,
            current: 0,
            drawerOpen: false,
            autoplayStored: readStoredAutoplay(),
            autoplayOffForView: false,
            autoplayProgress: 0,
            autoplayReloading: false,
            safeAreaPadding: {
                paddingTop: 'env(safe-area-inset-top)',
                paddingRight: 'env(safe-area-inset-right)',
                paddingBottom: 'env(safe-area-inset-bottom)',
                paddingLeft: 'env(safe-area-inset-left)',
            },
        }
    },
    computed: {
        pages() {
            if (!this.width || !this.height) {
                return [];
            }
            return buildPages({posts: this.posts, page: this.page, width: this.width, height: this.height, hasMore: this.hasMore}).pages;
        },
        pagePath() {
            return window.location.pathname;
        },
        autoplaySecondsOrDefault() {
            return this.autoplaySeconds || DEFAULT_INTERVAL_SECONDS;
        },
        autoplayEnabled() {
            return !this.autoplayOffForView && (this.autoplaySeconds !== null || this.autoplayStored);
        },
    },
    watch: {
        autoplayEnabled(enabled) {
            this.autoplayProgress = 0;
            if (enabled) {
                this.startAutoplay();
            } else {
                this.stopAutoplay();
            }
        },
    },
    methods: {
        sinceLabel,
        pageData(page) {
            return listingData({page, query: this.search, since: this.since});
        },
        gridArea(tile) {
            return {
                gridColumn: `${tile.col} / span ${tile.colSpan}`,
                gridRow: `${tile.row} / span ${tile.rowSpan}`,
            };
        },
        pageWidth() {
            return this.$refs.pager ? this.$refs.pager.clientWidth : 0;
        },
        goTo(index, instant = false) {
            const target = Math.max(0, Math.min(index, this.pages.length - 1));
            const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            this.$refs.pager.scrollTo({
                left: target * this.pageWidth(),
                behavior: instant || reduceMotion ? 'auto' : 'smooth',
            });
            this.current = target;
        },
        onScroll() {
            if (this.scrollFrame) {
                return;
            }
            this.scrollFrame = requestAnimationFrame(() => {
                this.scrollFrame = null;
                const width = this.pageWidth();
                if (width) {
                    this.current = Math.round(this.$refs.pager.scrollLeft / width);
                }
            });
        },
        onWheel(event) {
            this.onInteraction();
            // Horizontal trackpad gestures are left to native scroll-snap
            if (this.drawerOpen || Math.abs(event.deltaY) <= Math.abs(event.deltaX)) {
                return;
            }
            event.preventDefault();

            if (!this.wheelLocked) {
                const scale = event.deltaMode === 1 ? 16 : (event.deltaMode === 2 ? this.height : 1);
                this.wheelDelta = (this.wheelDelta || 0) + event.deltaY * scale;
                if (Math.abs(this.wheelDelta) >= WHEEL_THRESHOLD) {
                    this.goTo(this.current + Math.sign(this.wheelDelta));
                    this.wheelLocked = true;
                    this.wheelDelta = 0;
                }
            }

            clearTimeout(this.wheelTimer);
            this.wheelTimer = setTimeout(() => {
                this.wheelLocked = false;
                this.wheelDelta = 0;
            }, WHEEL_IDLE_MS);
        },
        onKeydown(event) {
            this.onInteraction();
            if (this.drawerOpen) {
                if (event.key === 'Escape') {
                    this.closeDrawer();
                }
                return;
            }

            const target = event.target;
            if (event.metaKey || event.ctrlKey || event.altKey || target.isContentEditable
                || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName)) {
                return;
            }

            const last = this.pages.length - 1;
            const actions = {
                ArrowRight: () => this.current + 1,
                PageDown: () => this.current + 1,
                ArrowLeft: () => this.current - 1,
                PageUp: () => this.current - 1,
                ' ': () => this.current + (event.shiftKey ? -1 : 1),
                Home: () => 0,
                End: () => last,
            };
            if (actions[event.key]) {
                event.preventDefault();
                this.goTo(actions[event.key]());
            }
        },
        onResize(entry) {
            const {width, height} = entry.contentRect;
            if (width === this.width && height === this.height) {
                return;
            }

            const anchor = this.pages.length ? firstPostOnPage(this.pages[this.current]) : null;
            this.width = width;
            this.height = height;

            this.$nextTick(() => {
                const index = anchor === null ? this.current : findPageForPost(this.pages, anchor);
                this.goTo(index, true);
            });
        },
        startAutoplay() {
            this.stopAutoplay();
            this.autoplayTimer = createAutoplayTimer(this.autoplaySecondsOrDefault * 1000, Date.now());
            this.autoplayInterval = setInterval(this.onAutoplayTick, AUTOPLAY_TICK_MS);
        },
        stopAutoplay() {
            clearInterval(this.autoplayInterval);
            this.autoplayInterval = null;
        },
        onAutoplayTick() {
            const paused = this.drawerOpen || document.hidden || this.autoplayReloading;
            const {advance, progress} = this.autoplayTimer.tick(Date.now(), paused);
            this.autoplayProgress = progress;
            if (!advance) {
                return;
            }

            if (this.current < this.pages.length - 1) {
                this.goTo(this.current + 1);
            } else {
                this.reloadAndRestart();
            }
        },
        reloadAndRestart() {
            // Fetch fresh posts when looping, so a long-running display stays current
            this.autoplayReloading = true;
            router.reload({
                only: ['posts', 'feeds', 'hasMore'],
                // On failure keep showing the current posts and retry on the next loop,
                // without Inertia's error handling taking over the display
                onNetworkError: () => false,
                onHttpException: () => false,
                onFinish: () => {
                    this.autoplayReloading = false;
                    this.$nextTick(() => this.goTo(0, true));
                },
            });
        },
        onInteraction() {
            if (this.autoplayTimer) {
                this.autoplayTimer.pauseForInteraction(Date.now());
            }
        },
        setAutoplay(enabled) {
            this.autoplayOffForView = !enabled;
            this.autoplayStored = enabled;
            try {
                window.localStorage.setItem(AUTOPLAY_STORAGE_KEY, enabled ? '1' : '0');
            } catch (e) {
                // Storage unavailable: the choice applies to this page view only
            }
        },
        openDrawer() {
            this.drawerOpen = true;
            this.$nextTick(() => this.$refs.closeButton.focus());
        },
        closeDrawer() {
            this.drawerOpen = false;
        },
        onDrawerClick(event) {
            if (event.target.closest('[data-flip-keep-drawer]')) {
                try {
                    window.sessionStorage.setItem(KEEP_DRAWER_KEY, String(Date.now()));
                } catch (e) {
                    // Storage unavailable: the drawer just closes after the visit
                }
            }
        },
        restoreDrawer() {
            try {
                const since = Number(window.sessionStorage.getItem(KEEP_DRAWER_KEY));
                window.sessionStorage.removeItem(KEEP_DRAWER_KEY);
                if (since && Date.now() - since < KEEP_DRAWER_MAX_AGE_MS) {
                    this.openDrawer();
                }
            } catch (e) {
                // Storage unavailable: start with the drawer closed
            }
        },
    },
    mounted() {
        // Stop the document behind the full-screen view from scrolling or bouncing
        document.documentElement.classList.add('flip-scroll-lock');
        this.restoreDrawer();

        // Measure once up front so the first render already has pages
        const probe = this.$refs.probe;
        const style = getComputedStyle(probe);
        this.width = probe.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);
        this.height = probe.clientHeight - parseFloat(style.paddingTop) - parseFloat(style.paddingBottom);

        this.resizeObserver = new ResizeObserver(entries => this.onResize(entries[0]));
        this.resizeObserver.observe(this.$refs.probe);
        this.keydownHandler = this.onKeydown.bind(this);
        window.addEventListener('keydown', this.keydownHandler);

        if (this.autoplayEnabled) {
            this.startAutoplay();
        }
    },
    unmounted() {
        document.documentElement.classList.remove('flip-scroll-lock');
        this.resizeObserver.disconnect();
        window.removeEventListener('keydown', this.keydownHandler);
        clearTimeout(this.wheelTimer);
        this.stopAutoplay();
        if (this.scrollFrame) {
            cancelAnimationFrame(this.scrollFrame);
        }
    },
}
</script>
<style>
html.flip-scroll-lock,
html.flip-scroll-lock body {
    overflow: hidden;
    overscroll-behavior: none;
}

.flip-pager {
    scrollbar-width: none;
}

.flip-pager::-webkit-scrollbar {
    display: none;
}

@media (hover: hover) and (pointer: fine) {
    .flip-edge-button {
        display: flex;
    }
}
</style>
