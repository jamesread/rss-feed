# Tasks

## 1. AI settings

- [x] 1.1 Add the AI keys to `AppSettings` (decision 4):
  - Provider constants and fixed endpoints.
  - Model defaults.
  - `DEFAULT_SYSTEM_PROMPT`.
  - The accessors `aiProvider()`, `aiEndpoint()`, `aiModel()`, `aiApiKey()`, `hasAiApiKey()` and `aiSystemPrompt()`.

  Verify: new cases in `tests/Feature/AppSettingsTest.php` cover:
  - The defaults when nothing is saved.
  - The fixed endpoint winning over a saved endpoint for `anthropic`.
  - The saved endpoint being used for `openai_compatible`.
  - The saved prompt winning over the default.
- [x] 1.2 Add the `ai` section to `SettingsController::show`, the `PUT /settings/ai` endpoint and the `ai` route constraint (decision 4).

  Verify: `SettingsControllerTest` covers:
  - The `ai` props with `has_api_key` and no key value. Assert that the key string is absent from the whole JSON response.
  - A valid save giving 204 and persisting all fields.
  - An empty `api_key` keeping the saved key, and `forget_api_key` removing it.
  - An unknown provider, an empty model, and `openai_compatible` with a non-http endpoint each giving 422 with nothing changed.
  - A prompt equal to the default forgetting the saved prompt.
  - `GET /settings/ai` rendering `section=ai`.
- [x] 1.3 Add the `AI` menu to `Settings.vue` below `Feeds`:
  - Provider select.
  - Endpoint shown read-only for the fixed providers and editable for compatible.
  - Model input.
  - Password-type key input with a "Key saved" note and a "Remove saved key" checkbox.
  - System prompt textarea with a "Use default" button.
  - Save via `http.js` and per-field errors.

  Verify: `npm run build` passes. In a browser at a scratch port:
  - `/settings/ai` lists `General`, `Feeds` and `AI` in that order.
  - Switching providers updates the endpoint field.
  - Saving a key shows "Key saved" after a reload, with the field empty.

## 2. Share list storage and generation

- [x] 2.1 Add the migration `create_shared_posts_table` and the `SharedPost` model (decision 1). Verify: `php artisan migrate:fresh` in the test suite runs, and existing tests pass.
- [x] 2.2 Build `ShareTextGenerator`, which sends the OpenAI chat-completions and Anthropic messages requests (decision 3).

  Verify: new `tests/Unit` or `Feature` `ShareTextGeneratorTest` with `Http::fake()` asserts:
  - The URL, auth headers, model, system prompt and user message, including title, feed, description and link, for `openai`, `openrouter`, `anthropic` and `openai_compatible` (the last one with no key and no `Authorization` header).
  - The text parsed from each response shape.
  - An exception with a readable message for: a 401, a connection error, an empty reply, and a missing key on a provider that needs one.
- [x] 2.3 Build the `GenerateShareText` job (decision 2): `$tries=1`, writes only while `pending`, maps failures to `failed` plus a short `error`, and has a `failed()` hook.

  Verify: a job test asserts:
  - A pending entry becomes `ready` with the faked text.
  - A `ready` entry is untouched, and no HTTP request is sent.
  - A faked 401 gives `failed` with "rejected the API key".
  - No key saved gives `failed` with "Set up an AI provider in Settings → Social Media".
- [x] 2.4 Build `SharePostController` with `index`, `store`, `update`, `destroy` and `retry`, and add the routes (decisions 1, 2, 5 and 8).

  Verify: new `tests/Feature/SharePostControllerTest.php` covers:
  - `store` for a starred URL creating a pending entry, copying the thumbnail to `shared/`, and dispatching the job (`Queue::fake`).
  - `store` for an unstarred URL giving 422.
  - `store` twice keeping one entry and dispatching once.
  - Unstarring keeping the entry and its thumbnail.
  - `update` saving the text for a `ready` entry, and giving 422 for a pending one and for an empty text.
  - `retry` giving 422 on a `ready` entry, and resetting a `failed` one to `pending` and dispatching.
  - `destroy` removing the row and thumbnail while the star stays.
  - `index` rendering `Share` with the newest entry first and including `text`, `status` and `error`.
  - A write without a CSRF token in the production env giving 419.

