<template>
    <!-- Only starred posts can be shared, so the button comes and goes with the star -->
    <button v-if="isStarred" type="button"
            class="inline-flex items-center justify-center rounded-full"
            :aria-label="isShared ? 'Remove from share list' : 'Add to share list'"
            :aria-pressed="isShared ? 'true' : 'false'"
            :title="isShared ? 'Remove from share list' : 'Add to share list'"
            @click.stop.prevent="shared.toggle(post)">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" :class="{'text-sky-500': isShared}">
            <path fill-rule="evenodd" d="M3.5 6a.5.5 0 0 0-.5.5v8a.5.5 0 0 0 .5.5h9a.5.5 0 0 0 .5-.5v-8a.5.5 0 0 0-.5-.5h-2a.5.5 0 0 1 0-1h2A1.5 1.5 0 0 1 14 6.5v8a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 2 14.5v-8A1.5 1.5 0 0 1 3.5 5h2a.5.5 0 0 1 0 1z"/>
            <path fill-rule="evenodd" d="M7.646.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1-.708.708L8.5 1.707V10.5a.5.5 0 0 1-1 0V1.707L5.354 3.854a.5.5 0 1 1-.708-.708z"/>
        </svg>
    </button>
</template>
<script>
import {starred} from "../starred";
import {shared} from "../shared";

export default {
    props: {
        post: Object,
    },
    data() {
        return {starred, shared};
    },
    computed: {
        isStarred() {
            return this.starred.isStarred(this.post.url);
        },
        isShared() {
            return this.shared.isShared(this.post.url);
        },
    },
};
</script>
