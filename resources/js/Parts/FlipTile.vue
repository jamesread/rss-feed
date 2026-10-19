<template>
    <div class="group relative overflow-hidden bg-gray-200 dark:bg-gray-800">
        <template v-if="post.thumbnail">
            <img :src="`storage/${post.thumbnail}`"
                 loading="lazy"
                 decoding="async"
                 alt=""
                 class="absolute inset-0 size-full object-cover saturate-50 transition [@media(hover:hover)]:group-hover:brightness-110">
            <div class="absolute inset-0 bg-linear-to-t from-black/85 via-black/40 via-45% to-transparent"></div>
        </template>
        <div v-else class="absolute inset-0" :class="{'bg-gray-500 dark:bg-gray-700': !post.feed.color}" :style="{backgroundColor: post.feed.color || null}">
            <div class="absolute inset-0 bg-black/25"></div>
        </div>

        <div class="absolute inset-0 flex flex-col p-3 gap-1 text-white" :class="post.thumbnail ? 'justify-end' : 'justify-between'">
            <h3 class="font-bold leading-tight" :class="titleClass">
                <a :href="post.url" target="_blank" :title="post.title"
                   class="after:absolute after:inset-0 focus-visible:outline-none focus-visible:after:ring-4 focus-visible:after:ring-inset focus-visible:after:ring-white/80">{{ post.title }}</a>
            </h3>

            <div class="flex flex-col gap-1">
                <p v-if="size === 'xl' && post.description" class="text-sm text-white/80 line-clamp-2">{{ post.description }}</p>
                <div class="flex items-center gap-2 text-xs min-w-0">
                    <Link :href="`f/${encodeURIComponent(encodeURIComponent(post.feed.url))}`"
                          :style="{color: post.feed.color}"
                          class="relative z-10 font-bold bg-black/70 rounded-sm px-1.5 py-0.5 truncate hover:underline">{{ post.feed.name }}</Link>
                    <span v-if="size === 'l' || size === 'xl'" class="italic text-white/80 whitespace-nowrap" :title="isoTime">About {{ relativeTime }} ago</span>
                </div>
            </div>
        </div>
    </div>
</template>
<script>
import {timestampToRelativeTime} from "../util";

const thumbnailTitle = {
    xl: 'text-2xl sm:text-3xl line-clamp-4',
    l: 'text-xl line-clamp-3',
    m: 'text-base line-clamp-3',
    s: 'text-sm line-clamp-3',
};

const placeholderTitle = {
    xl: 'text-3xl sm:text-4xl line-clamp-6 text-white/90',
    l: 'text-2xl line-clamp-5 text-white/90',
    m: 'text-lg line-clamp-4 text-white/90',
    s: 'text-base line-clamp-3 text-white/90',
};

export default {
    props: {
        post: Object,
        size: {
            type: String,
            default: 'm',
        },
    },
    computed: {
        titleClass() {
            return (this.post.thumbnail ? thumbnailTitle : placeholderTitle)[this.size];
        },
        isoTime() {
            return (new Date(this.post.published_at * 1000)).toISOString();
        },
        relativeTime() {
            return timestampToRelativeTime(this.post.published_at);
        },
    },
}
</script>
