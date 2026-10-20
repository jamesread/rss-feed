<template>
    <span class="truncate">
        <template v-for="(feed, i) in feeds" :key="feed.url">
            <template v-if="i > 0"> · </template>
            <Link :href="`f/${encodeURIComponent(encodeURIComponent(feed.url))}`"
                  :style="{color: feed.color}"
                  :class="linkClass">{{ feed.name }}</Link>
        </template>
    </span>
</template>
<script>
export default {
    props: {
        // The feeds of a post: all feeds a collapsed story came from, or just its own feed
        post: Object,
        linkClass: {
            type: String,
            default: '',
        },
    },
    computed: {
        feeds() {
            return this.post.feeds?.length ? this.post.feeds : [this.post.feed];
        },
    },
};
</script>
