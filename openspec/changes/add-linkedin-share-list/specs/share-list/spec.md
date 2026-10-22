# Spec Delta

## Purpose

Lets a reader collect starred posts they want to pass on, get an AI-drafted LinkedIn post for each one, adjust the text and hand it to LinkedIn.

## ADDED Requirements

### Requirement: Starred posts can be added to the share list
Clicking a starred post's share button SHALL add that post to the share list. The list is one list for the whole installation, kept on the server. Clicking the share button of a post that is already listed SHALL remove it from the share list, together with its text. The button SHALL show whether the post is in the share list.

#### Scenario: Add a post
- **WHEN** a reader clicks the share button on a starred post that is not in the share list
- **THEN** the post is added to the share list and the button shows it as listed without a page reload

#### Scenario: Second click removes
- **WHEN** a reader clicks the share button on a post that is already in the share list
- **THEN** the post and its text are removed from the share list and the button shows it as not listed, without a page reload

#### Scenario: Repeated add request
- **WHEN** a request asks to add a URL that is already in the share list
- **THEN** the share list keeps one entry for that post and its text is unchanged

#### Scenario: Only starred posts can be added
- **WHEN** a request asks to add a URL that is not a starred post
- **THEN** the request is rejected and the share list is unchanged

#### Scenario: Request fails
- **WHEN** the server does not accept the add or remove request
- **THEN** the button returns to its previous state

### Requirement: Share list entries are independent of stars
A share list entry SHALL keep its own copy of the post's title, description, feed, publication time and thumbnail. Unstarring a post or pruning old posts SHALL NOT remove or change its share list entry.

#### Scenario: Unstar a shared post
- **WHEN** a post in the share list is unstarred
- **THEN** it is still listed in the share view with its thumbnail and text

### Requirement: Share view at /share
The application SHALL serve the share view at `/share`, using the same two-pane layout as the posts views. Each entry SHALL be shown as the list view shows a post, with the most recently added first. Next to it (below it on narrow screens) SHALL be the post's LinkedIn text in an editable text box. The left pane SHALL hold a `« Back` link that returns to the starred view when the share view was opened from there, and to the home page otherwise. The view SHALL work under a proxy sub-path.

#### Scenario: Open the share view
- **WHEN** a reader opens `/share` with two posts in the share list
- **THEN** both posts are shown in list view style, each with its text box, the most recently added first

#### Scenario: Back to the starred view
- **WHEN** a reader opens the share view from the starred view's sidebar and clicks `« Back`
- **THEN** the starred view opens

#### Scenario: Back to the home page
- **WHEN** a reader opens the share view from the home page's sidebar, or directly at `/share`, and clicks `« Back`
- **THEN** the home page opens

#### Scenario: Empty share list
- **WHEN** a reader opens `/share` and the share list is empty
- **THEN** a message explains that posts are added with the share button on starred posts

### Requirement: Post text is generated once by AI
Adding a post SHALL start generating its LinkedIn text in the background with the configured AI provider and instructions. The request SHALL include the post's title, feed name, description and URL. A successful text SHALL be stored and SHALL NOT be generated again. While generation is running the entry SHALL show that it is in progress, and the view SHALL show the text once it is ready without a manual reload.

#### Scenario: Text appears
- **WHEN** a post is added and the AI provider returns a text
- **THEN** the share view shows that text in the post's text box

#### Scenario: No second generation
- **WHEN** a post already has a generated text and the share view is opened again or the post is added again
- **THEN** no new request is sent to the AI provider and the text is unchanged

#### Scenario: In progress
- **WHEN** the share view is open while a post's text is still being generated
- **THEN** the entry shows that the text is being generated and its text box is filled once generation finishes

### Requirement: Failed generation can be retried
When generation fails, for example because no AI provider is set up, the key is rejected or the provider cannot be reached, the entry SHALL show a short reason and a `Retry` button. Retry SHALL start generation again. Retry SHALL NOT be offered for an entry whose text was generated successfully.

#### Scenario: Provider not set up
- **WHEN** a post is added and no API key is saved
- **THEN** the entry shows that the AI provider must be set up in Settings → Social Media, and offers `Retry`

#### Scenario: Retry after fixing the key
- **WHEN** a reader saves a working API key and clicks `Retry` on a failed entry
- **THEN** the text is generated and shown

#### Scenario: Retry is refused for a generated text
- **WHEN** a retry is requested for an entry that already has a generated text
- **THEN** the request is rejected and the text is unchanged

### Requirement: Post text can be edited
The reader SHALL be able to change the text in the text box. Changes SHALL be saved on the server, so they are still shown after a reload and on other devices. A save failure SHALL be shown next to the text box.

#### Scenario: Edit and reload
- **WHEN** a reader changes a post's text, waits for it to be saved and reloads the share view
- **THEN** the changed text is shown

### Requirement: Text can be handed to LinkedIn
Each entry with a text SHALL have a `Share on LinkedIn` button. Clicking it SHALL copy the text box's current content to the clipboard and open LinkedIn's post composer in a new tab with that content filled in, so it can be pasted if LinkedIn does not fill it in.

#### Scenario: Share
- **WHEN** a reader clicks `Share on LinkedIn` on an entry
- **THEN** the text box content is on the clipboard and a new tab opens LinkedIn's composer with that text

#### Scenario: No text yet
- **WHEN** an entry's text is still being generated or failed
- **THEN** its `Share on LinkedIn` button is disabled

### Requirement: Posts can be removed from the share list
Each entry SHALL have a `Remove` button that deletes the entry and its text from the share list. Removing SHALL NOT unstar the post. A removed post SHALL be able to be added again, which generates a new text.

#### Scenario: Remove
- **WHEN** a reader clicks `Remove` on an entry
- **THEN** the entry disappears from the share view and stays gone after a reload, and the post is still starred

### Requirement: Share list survives a container restart
The share list, including generated and edited texts, SHALL be kept in the application's persistent storage, so it is unchanged after the container is recreated with the same storage volume.

#### Scenario: Restart
- **WHEN** posts are in the share list and the container is recreated with the same storage volume
- **THEN** the share view shows the same entries and texts

### Requirement: Share list changes are protected against cross-site requests
Requests that add, edit, retry or remove share list entries SHALL be rejected when they lack a valid CSRF token.

#### Scenario: Request without token
- **WHEN** a request to add a post arrives without a valid CSRF token
- **THEN** it is rejected and the share list is unchanged
