# Design

## Context

- **Stars:**
  - Stars live in `starred_posts`, which holds a snapshot of each post: title, description, published time, thumbnail copied to `storage/app/public/starred/`, feed and feeds.
  - The client store `starred.js` holds the starred URLs for the page. It sends `POST /starred` and `DELETE /starred` through `http.js`, which handles `requestJson` and the XSRF header.
  - `StarButton.vue` is used by `Post.vue` (list, card, compact and grid) and by `FlipTile.vue`.
- **Starred view:** `PostViewController::starred()` renders `Posts.vue` with `starred: true`. The sidebar in `Posts.vue` holds the `Starred (N)` link, the `RSS feed` link (starred view only) and the `Settings` link. The flip view drawer gets the same sidebar through a teleport.
- **Settings:**
  - `AppSettings` is a key/value store (`settings` table) with saved → config → default resolution.
  - `SettingsController` renders `Settings.vue` with a `section` prop. The `MENU` array in `Settings.vue` lists `general` and `feeds`, and the routes allow those two sections.
  - Writes go through JSON `PUT` endpoints that return 204 or 422.
- **Background work:** the container runs `queue:work` with the `database` queue (`docker/services.conf`, `--tries=3`). Tests use `QUEUE_CONNECTION=sync`. Existing jobs live in `app/Jobs/`.
- **App key:** `APP_KEY` is generated when the image is built (`docker/Dockerfile`), so it changes with every image pull. Anything encrypted with it would become unreadable after an update.
- **HTTP:** Guzzle is installed, so Laravel's `Http` facade and `Http::fake()` are available.

## Goals / Non-Goals

**Goals:**
- A share list that stands on its own, built from starred posts, with one AI generation per post.
- Two wire formats: OpenAI chat-completions (used by OpenAI, OpenRouter and OpenAI compatible) and Anthropic messages.
- Reuse the existing settings, JSON endpoint and list-view patterns.

**Non-Goals:**
- Other networks besides LinkedIn.
- Posting through the LinkedIn API or OAuth.
- Fetching the full article body for the prompt.
- Streaming responses.
- Per-post prompts.
- Usage or cost tracking.
- A share button anywhere other than on starred posts, and a share list link anywhere other than the starred sidebar.
- Login or protection for the settings page.

## Decisions

### 1. `shared_posts` table, copied from the starred snapshot on the server

- **Migration `create_shared_posts_table`:**
  - The same post columns as `starred_posts`: `url` (unique), `title`, `description`, `published_at`, `thumbnail`, `source`, `feed` (json) and `feeds` (json).
  - Plus `text` (text, nullable), `status` (`pending|ready|failed`), `error` (string, nullable) and `shared_at` (int).
- **Model:** `SharedPost`, with the same casts as `StarredPost`.
- **`POST /share {url}`:**
  - Looks up the `StarredPost` for that URL. If there is none, it returns 422.
  - If a `SharedPost` already exists for the URL, it returns 204 and changes nothing.
  - Otherwise it copies the snapshot fields and copies the thumbnail from `starred/…` to `shared/<sha1(url)>.<ext>`.
  - Then it creates the entry with `status=pending`, dispatches `GenerateShareText`, and returns 204.
- **Why server-side copy:** the client never sends post content, and the button only works for starred posts. The separate thumbnail copy means unstarring, which deletes `starred/…`, leaves the share entry intact.
- **`DELETE /share {url}`:** deletes the row and its `shared/` thumbnail. It does not touch `starred_posts`.

*Alternative:* a `shared` flag on `starred_posts`. Rejected because the user wants a separate list that survives unstarring.

### 2. Generation in a queued job, written once

- **`App\Jobs\GenerateShareText(string $url)`:**
  - Sets `$tries = 1` and `$timeout = 90`, so the worker's `--tries=3` does not triple-bill the provider.
  - Loads the entry and returns early unless `status` is `pending`.
  - Calls `ShareTextGenerator::generate(SharedPost)`. On success it saves `text`, sets `status=ready` and clears `error`.
- **Failures:**
  - Any exception (missing config, HTTP error or empty reply) sets `status=failed` and `error=<short message>`.
  - The `failed()` hook does the same, which covers a timeout.
  - Error messages are mapped to short, readable reasons:
    - "Set up an AI provider in Settings → Social Media"
    - "The AI provider rejected the API key (401)"
    - "The AI provider could not be reached"
  - The raw response body is logged, not shown.