## 3. Share button and sidebar link

- [x] 3.1 Add `sharedUrls` to `PostViewController` (posts views and starred view), and add the `resources/js/shared.js` store with optimistic `add` that reverts on failure (decision 7).

  Verify:
  - `PostViewControllerTest` asserts the `sharedUrls` prop on `/` and `/starred`.
  - New `tests/js/shared.test.js` covers `load`, `isShared`, `count`, a successful add, a failed add being reverted, and adding an already-shared URL not sending a request.
- [x] 3.2 Build `ShareButton.vue`, placed left of the star in `Post.vue` and `FlipTile.vue` and shown only for starred posts. Load `sharedUrls` in `Posts.vue` and add it to the flip reload `only` list. Add the `Post` prop `actions` (default true), which hides both buttons.

  Verify: `npm run build` and the vitest suite pass. In a browser:
  - A starred post shows the share button directly left of the star in the list and flip views.
  - An unstarred post shows none.
  - Starring a post shows the button without a reload.
  - Clicking it fills the icon.
- [x] 3.3 Add the `Share list (N)` link in the `Posts.vue` sidebar, below the Starred/RSS feed line, only when `starred` is true. Verify in a browser:
  - The link shows in the starred view and opens `/share`.
  - It is absent on `/` and `/t/<tag>`.

## 4. Share view

- [x] 4.1 Build `resources/js/Pages/Share.vue` (decisions 5 and 6):
  - Two-pane layout with the "« Starred" link.
  - List-style posts without action buttons, each with a text panel next to it on `md+` and below it on phones.
  - States: generating, failed with `Retry`, and an editable textarea.
  - Debounced save with a saved or error state.
  - `Share on LinkedIn`: clipboard copy plus `window.open` to the `shareActive&text` URL, disabled without text.
  - `Remove`.
  - An empty-list message.
  - Polling every 3 s while any entry is pending, stopped on unmount, without overwriting a text box being edited.

  Verify: `npm run build` passes. In a browser at a scratch port with a fake OpenAI-compatible endpoint (a small local stub returning a fixed reply), and with the queue worker running:
  - Adding a starred post shows "Generating…" and then the text.
  - Editing and reloading keeps the edit.
  - `Share on LinkedIn` opens a tab whose URL carries the encoded text.
  - `Remove` deletes the entry and the post stays starred.
  - With no key saved, the entry shows the setup message and `Retry`.
  - At 390 px width, the panel is stacked below the post and there is no horizontal scroll.
- [x] 4.2 Add a JS helper `linkedinShareUrl(text)` (used by `Share.vue`). Verify: a vitest case asserts the exact URL for text with line breaks, `#` and `&`.

## 5. Docs and checks

- [x] 5.1 Update the README:
  - Add a Features entry.
  - Add a "## Share list" section after "## Starred Posts". It covers the share button on starred posts, the sidebar link, generation once per post with retry on failure, editing, the LinkedIn hand-off (clipboard fallback), and removing.
  - Extend the Settings section with the AI menu: providers and endpoints, model, write-only key stored in the database, default prompt, and the no-login warning. Note that the queue worker must run.

  Verify: `grep -n -i "share list\|AI menu" README.md` shows the new sections.
- [x] 5.2 Run the gates and a persistence check:
  - `php artisan test`
  - `vendor/bin/phpstan --memory-limit=1G`
  - `npm test`
  - `npm run build`

  Then build a test image, run it as a container on a scratch port with a named volume (not `rss-flip` or `rss-local`), save AI settings, add a post to the share list, recreate the container, and confirm the settings and the share entry with its text are still there. Clean up the container, volume and image afterwards.
