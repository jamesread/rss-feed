# Spec Delta

## Purpose

Lets an operator connect the installation to a generative AI provider and set the instructions (system prompt) used to draft share texts, from the settings page.

## ADDED Requirements

### Requirement: Provider choice sets the API endpoint
The Social Media menu SHALL offer the providers `OpenAI`, `Anthropic`, `OpenRouter` and `OpenAI compatible`. For the first three the endpoint SHALL be fixed and shown read-only. For `OpenAI compatible` an endpoint field SHALL be shown and SHALL be required. It SHALL accept an `http` or `https` URL.

#### Scenario: Fixed endpoints
- **WHEN** a reader selects `OpenAI`, `Anthropic` or `OpenRouter`
- **THEN** the endpoint shown is `https://api.openai.com/v1`, `https://api.anthropic.com/v1` or `https://openrouter.ai/api/v1` respectively, and it cannot be edited

#### Scenario: Compatible endpoint
- **WHEN** a reader selects `OpenAI compatible`
- **THEN** an editable endpoint field is shown, and saving with it empty or not an http(s) URL shows an error next to it and saves nothing

### Requirement: A model is required
The Social Media menu SHALL have a model field that is sent to the provider as the model name. Saving with an empty model SHALL show an error and save nothing.

#### Scenario: Empty model
- **WHEN** a reader clears the model field and saves
- **THEN** an error is shown next to the model field and the saved settings are unchanged

### Requirement: The API key is write-only
The Social Media menu SHALL have an API key field. A saved key SHALL never be sent to the browser. The menu SHALL show whether a key is saved. Saving with the key field empty SHALL keep the saved key. The menu SHALL offer a way to remove the saved key.

#### Scenario: Key not echoed
- **WHEN** a key is saved and a reader opens the Social Media menu
- **THEN** the page shows that a key is saved, the key field is empty, and the key does not appear anywhere in the page data

#### Scenario: Keep the key
- **WHEN** a key is saved and a reader changes only the model and saves
- **THEN** the saved key is unchanged

#### Scenario: Remove the key
- **WHEN** a reader chooses to remove the saved key and saves
- **THEN** no key is saved and the menu shows that no key is set

### Requirement: Instructions with a default
The Social Media menu SHALL have an `Instructions` text box. Every generation SHALL use its content as the system prompt. Until instructions are saved, the text box SHALL show the default instructions, which are also used. The menu SHALL offer a way to return to the default.

#### Scenario: Default instructions
- **WHEN** no instructions are saved and a reader opens the Social Media menu
- **THEN** the text box shows the default instructions, which ask for:
  - A friendly LinkedIn post of at most three paragraphs, each at most three sentences.
  - Modest emoji use and no typical AI wording.
  - No em dashes and no false contrasts such as "this is not about X, it is about Y".
  - 3 to 4 hashtags, with the article link on the last line.

#### Scenario: Custom prompt used
- **WHEN** a reader saves a custom system prompt and a post is added to the share list
- **THEN** the request to the AI provider carries the custom prompt as its system prompt

#### Scenario: Back to default
- **WHEN** a reader chooses to use the default prompt and saves
- **THEN** no custom prompt is saved and the default is shown and used

### Requirement: AI settings are saved on the server
Saving the Social Media menu SHALL store the provider, endpoint, model, key and system prompt in the application's persistent storage, so they apply on every device and survive a container restart. Invalid input SHALL be rejected with an error next to the field and nothing saved.

#### Scenario: Restart
- **WHEN** AI settings are saved and the container is recreated with the same storage volume
- **THEN** the Social Media menu shows the same provider, endpoint, model, prompt and that a key is saved

#### Scenario: Unknown provider
- **WHEN** a save request names a provider other than the four offered
- **THEN** it is rejected and the saved settings are unchanged
