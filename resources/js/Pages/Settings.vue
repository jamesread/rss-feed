<template>
    <div class="md:flex h-full min-h-0">
        <div class="md:w-1/3 xl:w-1/4 md:flex md:justify-end md:border-r dark:border-black px-6 bg-gray-50 dark:bg-dark-surface md:px-12 md:overflow-auto py-3">
            <div class="md:max-w-xs w-full">
                <div class="mb-6 md:mb-8">
                    <Link href="./" class="font-bold text-black dark:text-gray-400">&laquo; Back</Link>
                </div>

                <nav class="mb-3 md:mb-8" aria-label="Settings menu">
                    <h2 class="font-bold mb-2 dark:text-gray-400">Settings</h2>
                    <ul class="flex gap-4 md:block md:space-y-1">
                        <li v-for="item in menu" :key="item.section">
                            <Link :href="item.href"
                                  class="font-bold text-gray-600 dark:text-gray-500 hover:text-black dark:hover:text-gray-300"
                                  :class="{'text-black! dark:text-gray-300!': item.section === section}"
                                  :aria-current="item.section === section ? 'page' : null">{{ item.label }}</Link>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>

        <!-- relative: the visually hidden row labels are absolutely positioned, so they must stay inside this scrolling pane instead of stretching the page -->
        <div class="relative md:w-2/3 xl:w-3/4 px-6 md:px-12 md:overflow-auto md:h-full py-2">
            <h2 class="font-bold text-black dark:text-gray-400 mb-4">Settings / {{ currentLabel }}</h2>

            <form v-if="section === 'general'" class="max-w-lg space-y-8 pb-8" @submit.prevent="saveGeneral">
                <fieldset>
                    <legend class="font-bold mb-2 dark:text-gray-400">Theme</legend>
                    <div class="flex border rounded-sm border-gray-300 dark:border-dark-line divide-x divide-gray-300 dark:divide-dark-line text-sm text-gray-600 dark:text-gray-400">
                        <label v-for="option in themeOptions" :key="option.value"
                               class="flex-1 text-center px-2 py-1 cursor-pointer hover:text-black dark:hover:text-gray-200 has-focus-visible:ring-2 has-focus-visible:ring-gray-500"
                               :class="{'bg-gray-200 text-gray-900 font-bold dark:bg-dark-raised dark:text-gray-200': option.value === form.theme}">
                            <input type="radio" name="theme" class="sr-only" :value="option.value" v-model="form.theme">
                            {{ option.label }}
                        </label>
                    </div>
                    <p v-if="errors.theme" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ errors.theme }}</p>
                </fieldset>

                <fieldset>
                    <legend class="font-bold mb-2 dark:text-gray-400">Dark background</legend>
                    <p class="text-sm text-gray-600 dark:text-gray-500 mb-2">Page background of the dark theme. Sidebar, buttons and dividers use matching shades.</p>
                    <div class="flex items-center gap-4">
                        <input type="color" aria-label="Dark background colour"
                               class="h-9 w-14 cursor-pointer rounded-sm border border-gray-300 dark:border-dark-line bg-white dark:bg-dark-page p-0.5 disabled:cursor-not-allowed disabled:opacity-50"
                               :value="pickerColor" :disabled="form.useDefaultBackground"
                               @input="form.darkBackground = $event.target.value">
                        <label class="flex items-center gap-2 text-gray-900 dark:text-gray-400 cursor-pointer">
                            <input type="checkbox" v-model="form.useDefaultBackground">
                            Use default <span class="text-sm text-gray-600 dark:text-gray-500">({{ general.default_dark_background }})</span>
                        </label>
                    </div>
                    <p v-if="errors.dark_background" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ errors.dark_background }}</p>
                </fieldset>

                <fieldset>
                    <legend class="font-bold mb-2 dark:text-gray-400">Auto-advance</legend>
                    <p class="text-sm text-gray-600 dark:text-gray-500 mb-3">Turns the pages of the flip view automatically, on every device. A <code>?autoplay</code> URL parameter still overrides it.</p>
                    <label class="flex items-center justify-between gap-3 max-w-xs text-gray-900 dark:text-gray-400 cursor-pointer mb-4">
                        <span>Advance pages automatically</span>
                        <input type="checkbox" class="peer sr-only" v-model="form.autoplayEnabled">
                        <span class="relative inline-block w-9 h-5 shrink-0 rounded-full bg-gray-300 dark:bg-dark-line transition peer-checked:bg-gray-800 dark:peer-checked:bg-gray-300 peer-focus-visible:ring-2 peer-focus-visible:ring-gray-500
                                     after:absolute after:top-0.5 after:left-0.5 after:size-4 after:rounded-full after:bg-white dark:after:bg-dark-page after:transition peer-checked:after:translate-x-4"
                              aria-hidden="true"></span>
                    </label>
                    <label class="flex items-center justify-between gap-3 max-w-xs text-gray-900 dark:text-gray-400">
                        <span>Interval</span>
                        <select v-model.number="form.autoplaySeconds"
                                class="border border-gray-300 dark:border-dark-line rounded-sm px-2 py-1 bg-white dark:bg-dark-page">
                            <option v-for="seconds in autoplayIntervals" :key="seconds" :value="seconds">{{ intervalLabel(seconds) }}</option>
                        </select>
                    </label>
                    <p v-if="errors.autoplay_seconds" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ errors.autoplay_seconds }}</p>
                </fieldset>

                <div class="flex items-center gap-4">
                    <button type="submit" :disabled="saving"
                            class="rounded-sm border-2 px-4 py-1 font-bold text-gray-700 border-gray-300 hover:bg-gray-100 dark:text-gray-300 dark:border-dark-line dark:hover:bg-dark-raised disabled:opacity-50">
                        Save
                    </button>
                    <p v-if="message" class="text-sm" :class="messageIsError ? 'text-red-600 dark:text-red-400' : 'text-gray-600 dark:text-gray-400'" role="status">{{ message }}</p>
                </div>
            </form>
            <form v-if="section === 'feeds'" class="pb-8" novalidate @submit.prevent="saveFeeds">
                <div class="hidden md:grid grid-cols-[minmax(8rem,1fr)_minmax(8rem,1fr)_minmax(12rem,2fr)_minmax(10rem,1.5fr)_2rem] gap-3 mb-1 text-sm font-bold text-gray-700 dark:text-gray-400">
                    <span>Title *</span>
                    <span class="flex items-center gap-1">Tags * <TagsHint id="tags-hint-header" /></span>
                    <span>Feed URL *</span>
                    <span>Picture origin</span>
                    <span></span>
                </div>

                <p v-if="feedRows.length === 0" class="py-4 text-gray-600 dark:text-gray-400">No feeds yet. Add one below.</p>

                <div v-for="(row, i) in feedRows" :key="row.key"
                     class="grid gap-2 md:gap-3 md:grid-cols-[minmax(8rem,1fr)_minmax(8rem,1fr)_minmax(12rem,2fr)_minmax(10rem,1.5fr)_2rem] items-start py-3 md:py-1.5 border-b md:border-0 border-gray-200 dark:border-dark-raised">
                    <div v-for="field in feedFields" :key="field.name">
                        <label :for="`row-${row.key}-${field.name}`"
                               class="md:sr-only flex items-center gap-1 text-sm font-bold text-gray-700 dark:text-gray-400 mb-0.5">
                            {{ field.label }}{{ field.required ? ' *' : '' }}
                            <TagsHint v-if="field.name === 'tags'" :id="`tags-hint-${row.key}`" class="md:hidden" />
                        </label>
                        <input :id="`row-${row.key}-${field.name}`" type="text" v-model="row[field.name]"
                               :placeholder="field.placeholder" :required="field.required" :aria-required="field.required ? 'true' : null"
                               :aria-invalid="errors[`rows.${i}.${field.name}`] ? 'true' : null"
                               class="w-full border rounded-sm px-2 py-1 bg-white dark:bg-dark-page"
                               :class="errors[`rows.${i}.${field.name}`] ? 'border-red-500 dark:border-red-500' : 'border-gray-300 dark:border-dark-line'">
                        <p v-if="errors[`rows.${i}.${field.name}`]" class="mt-0.5 text-sm text-red-600 dark:text-red-400">{{ errors[`rows.${i}.${field.name}`] }}</p>
                    </div>
                    <button type="button" :aria-label="`Remove ${row.title || 'feed'}`" title="Remove feed"
                            class="justify-self-start md:justify-self-center md:mt-1 flex items-center gap-1 rounded-sm p-1 text-sm font-bold text-gray-600 hover:bg-gray-200 hover:text-black dark:text-gray-400 dark:hover:bg-dark-raised dark:hover:text-gray-200"
                            @click="removeRow(i)">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/></svg>
                        <span class="md:hidden" aria-hidden="true">Remove</span>
                    </button>
                </div>

                <button type="button" class="mt-3 font-bold text-gray-600 dark:text-gray-500 hover:text-black dark:hover:text-gray-300" @click="addRow">+ Add feed</button>

                <p class="mt-6 text-sm text-gray-600 dark:text-gray-500">Saving rewrites the feeds file. Comment lines and blank lines in it are not kept.</p>
                <div class="mt-3 flex items-center gap-4">
                    <button type="submit" :disabled="saving"
                            class="rounded-sm border-2 px-4 py-1 font-bold text-gray-700 border-gray-300 hover:bg-gray-100 dark:text-gray-300 dark:border-dark-line dark:hover:bg-dark-raised disabled:opacity-50">
                        Save
                    </button>
                    <p v-if="message" class="text-sm" :class="messageIsError ? 'text-red-600 dark:text-red-400' : 'text-gray-600 dark:text-gray-400'" role="status">{{ message }}</p>
                </div>
            </form>
            <form v-if="section === 'ai'" class="max-w-2xl space-y-8 pb-8" novalidate @submit.prevent="saveAi">
                <p class="text-sm text-gray-600 dark:text-gray-500">Used to write a LinkedIn post for each post in the share list.</p>

                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block">
                        <span class="block font-bold mb-1 dark:text-gray-400">Provider</span>
                        <select v-model="aiForm.provider" @change="providerChanged"
                                class="w-full border border-gray-300 dark:border-dark-line rounded-sm px-2 py-1 bg-white dark:bg-dark-page">
                            <option v-for="option in providerOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                        <span v-if="errors.provider" class="mt-1 block text-sm text-red-600 dark:text-red-400">{{ errors.provider }}</span>
                    </label>
                    <label class="block">
                        <span class="block font-bold mb-1 dark:text-gray-400">Model *</span>
                        <input type="text" v-model="aiForm.model" placeholder="Model name" :aria-invalid="errors.model ? 'true' : null"
                               class="w-full border rounded-sm px-2 py-1 bg-white dark:bg-dark-page"
                               :class="errors.model ? 'border-red-500 dark:border-red-500' : 'border-gray-300 dark:border-dark-line'">
                        <span v-if="errors.model" class="mt-1 block text-sm text-red-600 dark:text-red-400">{{ errors.model }}</span>
                    </label>
                </div>

                <label class="block">
                    <span class="block font-bold mb-1 dark:text-gray-400">API endpoint{{ fixedEndpoint ? '' : ' *' }}</span>
                    <input v-if="fixedEndpoint" type="text" :value="fixedEndpoint" readonly
                           class="w-full border border-gray-300 dark:border-dark-line rounded-sm px-2 py-1 bg-gray-100 text-gray-600 dark:bg-dark-surface dark:text-gray-500">
                    <input v-else type="url" v-model="aiForm.endpoint" placeholder="http://localhost:11434/v1" :aria-invalid="errors.endpoint ? 'true' : null"
                           class="w-full border rounded-sm px-2 py-1 bg-white dark:bg-dark-page"
                           :class="errors.endpoint ? 'border-red-500 dark:border-red-500' : 'border-gray-300 dark:border-dark-line'">
                    <span v-if="!fixedEndpoint" class="mt-1 block text-sm text-gray-600 dark:text-gray-500">Base URL of an API that speaks the OpenAI chat completions format.</span>
                    <span v-if="errors.endpoint" class="mt-1 block text-sm text-red-600 dark:text-red-400">{{ errors.endpoint }}</span>
                </label>

                <div>
                    <label for="ai-api-key" class="block font-bold mb-1 dark:text-gray-400">API key</label>
                    <input id="ai-api-key" type="password" v-model="aiForm.apiKey" autocomplete="off" :disabled="aiForm.forgetApiKey"
                           :placeholder="ai.has_api_key ? 'A key is saved. Enter a new one to replace it.' : 'No key saved'"
                           class="w-full border border-gray-300 dark:border-dark-line rounded-sm px-2 py-1 bg-white dark:bg-dark-page disabled:opacity-50">
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-500">
                        {{ ai.has_api_key ? 'Key saved.' : 'No key saved.' }} The key is stored on the server and never shown again. Settings have no login, so anyone who can open this site can use it.
                    </p>
                    <label v-if="ai.has_api_key" class="mt-2 flex items-center gap-2 text-gray-900 dark:text-gray-400 cursor-pointer">
                        <input type="checkbox" v-model="aiForm.forgetApiKey"> Remove saved key
                    </label>
                    <p v-if="errors.api_key" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ errors.api_key }}</p>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label for="ai-system-prompt" class="font-bold dark:text-gray-400">Instructions</label>
                        <button type="button" class="text-sm font-bold text-gray-600 dark:text-gray-500 hover:text-black dark:hover:text-gray-300 disabled:opacity-50"
                                :disabled="aiForm.systemPrompt === ai.default_system_prompt"
                                @click="aiForm.systemPrompt = ai.default_system_prompt">Use default</button>
                    </div>
                    <textarea id="ai-system-prompt" v-model="aiForm.systemPrompt" rows="9"
                              class="w-full border border-gray-300 dark:border-dark-line rounded-sm px-2 py-1 bg-white dark:bg-dark-page text-sm"></textarea>
                    <p v-if="errors.system_prompt" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ errors.system_prompt }}</p>
                </div>

                <div class="flex items-center gap-4">
                    <button type="submit" :disabled="saving"
                            class="rounded-sm border-2 px-4 py-1 font-bold text-gray-700 border-gray-300 hover:bg-gray-100 dark:text-gray-300 dark:border-dark-line dark:hover:bg-dark-raised disabled:opacity-50">
                        Save
                    </button>
                    <p v-if="message" class="text-sm" :class="messageIsError ? 'text-red-600 dark:text-red-400' : 'text-gray-600 dark:text-gray-400'" role="status">{{ message }}</p>
                </div>
            </form>
        </div>
    </div>