- **`POST /share/retry {url}`:**
  - Allowed only when `status=failed`; any other status returns 422.
  - Sets `status=pending`, clears `error`, and dispatches the job again.
- **Generated once:** the job only writes while `status=pending`, and a `ready` entry can't go back to `pending`. That makes the "only once" rule hold even if a job is delivered twice.

*Alternative:* generating while the share view is open. Rejected because the user chose background generation, and a queued job keeps the browser out of provider calls and timeouts.

### 3. `ShareTextGenerator`: two request shapes over `Http`

- **Inputs:** provider, endpoint, model, key and system prompt, all read from `AppSettings`.
- **User message:** a plain block:
  ```
  Title: …
  Source: <feed name>
  Summary: <description>
  Link: <url>
  ```
- **OpenAI-style requests** (`openai`, `openrouter`, `openai_compatible`):
  - `POST {endpoint}/chat/completions` with `Authorization: Bearer <key>`.
  - Body: `{model, messages: [{role: system, content: prompt}, {role: user, content: …}]}`.
  - Reads `choices.0.message.content`.
  - For `openai_compatible`, an empty key is allowed (for example a local Ollama). Then no `Authorization` header is sent.
- **Anthropic requests** (`anthropic`):
  - `POST {endpoint}/messages` with headers `x-api-key` and `anthropic-version: 2023-06-01`.
  - Body: `{model, max_tokens: 1024, system: prompt, messages: [{role: user, content: …}]}`.
  - Joins the `content[].text` parts of type `text`.
- **Both paths:** a 60 s timeout. The result is trimmed. An empty result counts as a failure.
- **Fixed endpoints** are constants:
  - OpenAI: `https://api.openai.com/v1`
  - Anthropic: `https://api.anthropic.com/v1`
  - OpenRouter: `https://openrouter.ai/api/v1`
- **Tests** use `Http::fake()` for both shapes.

*Alternative:* a client SDK or Prism. Rejected because it adds a dependency for two small JSON calls.

### 4. AI settings in `AppSettings`

- The settings menu is labelled `Social Media` (URL `/settings/ai`) and the prompt field `Instructions`.

- **New keys:** `ai_provider`, `ai_endpoint` (only used for `openai_compatible`), `ai_model`, `ai_api_key` and `ai_system_prompt`.
- **Accessors:**
  - `aiProvider()`: default `openai`.
  - `aiEndpoint()`: the fixed endpoint for the first three providers, or the saved endpoint.
  - `aiModel()`: saved value, or the provider default. Defaults are `gpt-5-mini` for OpenAI and `claude-sonnet-5-5` for Anthropic; OpenRouter and compatible have no default (empty, so the field is required).
  - `aiApiKey()`
  - `hasAiApiKey()`
  - `aiSystemPrompt()`: saved prompt or `DEFAULT_SYSTEM_PROMPT`.
- **The API key is stored in plain text** in the SQLite database on the storage volume. It is not encrypted with `APP_KEY`, because that key changes with every image build (see Context) and would lose the saved key on update. The database file already has the same exposure as any secret placed in `docker-compose.yml`.
- **`PUT /settings/ai`** validates:
  - `provider` in the four values.
  - `endpoint` required with an http(s) regex when `openai_compatible`, and ignored otherwise.
  - `model` required, max 200.
  - `api_key` nullable string. Empty keeps the saved key.
  - `forget_api_key` boolean.
  - `system_prompt` nullable string, max 10 000. Null, or equal to the default, forgets the key.
- **The `ai` props** sent to the page are: `provider`, `endpoint`, `model`, `has_api_key`, `system_prompt`, `default_system_prompt`, and `fixed_endpoints` (a map). They never include the key.
- **Default system prompt** (a constant, `AppSettings::DEFAULT_AI_SYSTEM_PROMPT`): Markdown-style sections for content and perspective, tone and writing style (no buzzwords, no em dashes, no false contrasts, at most five emojis), structure and length (at most three paragraphs of at most three sentences, 3 to 4 hashtags, URL last), output requirements, and an Input section. The user message carries the post with the Input section's labels: `Article title:`, `Article source:`, `Article content or summary:`, `Article URL:`.

### 5. Share view and props

- **`GET /share`** renders the Inertia page `Share` with:
  - `posts`: share entries, newest `shared_at` first, each including `text`, `status` and `error`.
  - `feeds`: needed by `FeedNames`.
