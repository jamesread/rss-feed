<template>
    <div
        :class="{'py-2': formatCompact}"
        class="rss-post p-4 block border border-transparent hover:border-gray-200 hover:bg-gray-100
        dark:hover:border-dark-raised dark:hover:bg-dark-surface rounded hover:relative hover:z-20 -my-px">

        <div class="flex text-sm text-gray-600 dark:text-gray-400 items-center flex-wrap">
            <FeedNames :post="post" class="font-bold text-xs block min-w-0" />
            <span v-if="post.source" class="ml-1.5 text-xs">via {{ post.source }}</span>
            <div class="px-2">&bull;</div>
            <div class="italic" :title="isoTime">About {{ relatedPublishedTime }} ago</div>
            <div class="px-2 hidden md:block">&bull;</div>
            <div class="flex gap-2 basis-full md:basis-auto">
                <Tag v-for="tag in post.feed.tags" :tag="tag"/>
            </div>
            <div v-if="actions" class="ml-auto flex -my-1">
                <ShareButton :post="post" class="size-7 text-gray-500 hover:text-gray-900 dark:hover:text-gray-200" />
                <StarButton :post="post" class="size-7 text-gray-500 hover:text-gray-900 dark:hover:text-gray-200" />
            </div>
        </div>

        <div :class="{'flex items-center gap-5': formatList && post.thumbnail}">
            <div v-if="post.thumbnail && !formatCompact" :class="{'w-32': formatList}">
                <a :href="post.url" target="_blank" class="block overflow-hidden rounded-sm my-2 border dark:border-black">
                    <img :src="`storage/${post.thumbnail}`"
                         loading="lazy"
                         class="w-full object-cover saturate-50"
                         :class="{'h-16 md:h-16 lg:h-16': formatList, 'h-32 md:h-48 lg:h-64': !formatList}"
                         :alt="post.title">
                </a>
            </div>
            <div v-if="formatGrid && !post.thumbnail">
              <a :href="post.url" target="_blank"
                 :title="post.title"
                 class="relative block overflow-hidden rounded-sm my-2 border dark:border-black">
                <div :style="{backgroundColor: post.feed.color}"
                    class="w-full object-cover saturate-80 text-white/50
                     flex items-center p-5 opacity-60
                     text-5xl font-bold h-32 md:h-48 lg:h-64">
                  <template v-if="!showFavicon">{{ post.title }}</template>
                </div>
                <div v-if="showFavicon" class="absolute inset-0 flex items-center justify-center">
                  <img :src="`storage/${post.feed.favicon}`"
                       loading="lazy"
                       alt=""
                       class="size-27 max-h-[75%] w-auto aspect-square object-contain rounded-xl bg-white/90 p-2 shadow"
                       @error="faviconFailed = true">
                </div>
              </a>
            </div>

            <div :class="{'whitespace-nowrap text-ellipsis overflow-hidden': formatCompact, 'flex-1': formatList}">
                <h3 class="font-bold text-black dark:text-gray-300" :class="{'text-lg': formatCard, 'text-md': formatList, 'inline': formatCompact}">
                    <a :href="post.url" target="_blank" class="hover:underline">{{ post.title }}</a>
                </h3>

                <p class="text-gray-600 dark:text-gray-400" :class="{'inline ml-2 text-sm': formatCompact}">
                    {{ post.description}}
                </p>
            </div>
        </div>
    </div>
</template>
<script>
import Tag from "./Tag.vue";
import FeedNames from "./FeedNames.vue";
import StarButton from "./StarButton.vue";
import ShareButton from "./ShareButton.vue";
import {timestampToRelativeTime} from "../util";

export default {
    components: {FeedNames, ShareButton, StarButton, Tag},
    props: {
        post: Object,
        format: {
            type: String,
            default: 'list',
        },
        // Show the share and star buttons
        actions: {
            type: Boolean,
            default: true,
        },
    },
    data() {
        return {
            faviconFailed: false,
        };
    },
    computed: {
        showFavicon() {
            return !!this.post.feed.favicon && !this.faviconFailed;
        },
        isoTime() {
            return (new Date(this.post.published_at * 1000)).toISOString();
        },
        relatedPublishedTime() {
            return timestampToRelativeTime(this.post.published_at);
        },
        formatCard() {
            return this.format === 'card' || this.format === 'grid';
        },
        formatGrid() {
          return this.format === 'grid';
        },
        formatList() {
            return this.format === 'list';
        },
        formatCompact() {
            return this.format === 'compact';
        }
    }
}
</script>
