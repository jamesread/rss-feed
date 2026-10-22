<template>
    <span class="group relative inline-flex">
        <button type="button" :aria-describedby="id" aria-label="About tags"
                class="rounded-full text-gray-500 hover:text-gray-900 dark:text-gray-500 dark:hover:text-gray-200 focus-visible:outline-2 focus-visible:outline-gray-500">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
            </svg>
        </button>
        <span :id="id" role="tooltip"
              class="invisible group-hover:visible group-focus-within:visible absolute -left-8 top-full z-10 mt-1 w-56 rounded-sm bg-gray-900 px-2 py-1 text-xs font-normal text-white shadow-lg dark:bg-gray-200 dark:text-gray-900">
            Separate several tags with commas, for example <code>tag_1, tag_2</code>. Spaces after the commas are optional.
        </span>
    </span>
</template>
<script>
    export default {
        props: {
            id: String,
        },
    }
</script>
