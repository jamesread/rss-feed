<template>
    <div class="md:flex h-full min-h-0">
        <FlipView v-if="postFormat === 'flip'" :posts="posts" :page="page" :feeds="feeds" :tag="tag" :feed="feed" :search="search" />
        <div v-show="postFormat !== 'flip'" class="md:w-1/3 xl:w-1/4 md:flex md:justify-end md:border-r dark:border-black px-6 bg-gray-50 dark:bg-gray-800 md:px-12 md:overflow-auto py-3">
            <div class="md:hidden mb-2">
                <button @click="mobileShowOptions = !mobileShowOptions"
                        class="border w-full rounded-sm py-1 px-3 hover:bg-gray-100 dark:hover:bg-gray-700 border-2 text-gray-600 dark:text-gray-400 dark:border-gray-600 gap-3 font-bold flex items-center">
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

                <div v-if="search || tag || feed || page !== 1" class="mb-8">
                    <Link href="./" data-flip-keep-drawer class="font-bold text-black dark:text-gray-400">&laquo; Back</Link>
                </div>

                <div class="mb-8">
                    <h2 class="font-bold mb-2 dark:text-gray-400">Search</h2>
                    <form @submit.prevent="submitSearch">
                        <input type="text"
                               v-model="searchInputVal"
                               placeholder="Post name or description"
                               @change="submitSearch"
                               class="border border-gray-300 dark:border-black rounded-sm px-2 py-1 w-full bg-white dark:bg-gray-900">
                        <button type="submit" class="hidden"></button>
                    </form>
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
                        <hr class="last:hidden dark:border-gray-700">
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
                    <span v-if="search"> ?= {{ search }}</span>
                </h2>

                <div>
                    <FormatSelector :format="postFormat" @change="updateFormat($event)" />
                </div>
            </div>

            <div :class="{'max-w-lg': postFormat === 'card', 'max-w-2xl': postFormat === 'list', 'w-full': postFormat === 'compact' || postFormat === 'grid'}">

                <div class="-mx-4" :class="{ 'flex flex-wrap': postFormat === 'grid' }">
                    <template v-for="post in posts">
                        <Post :post="post" :format="postFormat" :class="{'flex-1 min-w-sm w-md max-w-lg': postFormat === 'grid'}"/>
                        <div v-if="postFormat !== 'grid'" class="px-4">
                            <hr class="dark:border-gray-700">
                        </div>
                    </template>
                </div>

                <div class="py-2 flex">
                    <Link v-if="page > 1" :href="pagePath" :data="{page: page-1}" class="cursor-pointer text-gray-600 dark:text-gray-500 dark:hover:text-gray-300 font-bold py-2 hover:text-black">&laquo; Previous Page</Link>
                    <Link v-if="posts.length === 100" :href="pagePath" :data="{page: page + 1}" class="ml-auto cursor-pointer text-gray-600 dark:text-gray-500 dark:hover:text-gray-300 font-bold py-2 hover:text-black">Next Page &raquo;</Link>
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

    export default {
        components: {FlipView, FormatSelector, Tag, Feed, Post},
        props: {
            page: Number,
            feeds: Array,
            posts: Array,
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
            }
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
            }
        },
        data() {
            return {
                searchInputVal: this.search,
                postFormat: 'card',
                mobileShowOptions: false,
            }
        },
        methods: {
            submitSearch() {
                this.$inertia.visit(this.pagePath, {
                    data: {query: this.searchInputVal}
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
        }
    }
</script>
