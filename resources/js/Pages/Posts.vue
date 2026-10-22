<template>
    <div class="md:flex h-full min-h-0">
        <FlipView v-if="postFormat === 'flip'" :posts="shownPosts" :has-more="hasMore" :page="page" :feeds="feeds" :tag="tag" :feed="feed" :search="search" :since="since" :starred="starred" :autoplay-seconds="autoplaySeconds" :saved-autoplay-seconds="autoplay"
                  :empty-text="starred ? 'No starred posts' : 'No posts'" />
        <div v-show="postFormat !== 'flip'" class="md:w-1/3 xl:w-1/4 md:flex md:justify-end md:border-r dark:border-black px-6 bg-gray-50 dark:bg-dark-surface md:px-12 md:overflow-auto py-3">
            <div class="md:hidden mb-2">
                <button @click="mobileShowOptions = !mobileShowOptions"
                        class="border w-full rounded-sm py-1 px-3 hover:bg-gray-100 dark:hover:bg-dark-raised border-2 text-gray-600 dark:text-gray-400 dark:border-dark-line gap-3 font-bold flex items-center">
                    <svg :class="{'rotate-90': mobileShowOptions}" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-caret-right-fill" viewBox="0 0 16 16">
                        <path d="m12.14 8.753-5.482 4.796c-.646.566-1.658.106-1.658-.753V3.204a1 1 0 0 1 1.659-.753l5.48 4.796a1 1 0 0 1 0 1.506z"/>
                    </svg>
                    Toggle filters & options
                </button>
            </div>
            <!-- In flip view the sidebar moves into the flip drawer. "to" changes with the view so Vue re-resolves the target, which only exists while FlipView is mounted. -->
            <Teleport defer :to="postFormat === 'flip' ? '#flip-drawer-body' : 'body'" :disabled="postFormat !== 'flip'">
            <div class="md:max-w-xs w-full hidden md:block" :class="{'block!': mobileShowOptions || postFormat === 'flip'}">

                <div v-if="postFormat === 'flip'" class="mb-8 flex">
                    <FormatSelector :format="postFormat" @change="updateFormat($event)" />
                </div>

                <div v-if="search || since || tag || feed || starred || page !== 1" class="mb-8">
                    <Link href="./" data-flip-keep-drawer class="font-bold text-black dark:text-gray-400">&laquo; Back</Link>
                </div>

                <!-- The starred view lists all starred posts, so search and the time filter don't apply to it -->
                <div v-if="!starred" class="mb-8">
                    <h2 class="font-bold mb-2 dark:text-gray-400">Search</h2>
                    <form @submit.prevent="submitSearch">
                        <input type="text"
                               v-model="searchInputVal"
                               placeholder="Post name or description"
                               class="border border-gray-300 dark:border-black rounded-sm px-2 py-1 w-full bg-white dark:bg-dark-page">
                        <button type="submit" class="hidden"></button>
                    </form>
                    <div class="mt-3 flex border rounded-sm border-gray-300 dark:border-dark-line divide-x divide-gray-300 dark:divide-dark-line text-sm text-gray-600 dark:text-gray-400">
                        <Link v-for="option in sinceOptions"
                              :href="pagePath"
                              :data="listingData({query: search, since: option.value})"
                              data-flip-keep-drawer
                              class="flex-1 text-center px-2 py-1 hover:text-black dark:hover:text-gray-200"
                              :class="{'bg-gray-200 text-gray-900 font-bold dark:bg-dark-raised dark:text-gray-200': option.value === since}"
                              :aria-current="option.value === since ? 'true' : null">{{ option.label }}</Link>
                    </div>
                </div>

                <div class="mb-8">
                    <Link href="starred" data-flip-keep-drawer
                          class="inline-flex items-center gap-1.5 font-bold text-gray-600 dark:text-gray-500 hover:text-black dark:hover:text-gray-300"
                          :class="{'text-black! dark:text-gray-300!': starred}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16" class="text-amber-400">
                            <path d="M3.612 15.443c-.386.198-.824-.149-.746-.592l.83-4.73L.173 6.765c-.329-.314-.158-.888.283-.95l4.898-.696L7.538.792c.197-.39.73-.39.927 0l2.184 4.327 4.898.696c.441.062.612.636.282.95l-3.522 3.356.83 4.73c.078.443-.36.79-.746.592L8 13.187l-4.389 2.256z"/>
                        </svg>
                        Starred - {{ starredCount }}
                    </Link>
                    <!-- "from" lets the share view's back link return to the starred view -->
                    <Link :href="starred ? 'share?from=starred' : 'share'"
                          class="mt-2 flex w-fit items-center gap-1.5 font-bold text-gray-600 dark:text-gray-500 hover:text-black dark:hover:text-gray-300">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                            <path fill-rule="evenodd" d="M3.5 6a.5.5 0 0 0-.5.5v8a.5.5 0 0 0 .5.5h9a.5.5 0 0 0 .5-.5v-8a.5.5 0 0 0-.5-.5h-2a.5.5 0 0 1 0-1h2A1.5 1.5 0 0 1 14 6.5v8a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 2 14.5v-8A1.5 1.5 0 0 1 3.5 5h2a.5.5 0 0 1 0 1z"/>
                            <path fill-rule="evenodd" d="M7.646.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1-.708.708L8.5 1.707V10.5a.5.5 0 0 1-1 0V1.707L5.354 3.854a.5.5 0 1 1-.708-.708z"/>
                        </svg>
                        Share List - {{ sharedCount }}
                    </Link>
                    <Link href="settings"
                          class="mt-2 flex w-fit items-center gap-1.5 font-bold text-gray-600 dark:text-gray-500 hover:text-black dark:hover:text-gray-300">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                            <path d="M9.405 1.05c-.413-1.4-2.397-1.4-2.81 0l-.1.34a1.464 1.464 0 0 1-2.105.872l-.31-.17c-1.283-.698-2.686.705-1.987 1.987l.169.311c.446.82.023 1.841-.872 2.105l-.34.1c-1.4.413-1.4 2.397 0 2.81l.34.1a1.464 1.464 0 0 1 .872 2.105l-.17.31c-.698 1.283.705 2.686 1.987 1.987l.311-.169a1.464 1.464 0 0 1 2.105.872l.1.34c.413 1.4 2.397 1.4 2.81 0l.1-.34a1.464 1.464 0 0 1 2.105-.872l.31.17c1.283.698 2.686-.705 1.987-1.987l-.169-.311a1.464 1.464 0 0 1 .872-2.105l.34-.1c1.4-.413 1.4-2.397 0-2.81l-.34-.1a1.464 1.464 0 0 1-.872-2.105l.17-.31c.698-1.283-.705-2.686-1.987-1.987l-.311.169a1.464 1.464 0 0 1-2.105-.872l-.1-.34zM8 10.93a2.929 2.929 0 1 1 0-5.86 2.929 2.929 0 0 1 0 5.858z"/>
                        </svg>
                        Settings
                    </Link>
                </div>

                <div v-if="tags.length > 0" class="mb-8">
                    <h2 class="font-bold mb-2 dark:text-gray-400">Tags</h2>
                    <template v-for="tag in tags">
                        <Tag :tag="tag" class="mr-3 font-bold text-gray-600 dark:text-gray-500"/>
                    </template>
                </div>

                <div class="mb-8">
                    <h2 class="font-bold dark:text-gray-400">Feeds</h2>
                    <template v-for="feed in feeds">
                        <Feed :feed="feed"/>
                        <hr class="last:hidden dark:border-dark-raised">
                    </template>
                </div>

            </div>
            </Teleport>
        </div>
        <div v-if="postFormat !== 'flip'" class="md:w-2/3 xl:w-3/4 px-6 md:px-12 md:overflow-auto md:h-full py-2">
            <div class="flex items-center justify-between mb-2">
                <h2 class="font-bold text-black dark:text-gray-400">
                    <Link href="./">Posts</Link>
                    <span v-if="tag"> / #{{ tag }}</span>
                    <span v-if="feed && feeds.length === 1"> / <span :style="{ color: feeds[0].color}">{{ feeds[0].name }}</span></span>
                    <span v-if="starred"> / Starred</span>
                    <span v-if="search"> ?= {{ search }}</span>
                    <span v-if="since"> · {{ sinceLabel(since) }}</span>
                </h2>

                <div>
                    <FormatSelector :format="postFormat" @change="updateFormat($event)" />
                </div>
            </div>

            <div :class="{'max-w-lg': postFormat === 'card', 'max-w-2xl': postFormat === 'list', 'w-full': postFormat === 'compact' || postFormat === 'grid'}">

                <div class="-mx-4" :class="{ 'flex flex-wrap': postFormat === 'grid' }">
                    <p v-if="starred && shownPosts.length === 0" class="px-4 py-6 text-gray-600 dark:text-gray-400">No starred posts yet. Use the star on a post to keep it here.</p>
                    <template v-for="post in shownPosts" :key="post.url">
                        <Post :post="post" :format="postFormat" :class="{'flex-1 min-w-sm w-md max-w-lg': postFormat === 'grid'}"/>
                        <div v-if="postFormat !== 'grid'" class="px-4">
                            <hr class="dark:border-dark-raised">
                        </div>
                    </template>
                </div>

                <div class="py-2 flex">
                    <Link v-if="page > 1" :href="pagePath" :data="pageData(page - 1)" class="cursor-pointer text-gray-600 dark:text-gray-500 dark:hover:text-gray-300 font-bold py-2 hover:text-black">&laquo; Previous Page</Link>
                    <Link v-if="hasMore" :href="pagePath" :data="pageData(page + 1)" class="ml-auto cursor-pointer text-gray-600 dark:text-gray-500 dark:hover:text-gray-300 font-bold py-2 hover:text-black">Next Page &raquo;</Link>
                </div>

            </div>
        </div>
    </div>

