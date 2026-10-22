<?php

namespace App\Http\Controllers;

use App\Config\AppSettings;
use App\Config\RssConfig;
use ErrorException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function __construct(
        protected AppSettings $settings,
    ) {
    }

    public function show(string $section = 'general')
    {
        return Inertia::render('Settings', [
            'section' => $section,
            'general' => [
                'theme' => $this->settings->theme(),
                'dark_background' => $this->settings->savedDarkBackground(),
                // What the dark background falls back to when no colour is saved
                'default_dark_background' => AppSettings::normalizeColor(config('app.dark_background')) ?? AppSettings::DEFAULT_DARK_BACKGROUND,
                'autoplay_enabled' => $this->settings->autoplayEnabled(),
                'autoplay_seconds' => $this->settings->autoplayInterval(),
            ],
            'autoplayIntervals' => AppSettings::AUTOPLAY_INTERVALS,
            'rows' => $this->feedRows(),
            // The saved API key is never sent to the browser, only whether there is one
            'ai' => [
                'provider' => $this->settings->aiProvider(),
                'endpoint' => $this->settings->savedAiEndpoint(),
                'model' => $this->settings->aiModel(),
                'has_api_key' => $this->settings->hasAiApiKey(),
                'system_prompt' => $this->settings->aiSystemPrompt(),
                'default_system_prompt' => AppSettings::DEFAULT_AI_SYSTEM_PROMPT,
                'fixed_endpoints' => AppSettings::AI_ENDPOINTS,
                'default_models' => AppSettings::AI_DEFAULT_MODELS,
            ],
        ]);
    }

    public function update(Request $request): Response
    {
        $data = $request->validate([
            'theme' => ['required', 'string', Rule::in(AppSettings::THEMES)],
            'dark_background' => ['nullable', 'string', 'regex:/^#([0-9a-fA-F]{3}){1,2}$/'],
            'autoplay_enabled' => ['required', 'boolean'],
            'autoplay_seconds' => ['required', 'integer', Rule::in(AppSettings::AUTOPLAY_INTERVALS)],
        ]);

        $this->settings->save([
            'theme' => $data['theme'],
            'dark_background' => isset($data['dark_background']) ? AppSettings::normalizeColor($data['dark_background']) : null,
            'autoplay_enabled' => $data['autoplay_enabled'] ? '1' : '0',
            'autoplay_seconds' => (string) $data['autoplay_seconds'],
        ]);

        return response()->noContent();
    }

    /**
     * Save the AI settings. An empty key keeps the saved key; a prompt equal to the default is not saved,
     * so a later change of the default applies.
     */
    public function updateAi(Request $request): Response
    {
        $data = $request->validate([
            'provider' => ['required', 'string', Rule::in(AppSettings::AI_PROVIDERS)],
            'endpoint' => ['nullable', 'required_if:provider,openai_compatible', 'string', 'max:2048', 'regex:/^https?:\/\/\S+$/i'],
            'model' => ['required', 'string', 'max:200'],
            'api_key' => ['nullable', 'string', 'max:1000'],
            'forget_api_key' => ['nullable', 'boolean'],
            'system_prompt' => ['nullable', 'string', 'max:10000'],
        ], [
            'endpoint.required_if' => 'The endpoint is required for an OpenAI compatible provider.',
            'endpoint.regex' => 'The endpoint must start with http:// or https:// and contain no spaces.',
        ]);

        $values = [
            'ai_provider' => $data['provider'],
            'ai_model' => trim($data['model']),
            'ai_system_prompt' => $this->customPrompt($data['system_prompt'] ?? null),
        ];
        if ($data['provider'] === 'openai_compatible') {
            $values['ai_endpoint'] = rtrim($data['endpoint'], '/');
        }

        $apiKey = trim($data['api_key'] ?? '');
        if ($apiKey !== '') {
            $values['ai_api_key'] = $apiKey;
        } elseif ($data['forget_api_key'] ?? false) {
            $values['ai_api_key'] = null;
        }

        $this->settings->save($values);

        return response()->noContent();
    }

    /**
     * Replace the feeds file with the given rows. Colour and hidden flag are not shown on the page,
     * the rows carry them so they are written back unchanged.
     */
    public function updateFeeds(Request $request): Response|JsonResponse
    {
        $tagsPerRow = [];
        $validator = Validator::make($request->all(), [
            'rows' => ['present', 'array', 'max:1000'],
            'rows.*' => ['array'],
            // The name is one word in the file: spaces become underscores, other whitespace and brackets can't be kept
            'rows.*.title' => ['required', 'string', 'max:250', 'not_regex:/[\[\]]|[^\S ]/'],
            'rows.*.tags' => ['required', 'string', 'max:1000'],
            'rows.*.url' => ['required', 'string', 'max:2048', 'regex:/^https?:\/\/\S+$/i'],
            'rows.*.site' => ['nullable', 'string', 'max:2048', 'regex:/^https?:\/\/\S+$/i'],
            'rows.*.color' => ['nullable', 'string', 'max:100', 'not_regex:/[\s\[\]]/'],
            'rows.*.hidden' => ['nullable', 'boolean'],
        ], [
            'rows.*.title.not_regex' => 'The title cannot contain [ or ].',
            'rows.*.url.regex' => 'The feed URL must start with http:// or https:// and contain no spaces.',
            'rows.*.site.regex' => 'The picture origin must start with http:// or https:// and contain no spaces.',
        ], [
            'rows.*.title' => 'title',
            'rows.*.tags' => 'tags',
            'rows.*.url' => 'feed URL',
            'rows.*.site' => 'picture origin',
        ]);

        $validator->after(function ($validator) use ($request, &$tagsPerRow) {
            $seenUrls = [];
            foreach ((array) $request->input('rows', []) as $i => $row) {
                $tags = $row['tags'] ?? null;
                if (is_string($tags) && trim($tags) !== '') {
                    $tagsPerRow[$i] = $this->parseTags($tags);
                    if ($tagsPerRow[$i] === null) {
                        $validator->errors()->add("rows.{$i}.tags", 'Tags cannot contain spaces or #. Separate several tags with commas.');
                    } elseif ($tagsPerRow[$i] === []) {
                        $validator->errors()->add("rows.{$i}.tags", 'The tags field is required.');
                    }
                }

                $url = $row['url'] ?? null;
                if (is_string($url) && $url !== '') {
                    if (isset($seenUrls[$url])) {
                        $validator->errors()->add("rows.{$i}.url", 'This feed is already listed.');
                    }
                    $seenUrls[$url] = true;
                }
            }
        });

        $rows = $validator->validate()['rows'];

        $config = new RssConfig();
        foreach ($rows as $i => $row) {
            $config->addFeed($row['url'], $row['title'], $tagsPerRow[$i], $row['color'] ?? '', (bool) ($row['hidden'] ?? false), $row['site'] ?? '');
        }

        $content = $rows ? $config->toString() . "\n" : '';
        if (!$this->writeConfigFile($content)) {
            return response()->json(['message' => 'The feeds file could not be written. Check that the application can write to it.'], 500);
        }

        return response()->noContent();
    }

    protected function customPrompt(?string $prompt): ?string
    {
        $prompt = trim((string) $prompt);
        return $prompt === '' || $prompt === AppSettings::DEFAULT_AI_SYSTEM_PROMPT ? null : $prompt;
    }

    /**
     * The configured feeds as rows for the feeds editor.
     * @return array<int, array{title: string, tags: string, url: string, site: string, color: string, hidden: bool}>
     */
    protected function feedRows(): array
    {
        $path = config('app.config_file');
        $config = new RssConfig();
        if ($path && is_file($path)) {
            $config->parseFromString((string) file_get_contents($path));
        }

        return array_map(fn (string $url) => [
            'title' => $config->getName($url),
            'tags' => implode(', ', array_map(fn (string $tag) => ltrim($tag, '#'), $config->getTags($url))),
            'url' => $url,
            'site' => $config->getSite($url),
            'color' => $config->getColor($url),
            'hidden' => $config->getHidden($url),
        ], $config->getFeedUrls());
    }

    /**
     * Split comma-separated tags into '#'-prefixed tags. Returns null when a tag contains whitespace or '#'.
     * @return string[]|null
     */
    protected function parseTags(string $input): ?array
    {
        $tags = [];
        foreach (explode(',', $input) as $tag) {
            $tag = trim($tag);
            if (str_starts_with($tag, '#')) {
                $tag = substr($tag, 1);
            }
            if ($tag === '') {
                continue;
            }
            if (preg_match('/[\s#]/', $tag)) {
                return null;
            }
            $tags[] = '#' . $tag;
        }

        return $tags;
    }

    /**
     * Write the feeds file in place, so a feeds file bind-mounted on its own keeps working.
     */
    protected function writeConfigFile(string $content): bool
    {
        $path = (string) config('app.config_file');
        $writable = is_file($path) ? is_writable($path) : ($path !== '' && is_writable(dirname($path)));
        if (!$writable) {
            return false;
        }

        try {
            return file_put_contents($path, $content, LOCK_EX) !== false;
        } catch (ErrorException) {
            return false;
        }
    }
}
