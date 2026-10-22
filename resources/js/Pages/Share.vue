<template>
    <div class="md:flex h-full min-h-0">
        <div class="md:w-1/3 xl:w-1/4 md:flex md:justify-end md:border-r dark:border-black px-6 bg-gray-50 dark:bg-dark-surface md:px-12 md:overflow-auto py-3">
            <div class="md:max-w-xs w-full">
                <div class="mb-6 md:mb-8">
                    <Link :href="backHref" class="font-bold text-black dark:text-gray-400">&laquo; Back</Link>
                </div>

                <div class="mb-3 md:mb-8">
                    <h2 class="font-bold mb-2 dark:text-gray-400">Share List</h2>
                    <p class="text-sm text-gray-600 dark:text-gray-500">Each post gets a LinkedIn text written by the AI provider set in <Link href="settings/ai" class="underline hover:text-black dark:hover:text-gray-300">Settings → Social Media</Link>.<br>Adjust it, then share it.</p>
                </div>
            </div>
        </div>

        <div class="md:w-2/3 xl:w-3/4 px-6 md:px-12 md:overflow-auto md:h-full py-2">
            <h2 class="font-bold text-black dark:text-gray-400 mb-2">Share List</h2>

            <p v-if="posts.length === 0" class="py-6 text-gray-600 dark:text-gray-400">No posts to share yet. Use the share button next to the star of a starred post to add it here.</p>

            <div v-for="post in posts" :key="post.url"
                 class="grid gap-3 lg:grid-cols-2 lg:gap-6 py-4 border-b last:border-0 border-gray-200 dark:border-dark-raised">
                <div class="-mx-4">
                    <Post :post="post" format="list" :actions="false" />
                </div>

                <div class="min-w-0">
                    <label :for="textId(post)" class="sr-only">LinkedIn text for {{ post.title }}</label>

                    <div v-if="post.status === 'failed'"
                         class="rounded-sm border border-red-300 dark:border-red-900 bg-red-50 dark:bg-dark-surface px-3 py-2 text-sm text-red-700 dark:text-red-400">
                        <p>{{ post.error || 'The text could not be generated.' }}</p>
                        <button type="button" class="mt-2 font-bold underline hover:no-underline disabled:opacity-50"
                                :disabled="entry(post).busy" @click="retry(post)">Retry</button>
                    </div>
                    <textarea v-else :id="textId(post)" rows="9"
                              :value="post.status === 'ready' ? entry(post).text : ''"
                              :disabled="post.status !== 'ready'"
                              :placeholder="post.status === 'ready' ? '' : 'Generating…'"
                              class="w-full border border-gray-300 dark:border-dark-line rounded-sm px-2 py-1 bg-white dark:bg-dark-page text-sm disabled:opacity-60"
                              @input="edit(post, $event.target.value)"
                              @blur="saveNow(post)"></textarea>

                    <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-2">
                        <button type="button" :disabled="post.status !== 'ready' || !entry(post).text.trim()"
                                class="rounded-sm border-2 px-3 py-1 font-bold text-gray-700 border-gray-300 hover:bg-gray-100 dark:text-gray-300 dark:border-dark-line dark:hover:bg-dark-raised disabled:opacity-50"
                                @click="shareOnLinkedin(post)">Share on LinkedIn</button>
                        <button type="button" :disabled="entry(post).busy"
                                class="font-bold text-gray-600 dark:text-gray-500 hover:text-black dark:hover:text-gray-300 disabled:opacity-50"
                                @click="remove(post)">Remove</button>
                        <p class="text-sm" :class="entry(post).error ? 'text-red-600 dark:text-red-400' : 'text-gray-600 dark:text-gray-500'" role="status">{{ entry(post).status }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
<script>
    import {reactive} from "vue";
    import {router} from "@inertiajs/vue3";
    import Post from "../Parts/Post.vue";
    import {requestJson} from "../http";
    import {linkedinShareUrl} from "../linkedin";

    const SAVE_DELAY_MS = 800;
    const POLL_INTERVAL_MS = 3000;

    export default {
        components: {Post},
        props: {
            posts: Array,
        },
        data() {
            return {
                // Local state per post URL: the text being edited and its save status
                entries: reactive({}),
                pollTimer: null,
            };
        },
        computed: {
            // Back to the view the share list was opened from: the starred view or the home page
            backHref() {
                return new URLSearchParams(window.location.search).get('from') === 'starred' ? 'starred' : './';
            },
            hasPending() {
                return this.posts.some(post => post.status === 'pending');
            },
        },
        watch: {
            posts: {
                handler(posts) {
                    for (const post of posts) {
                        const entry = this.entries[post.url];
                        // Keep a text that is being edited; fill in texts that were generated meanwhile
                        if (!entry || !entry.ready) {
                            this.entries[post.url] = {
                                text: post.text || '',
                                ready: post.status === 'ready',
                                status: entry?.status || '',
                                error: entry?.error || false,
                                busy: false,
                                timer: null,
                            };
                        }
                    }
                },
                immediate: true,
            },
            hasPending: {
                handler(pending) {
                    clearInterval(this.pollTimer);
                    this.pollTimer = pending ? setInterval(() => router.reload({only: ['posts']}), POLL_INTERVAL_MS) : null;
                },
                immediate: true,
            },
        },
        beforeUnmount() {
            clearInterval(this.pollTimer);
            for (const entry of Object.values(this.entries)) {
                clearTimeout(entry.timer);
            }
        },
        methods: {
            entry(post) {
                return this.entries[post.url];
            },
            textId(post) {
                return 'share-text-' + this.posts.indexOf(post);
            },
            setStatus(entry, status, error = false) {
                entry.status = status;
                entry.error = error;
            },
            edit(post, text) {
                const entry = this.entry(post);
                entry.text = text;
                this.setStatus(entry, '');
                clearTimeout(entry.timer);
                entry.timer = setTimeout(() => this.saveNow(post), SAVE_DELAY_MS);
            },
            async saveNow(post) {
                const entry = this.entry(post);
                if (!entry.timer) {
                    return;
                }
                clearTimeout(entry.timer);
                entry.timer = null;

                if (!entry.text.trim()) {
                    this.setStatus(entry, 'The text cannot be empty.', true);
                    return;
                }
                this.setStatus(entry, 'Saving…');
                const result = await requestJson('PUT', 'share', {url: post.url, text: entry.text});
                if (result.ok) {
                    this.setStatus(entry, 'Saved.');
                } else {
                    this.setStatus(entry, result.data?.message || 'Saving failed. Please try again.', true);
                }
            },
            shareOnLinkedin(post) {
                const text = this.entry(post).text;
                this.saveNow(post);
                // Start copying before the new tab takes the focus, the clipboard needs a focused page
                navigator.clipboard?.writeText(text).catch(() => {});
                window.open(linkedinShareUrl(text), '_blank', 'noopener');
                this.setStatus(this.entry(post), 'Copied. Paste it in LinkedIn if the text is missing.');
            },
            async retry(post) {
                const entry = this.entry(post);
                entry.busy = true;
                const result = await requestJson('POST', 'share/retry', {url: post.url});
                entry.busy = false;
                if (result.ok) {
                    this.setStatus(entry, '');
                    router.reload({only: ['posts']});
                } else {
                    this.setStatus(entry, 'Retry failed. Please try again.', true);
                }
            },
            async remove(post) {
                const entry = this.entry(post);
                clearTimeout(entry.timer);
                entry.timer = null;
                entry.busy = true;
                const result = await requestJson('DELETE', 'share', {url: post.url});
                entry.busy = false;
                if (result.ok) {
                    router.reload({only: ['posts']});
                } else {
                    this.setStatus(entry, 'Removing failed. Please try again.', true);
                }
            },
        },
    }
</script>