- **`Share.vue` layout:**
  - It copies the two-pane layout of `Posts.vue`/`Settings.vue`. The left pane has a "« Starred" link and a short explanation.
  - On `md+` screens, each entry is a two-column grid: `Post` with `format="list"` on the left and the text panel on the right. On phones they are stacked.
  - The `Post` there gets a prop `:actions="false"`, which hides the star and share buttons, so the list shows the article only.
- **Text panel:**
  - A `textarea`, with a "Generating…" placeholder that is disabled while pending.
  - On failure it shows the error plus `Retry`.
  - Buttons: `Share on LinkedIn` and `Remove`.
  - A small save state: "Saved", or the error.
- **Editing:**
  - `PUT /share {url, text}` saves the text. It is debounced at 800 ms after typing and also sent on blur.
  - It is only allowed for `status=ready`. The text is required, max 5000.
- **Polling:** while any entry is `pending`, `router.reload({only: ['posts']})` runs every 3 s. Polling stops when none are pending and when the page unmounts.
  - Text boxes the reader is editing keep their local value. The reload only fills entries whose local state is empty or pending.

### 6. LinkedIn hand-off

- The button calls `navigator.clipboard.writeText(text)`, which needs HTTPS or localhost. The live site is HTTPS, and failure is ignored.
- It then calls `window.open('https://www.linkedin.com/feed/?shareActive=true&text=' + encodeURIComponent(text), '_blank', 'noopener')`.
- After clicking, a note says "Copied — paste it in LinkedIn if the text is missing".
- LinkedIn has no documented prefill URL for post text. The `shareActive&text` URL is what works today, and the clipboard copy is the fallback.

*Alternative:* `sharing/share-offsite/?url=` only shares the link and ignores the text, so it doesn't meet the requirement.

### 7. Share button and client store

- **`resources/js/shared.js`:** mirrors `starred.js`, with `load(urls)`, `isShared(url)`, `count()` and `add(post)`.
  - `toggle` is optimistic and reverts on failure, like the star. It sends `POST share {url}` to add and `DELETE share {url}` to remove a listed post.
- **`PostViewController`:** `renderPostsView` and `starred()` add a `sharedUrls` prop. `Posts.vue` loads it into the store, the same way as `starredUrls`, and adds `sharedUrls` to the flip wrap reload's `only` list.
- **`ShareButton.vue`:**
  - Shown when `starred.isStarred(post.url)`.
  - Its icon is "box with arrow". It is filled with a brand-neutral accent when the post is shared.
  - Labels: `aria-label`/`title` "Add to share list" or "Remove from share list".
  - Clicking a shared post removes it from the share list, with its text.
- **Placement:** in `Post.vue` and `FlipTile.vue` it is rendered just before `StarButton`. The `ml-auto` moves from the star to the share button, or to a wrapper, so both sit at the right edge.
- **Sidebar:** `Posts.vue` gets a `Share List - N` link in every posts view, below `Starred - N` and above `Settings`. From the starred view it links to `share?from=starred`, and the share view's `« Back` link returns to `starred` for that parameter and to `./` otherwise.

### 8. Routes

```
GET    /share          SharePostController@index
POST   /share          SharePostController@store
PUT    /share          SharePostController@update
DELETE /share          SharePostController@destroy
POST   /share/retry    SharePostController@retry
GET    /settings/{section?}  whereIn: general, feeds, ai
PUT    /settings/ai    SettingsController@updateAi
```

All write routes are inside the `web` group, so CSRF applies (the spec requires it).

## Risks / Trade-offs

- **Unprotected key:** anyone who can reach the site can generate text on the saved key, or replace the endpoint and model. The key itself is never readable through the UI. → The README states this. It matches the "no protection" decision for settings.
- **LinkedIn prefill is undocumented and could stop working** → the clipboard copy and the note keep the flow usable.
- **Model default names age** → the model field is editable and validated only as non-empty. A wrong model surfaces as a readable error with `Retry`.
- **Worker down:** entries stay `pending`. → The share view shows "Generating…". It could look stuck, but entries recover once the worker runs again. Tests run synchronously.
- **Endpoint as SSRF vector:** `openai_compatible` lets the server POST to any URL. This is accepted for a single-operator self-hosted reader. It is the same trust level as the editable feed URLs, which the server already fetches.
