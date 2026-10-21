# Design

## Context

See proposal.md for motivation. Current state after `add-dark-background-env`:

- `app.blade.php` validates `APP_DARK_BACKGROUND` and, when it is valid, renders `style="--app-dark-bg: #rrggbb"` on `<html>`. The `<html>` class is `dark:bg-[var(--app-dark-bg,var(--color-gray-900))]`.
- `app.css` defines the custom `dark` variant (forced dark, or `system` plus `prefers-color-scheme: dark`).
- Every other dark surface hard-codes a Tailwind gray. This inventory (from `grep` of `resources/js`) is what this change replaces:

| Today | Role | Where |
|---|---|---|
| `gray-900` | page | `<html>`; search input `dark:bg-gray-900` (Posts.vue); flip edge, nav and progress buttons `dark:bg-gray-900/70`, `/80`, `/85` and `dark:hover:bg-gray-900`; toggle knob `dark:after:bg-gray-900` (FlipView.vue) |
| `gray-800` | surface | sidebar `dark:bg-gray-800` (Posts.vue); post hover `dark:hover:bg-gray-800` (Post.vue); active format button `dark:bg-gray-800` (FormatSelector.vue ×5); flip drawer `dark:bg-gray-800`, page-number buttons `dark:bg-gray-800` (FlipView.vue); tile frame `dark:bg-gray-800` (FlipTile.vue) |
| `gray-700` | raised | post hover border `dark:hover:border-gray-700` (Post.vue); sidebar `<hr>` `dark:border-gray-700`, active since filter `dark:bg-gray-700`, mobile toggle `dark:hover:bg-gray-700` (Posts.vue); flip close/number button hover `dark:hover:bg-gray-700` (FlipView.vue); tile fallback `dark:bg-gray-700` (FlipTile.vue) |
| `gray-600` | line | filter group `dark:border-gray-600`, `dark:divide-gray-600`, mobile toggle `dark:border-gray-600` (Posts.vue); FormatSelector border and divide; toggle track `dark:bg-gray-600` (FlipView.vue) |
| `gray-950` | deep | flip page `dark:bg-gray-950` (FlipView.vue) |

Not touched: `dark:border-black`, `dark:bg-white/40` (progress fill), `dark:peer-checked:bg-gray-300`, all `dark:text-*`, and feed colours.

## Goals / Non-Goals

**Goals:**
- One variable drives the whole dark palette. The default output stays identical when it is unset.
- Derivation lives in CSS, so the server still emits a single validated hex value.
- Components name roles (page, surface, raised, line, deep), not grays. A future palette change then touches only `app.css`.

**Non-Goals:**
- No automatic text-contrast adjustment. Text stays `gray-200` to `gray-500`, and choosing a dark enough colour is the operator's job (documented in the README).
- No light-theme customisation and no per-role environment variables.

## Decisions

### 1. Role tokens as Tailwind theme colours with a CSS-side switch
In `app.css`:

```css
@theme inline {
  --color-dark-page: var(--app-dark-page);
  --color-dark-surface: var(--app-dark-surface);
  --color-dark-raised: var(--app-dark-raised);
  --color-dark-line: var(--app-dark-line);
  --color-dark-deep: var(--app-dark-deep);
}
:root {
  --app-dark-page: var(--color-gray-900);
  --app-dark-surface: var(--color-gray-800);
  --app-dark-raised: var(--color-gray-700);
  --app-dark-line: var(--color-gray-600);
  --app-dark-deep: var(--color-gray-950);
}
:root[data-dark-bg] {
  --app-dark-page: var(--app-dark-bg);
  --app-dark-surface: color-mix(in oklab, var(--app-dark-bg), white 9%);
  --app-dark-raised: color-mix(in oklab, var(--app-dark-bg), white 21%);
  --app-dark-line: color-mix(in oklab, var(--app-dark-bg), white 30%);
  --app-dark-deep: color-mix(in oklab, var(--app-dark-bg), black 38%);
}
```

Components then use `dark:bg-dark-surface`, `dark:hover:border-dark-raised`, `dark:bg-dark-page/85`, and so on. `@theme inline` makes Tailwind emit the `var()` reference rather than the resolved value, so the opacity modifiers (`/70`, `/80`, `/85`) keep working through Tailwind's own `color-mix`. `gray-950` and `gray-600` must stay in the emitted theme. They are referenced through `var()` in the tokens, and Tailwind v4 emits theme variables that CSS references, which a task checks.

- Alternatives considered:
  - Arbitrary values in every class (`dark:bg-[var(--app-dark-surface,var(--color-gray-800))]`). Rejected because the classes are long, opacity modifiers become awkward, and the fallback chain is repeated about 25 times.
  - Computing the shades in PHP and emitting five custom properties. Rejected because it needs colour-space maths in PHP and more inline style, with no gain over CSS `color-mix`.
  - The relative colour syntax `oklch(from var(--app-dark-bg) calc(l + .07) c h)`. Rejected because Safari support for `calc()` on channel keywords landed later than `color-mix` (Safari 16.2). Wall-mounted iPads may run older Safari.

### 2. A `data-dark-bg` marker on `<html>` selects derived tokens
Blade renders `data-dark-bg` next to `style="--app-dark-bg: …"` only when the value is valid. Without the marker, the `:root` defaults are the exact Tailwind tokens, which satisfies the "unchanged default" requirement byte for byte. The `<html>` class becomes `dark:bg-dark-page`, replacing the arbitrary-value class from the last change.

- Alternative: the selector `:root[style*="--app-dark-bg"]`. Rejected as brittle string matching on an attribute.

### 3. Mix percentages approximate Tailwind's gray steps
The gray scale's oklch lightness goes 950 = .13, 900 = .21, 800 = .278, 700 = .373, 600 = .446. Mixing a colour of lightness L with white by p gives roughly L + p(1 − L). From L = .21 that gives p ≈ 9% (to 800), 21% (to 700) and 30% (to 600). Mixing with black by 38% gives roughly .13 (to 950). These percentages reproduce the default contrast steps for any dark base colour while keeping its hue.

## Risks / Trade-offs

- [Light configured colours make the light gray text hard to read, and white mixing pushes surfaces lighter still] → The README advises a dark colour. There is no runtime contrast check (non-goal).
- [Opacity modifiers on `var()`-based theme colours] → Covered by `@theme inline`. A task checks that the built CSS contains `color-mix(… var(--color-dark-page) 85% …)` or an equivalent for the `/85` class.
- [A missed hard-coded gray leaves one surface slate] → A task greps for any remaining `dark:(hover:|after:)?(bg|border|divide)-gray-(600|700|800|900|950)` in `resources/js` and the layout. The visual check covers hover and the flip view.
- [Existing instances change appearance on update] → This is intended (see proposal, BREAKING note). The README describes the new scope.

## Migration Plan

Deploy normally. Instances without `APP_DARK_BACKGROUND` see no change. Rollback means reverting the commit.

## Open Questions

None.