</template>
<script>
    import {router} from "@inertiajs/vue3";
    import {requestJson} from "../http";
    import TagsHint from "../Parts/TagsHint.vue";

    const MENU = [
        {section: 'general', label: 'General', href: 'settings'},
        {section: 'feeds', label: 'Feeds', href: 'settings/feeds'},
        {section: 'ai', label: 'Social Media', href: 'settings/ai'},
    ];

    const PROVIDER_OPTIONS = [
        {value: 'openai', label: 'OpenAI'},
        {value: 'anthropic', label: 'Anthropic'},
        {value: 'openrouter', label: 'OpenRouter'},
        {value: 'openai_compatible', label: 'OpenAI compatible'},
    ];

    /** Editable copy of the AI settings. The key field starts empty: the saved key never reaches the browser. */
    function aiFormFrom(ai) {
        return {
            provider: ai.provider,
            endpoint: ai.endpoint,
            model: ai.model,
            apiKey: '',
            forgetApiKey: false,
            systemPrompt: ai.system_prompt,
        };
    }

    /** Expand "#rgb" to "#rrggbb", the only form a colour input accepts. */
    function expandColor(color) {
        return /^#[0-9a-f]{3}$/i.test(color) ? '#' + color.slice(1).split('').map(c => c + c).join('') : color;
    }

    const FEED_FIELDS = [
        {name: 'title', label: 'Title', required: true, placeholder: 'Tech News'},
        {name: 'tags', label: 'Tags', required: true, placeholder: 'tech, news'},
        {name: 'url', label: 'Feed URL', required: true, placeholder: 'https://example.com/feed.xml'},
        {name: 'site', label: 'Picture origin', required: false, placeholder: 'https://www.example.com'},
    ];

    let nextRowKey = 1;

    /** Rows for editing. Colour and hidden flag are not shown but are sent back unchanged. */
    function editableRows(rows) {
        return (rows || []).map(row => ({...row, key: nextRowKey++}));
    }

    /** First error message per field from a 422 response body. */
    function firstErrors(data) {
        return Object.fromEntries(Object.entries(data?.errors || {}).map(([field, messages]) => [field, messages[0]]));
    }

    export default {
        components: {TagsHint},
        props: {
            section: String,
            general: Object,
            autoplayIntervals: Array,
            rows: Array,
            ai: Object,
        },
        data() {
            return {
                menu: MENU,
                feedFields: FEED_FIELDS,
                feedRows: editableRows(this.rows),
                providerOptions: PROVIDER_OPTIONS,
                aiForm: aiFormFrom(this.ai),
                themeOptions: [
                    {value: 'system', label: 'System'},
                    {value: 'light', label: 'Light'},
                    {value: 'dark', label: 'Dark'},
                ],
                form: {
                    theme: this.general.theme,
                    darkBackground: this.general.dark_background || this.general.default_dark_background,
                    useDefaultBackground: !this.general.dark_background,
                    autoplayEnabled: this.general.autoplay_enabled,
                    autoplaySeconds: this.general.autoplay_seconds,
                },
                errors: {},
                message: '',
                messageIsError: false,
                saving: false,
            };
        },
        computed: {
            currentLabel() {
                return MENU.find(item => item.section === this.section)?.label || '';
            },
            fixedEndpoint() {
                return this.ai.fixed_endpoints[this.aiForm.provider] || null;
            },
            pickerColor() {
                return expandColor(this.form.useDefaultBackground ? this.general.default_dark_background : this.form.darkBackground);
            },
        },
        watch: {
            rows(rows) {
                this.feedRows = editableRows(rows);
            },
            ai(ai) {
                this.aiForm = aiFormFrom(ai);
            },
        },
        methods: {
            intervalLabel(seconds) {
                return seconds < 60 ? `${seconds} s` : `${seconds / 60} min`;
            },
            showResult(result, successMessage) {
                this.errors = result.status === 422 ? firstErrors(result.data) : {};
                this.messageIsError = !result.ok;
                if (result.ok) {
                    this.message = successMessage;
                } else if (result.status === 422) {
                    this.message = 'Please correct the marked fields.';
                } else {
                    this.message = result.data?.message || 'Saving failed. Please try again.';
                }
            },
            async saveGeneral() {
                this.saving = true;
                const result = await requestJson('PUT', 'settings', {
                    theme: this.form.theme,
                    dark_background: this.form.useDefaultBackground ? null : this.form.darkBackground,
                    autoplay_enabled: this.form.autoplayEnabled,
                    autoplay_seconds: this.form.autoplaySeconds,
                });
                this.saving = false;
                this.showResult(result, 'Saved.');
                if (result.ok) {
                    // Theme and background are set on the server-rendered <html>, so load the page again
                    window.location.reload();
                }
            },
            addRow() {
                this.feedRows.push({title: '', tags: '', url: '', site: '', color: '', hidden: false, key: nextRowKey++});
                this.$nextTick(() => document.getElementById(`row-${this.feedRows[this.feedRows.length - 1].key}-title`)?.focus());
            },
            removeRow(index) {
                this.feedRows.splice(index, 1);
                // Error keys are row positions, so they no longer match after a removal
                this.errors = {};
            },
            async saveFeeds() {
                this.saving = true;
                const result = await requestJson('PUT', 'settings/feeds', {
                    rows: this.feedRows.map(({title, tags, url, site, color, hidden}) => ({title, tags, url, site, color, hidden})),
                });
                this.saving = false;
                this.showResult(result, 'Saved.');
                if (result.ok) {
                    router.reload({only: ['rows']});
                }
            },
            providerChanged() {
                // Swap in the new provider's default model unless a model of its own was typed
                const defaults = Object.values(this.ai.default_models);
                if (this.aiForm.model === '' || defaults.includes(this.aiForm.model)) {
                    this.aiForm.model = this.ai.default_models[this.aiForm.provider] || '';
                }
            },
            async saveAi() {
                this.saving = true;
                const result = await requestJson('PUT', 'settings/ai', {
                    provider: this.aiForm.provider,
                    endpoint: this.fixedEndpoint ? null : this.aiForm.endpoint,
                    model: this.aiForm.model,
                    api_key: this.aiForm.forgetApiKey ? null : this.aiForm.apiKey,
                    forget_api_key: this.aiForm.forgetApiKey,
                    system_prompt: this.aiForm.systemPrompt,
                });
                this.saving = false;
                this.showResult(result, 'Saved.');
                if (result.ok) {
                    router.reload({only: ['ai']});
                }
            },
        },
    }
</script>