</template>
<script>
    import Post from "../Parts/Post.vue";
    import Feed from "../Parts/Feed.vue";
    import Tag from "../Parts/Tag.vue";
    import FormatSelector from "../Parts/FormatSelector.vue";
    import FlipView from "../Parts/FlipView.vue";
    import {SINCE_OPTIONS, listingData, sinceLabel} from "../listing";
    import {starred as starredStore} from "../starred";
    import {shared as sharedStore} from "../shared";
    import {parseAutoplayParam} from "../flip/autoplay";

    export default {
        components: {FlipView, FormatSelector, Tag, Feed, Post},
        props: {
            page: Number,
            feeds: Array,
            posts: Array,
            hasMore: Boolean,
            tag: {
                type: String,
                default: null,
            },
            feed: {
                type: String,
                default: null,
            },
            search: {
                type: String,
                default: null,
            },
            since: {
                type: String,
                default: '',
            },
            starred: {
                type: Boolean,
                default: false,
            },
            starredUrls: {
                type: Array,
                default: () => [],
            },
            sharedUrls: {
                type: Array,
                default: () => [],
            },
            // Auto-advance interval saved in the settings, or null when it is off
            autoplay: {
                type: Number,
                default: null,
            },
        },
        computed: {
            tags() {
                const tags = {};

                for (let i = 0; i < this.feeds.length; i++) {
                    const feed = this.feeds[i];
                    for (const tag of feed.tags) {
                        tags[tag] = true;
                    }
                }

                return Object.keys(tags).sort();
            },
            pagePath() {
                return window.location.pathname;
            },
            shownPosts() {
                // Hide posts unstarred on this page without waiting for a reload
                return this.starred ? this.posts.filter(post => starredStore.isStarred(post.url)) : this.posts;
            },
            starredCount() {
                return starredStore.count();
            },
            sharedCount() {
                return sharedStore.count();
            }
        },
        data() {
            return {
                searchInputVal: this.search,
                autoplaySeconds: parseAutoplayParam(window.location.search),
                sinceOptions: SINCE_OPTIONS,
                postFormat: 'card',
                mobileShowOptions: false,
            }
        },
        watch: {
            starredUrls(urls) {
                starredStore.load(urls);
            },
            sharedUrls(urls) {
                sharedStore.load(urls);
            },
        },
        created() {
            starredStore.load(this.starredUrls);
            sharedStore.load(this.sharedUrls);
        },
        methods: {
            listingData,
            sinceLabel,
            pageData(page) {
                return listingData({page, query: this.search, since: this.since});
            },
            submitSearch() {
                this.$inertia.visit(this.pagePath, {
                    data: listingData({query: this.searchInputVal, since: this.since})
                });
            },
            updateFormat(postFormat) {
                this.postFormat = postFormat;
                window.localStorage.setItem('post-format', postFormat);
            }
        },
        mounted() {
            const format = window.localStorage.getItem('post-format');
            if (format) {
                this.postFormat = format;
            }
            // A kiosk URL with ?autoplay shows the flip view without changing the saved layout
            if (this.autoplaySeconds !== null) {
                this.postFormat = 'flip';
            }
        }
    }
</script>
