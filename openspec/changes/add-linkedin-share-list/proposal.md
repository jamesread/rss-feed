# Proposal

## Why

Starred posts are often ones worth passing on, but writing a LinkedIn post for each one is slow. A share list next to the starred posts, with an AI-drafted post text that can be edited and sent to LinkedIn, turns "worth sharing" into a few clicks.

## What Changes

- **Share button:** starred posts get a share button immediately to the left of the star, in the list, card, compact, grid and flip views. Clicking it adds the post to a separate share list. Posts that aren't starred show no share button.
- **Share list view at `/share`:**
  - Uses the same two-pane layout as the posts views.
  - Each post is shown the way the list view shows it, with an editable text box next to it holding the LinkedIn post text.
  - Each post has a button that copies the text and opens LinkedIn with the text filled in.
  - Each post has a button that removes it from the share list.
- **Navigation:** the sidebar of every posts view shows a `Share List - N` link below `Starred - N`. The share view's `« Back` link returns to wherever it was opened from (the starred view or the home page).
- **Text generation:**
  - Adding a post to the share list queues a background job that asks the configured AI provider for a LinkedIn post. The job uses the saved system prompt and the post's title, feed, description and URL.
  - Each post's text is generated once. The only exception is that a failed generation can be retried.
  - Edits in the text box are saved on the server.
- **AI settings menu:**
  - A new `AI` menu sits below `Feeds` on the settings page.
  - Provider choices: `OpenAI`, `Anthropic`, `OpenRouter` or `OpenAI compatible`. The first three fix the API endpoint; `OpenAI compatible` shows an editable endpoint field.
  - Also offers a model field and a write-only API key field. The key is saved on the server and never sent back to the browser.
  - A system prompt text box comes pre-filled with a default prompt. The default asks for:
    - A friendly LinkedIn post of at most three paragraphs, each at most three sentences.
    - Modest emoji use and no typical AI wording.
    - 3–4 hashtags, with the article link at the bottom.
- The share list and the AI settings live in the SQLite database on the storage volume, so they survive container restarts.

## Capabilities

### New Capabilities
- `share-list`: covers the following:
  - The list of posts picked for sharing and adding posts to it.
  - The share view.
  - AI generation of the post text, done once per post.
  - Editing the text, handing it off to LinkedIn, and removing posts.
- `ai-provider`: the AI connection settings (provider, endpoint, model, API key and system prompt) and the default system prompt.

### Modified Capabilities
- `app-settings`: the settings page gains a third menu, `AI`, below `Feeds`.
- `starred-posts`: starred posts show a share button left of the star, the sidebar links to the share list, and the sidebar reads `Starred - N`.
- `starred-feed`: the starred view no longer shows an RSS feed link in its sidebar; the feed stays announced in every page's HTML head.

## Impact

- **Backend:**
  - New `shared_posts` table and `SharedPost` model.
  - New `SharePostController` with routes `GET /share`, `POST /share`, `PUT /share`, `DELETE /share` and `POST /share/retry`.
  - New `GenerateShareText` queued job and a small AI client that speaks the OpenAI chat-completions and Anthropic messages APIs over Laravel's HTTP client (Guzzle is already installed).
  - `AppSettings` gains the AI keys, and `SettingsController` gains an AI section and save endpoint.
  - `PostViewController` sends `sharedUrls` with the posts pages.
- **Frontend:**
  - New `Share.vue` page, `ShareButton.vue` and `shared.js` store.
  - `Post.vue` and `FlipTile.vue` gain the share button.
  - `Posts.vue` gains the share list link in the sidebar, and `Settings.vue` gains the AI menu.
- **Runtime:**
  - Relies on the queue worker the container already runs (`queue:work` in `docker/services.conf`).
  - Outbound HTTPS from the container goes to the chosen AI endpoint.
- **Security:** the settings page has no login, so anyone who can reach the site can use the saved key, replace it, or point it at another endpoint. The README says this. The key is never sent back to the browser.
- **No new dependencies.**
