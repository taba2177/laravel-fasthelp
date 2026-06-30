# FastHelp

A drop-in live-support widget for Laravel — real-time client⇄agent chat, online presence, an optional AI first-line responder, and a Filament admin, all installable as a single package.

## Features

- **Floating widget** — a single Blade directive (`@fastHelpWidget`) embeds a chat launcher in any host layout.
- **Live client⇄agent chat** — guests and authenticated users chat with human agents in real time.
- **Online presence** — visitors see how many agents are currently available; agents see a live roster.
- **AI first-line response with human handoff** — an optional Gemini-powered responder can answer first and hand off to a human on request or failure.
- **Filament admin** — an inbox (`ConversationResource`) with a live agent chat view, an `AgentResource` to manage who can answer chats, and a settings page for widget/AI behavior.
- **Guest + authenticated identity** — guests are tracked with a signed cookie; logged-in host users are linked automatically and keep their conversation across sessions.
- **Polished, RTL-aware UI** — modern self-styled chat with SVG icons (no build step required); the widget and agent chat mirror automatically for right-to-left pages (Arabic, Hebrew, …) based on the host page's `dir`/`lang`. Ships English + Arabic copy.

## Requirements

- PHP ^8.2
- Laravel ^12
- Livewire ^3
- Filament ^3.3
- Guzzle ^7.9 (used for the optional Gemini integration)

## Installation

1. Require the package:

   ```bash
   composer require tabadev/laravel-fasthelp
   ```

   The `Tabadev\FastHelp\FastHelpServiceProvider` is auto-discovered — no manual registration needed.

2. Publish the config:

   ```bash
   php artisan vendor:publish --tag=fasthelp-config
   ```

3. Publish and run the migrations:

   ```bash
   php artisan vendor:publish --tag=fasthelp-migrations
   php artisan migrate
   ```

4. Publish the front-end assets (CSS/JS served to the browser):

   ```bash
   php artisan vendor:publish --tag=fasthelp-assets
   ```

Other available tags, published on demand:

| Tag                      | What it publishes                                  | Destination                          |
|---------------------------|-----------------------------------------------------|---------------------------------------|
| `fasthelp-config`         | `config/fasthelp.php`                               | `config/fasthelp.php`                 |
| `fasthelp-migrations`     | Database migrations                                 | `database/migrations`                 |
| `fasthelp-views`          | Blade views                                         | `resources/views/vendor/fasthelp`     |
| `fasthelp-translations`   | Language files                                      | `lang/vendor/fasthelp`                |
| `fasthelp-assets`         | Compiled `fasthelp.css` / `fasthelp.js`             | `public/vendor/fasthelp`              |

## Filament setup

1. Your host `User` model must implement `Filament\Models\Contracts\FilamentUser` (required by Filament itself to access any panel).

2. Register the plugin on your Panel provider:

   ```php
   use Tabadev\FastHelp\Filament\FastHelpPlugin;

   public function panel(Panel $panel): Panel
   {
       return $panel
           // ...
           ->plugin(FastHelpPlugin::make());
   }
   ```

   This registers:
   - **ConversationResource** — the support inbox, including a live agent chat view per conversation.
   - **AgentResource** — manage which host users are agents.
   - **FastHelpSettings** page (slug `fasthelp-settings`) — edit widget and AI behavior at runtime.

3. Create an agent: open the **Agents** resource in the panel and select a host user, or insert a row into `fasthelp_agents` directly. Alternatively, skip the table entirely and supply your own eligibility check via `config('fasthelp.agents.resolver')` (see [Configuration reference](#configuration-reference)) — for example, gating on a role or permission you already have.

## Embedding the widget

Add the `@fastHelpWidget` Blade directive to your host layout, typically just before `</body>`:

```blade
    {{-- ... --}}
    @fastHelpWidget
</body>
```

This renders the `fasthelp-widget` Livewire component and the published `<link>`/`<script>` tags for the widget's CSS/JS. The widget can be disabled at runtime via `widget.enabled` (config or the Filament settings page) without removing the directive.

## Real-time setup

FastHelp broadcasts over three channels (prefix configurable, default `fasthelp`):

- `{prefix}.conversation.{uuid}` — private channel for messages and typing indicators in one conversation.
- `{prefix}.presence.agents` — presence channel for the live agent roster.
- `{prefix}.status` — public channel carrying the agent-availability count.

For live updates, install and configure **Laravel Reverb** and **Laravel Echo** in the host application (broadcaster, `BROADCAST_CONNECTION=reverb`, the `resources/js/echo.js` setup, and the `/broadcasting/auth` route, which the host app — not this package — is responsible for registering). FastHelp only registers the channel **authorization** callbacks (`routes/channels.php`); it does not call `Broadcast::routes()`.

**Without Reverb/Echo configured**, the widget keeps working via an HTTP polling fallback:

- `GET fasthelp/conversation/{uuid}/messages?after={id}` — fetch new messages since a given message ID.
- `GET fasthelp/status` — fetch the current agent-availability count.

Both endpoints are always registered (prefix configurable via `routes.prefix`), so you can ship without Reverb and add real-time later with no code changes.

## AI setup

FastHelp can let Gemini answer the first message in a conversation before any human is involved:

```env
FASTHELP_AI_ENABLED=true
FASTHELP_GEMINI_KEY=your-gemini-api-key
```

Behavior:

- Disabled by default (`FASTHELP_AI_ENABLED=false`) — nothing related to Gemini is called or validated at boot when disabled, and an unset `FASTHELP_GEMINI_KEY` never throws.
- When enabled, the AI answers first. It hands off to a human agent when:
  - the visitor's message contains a configured handoff keyword (default: `human`, `agent`, `representative`, `person`, `talk to someone`), or
  - the AI call fails or returns an empty response.
- The model, system prompt, and handoff keywords are editable at runtime from the Filament **FastHelp Settings** page, or via config/env at deploy time.

## Configuration reference

After publishing `config/fasthelp.php`:

| Key                              | Env var                          | Default                                   | Purpose                                                                 |
|-----------------------------------|-----------------------------------|--------------------------------------------|--------------------------------------------------------------------------|
| `user_model`                     | `FASTHELP_USER_MODEL`             | `App\Models\User`                          | The host's authenticatable model used for agents/auth clients.          |
| `auth_guard`                     | `FASTHELP_AUTH_GUARD`             | `null` (default guard)                     | Auth guard used to resolve the current user.                            |
| `cookie`                         | `FASTHELP_COOKIE`                 | `fasthelp_visitor`                         | Signed cookie name used to identify guest visitors.                     |
| `agents.resolver`                | —                                  | `null`                                     | Optional `fn(Authenticatable $user): bool` callable. `null` = use the `fasthelp_agents` table. |
| `presence.stale_after`           | `FASTHELP_PRESENCE_STALE_AFTER`   | `60`                                       | Seconds after which an agent's "online" status is considered stale.     |
| `broadcasting.channel_prefix`    | `FASTHELP_CHANNEL_PREFIX`         | `fasthelp`                                 | Prefix for all broadcast channel names.                                 |
| `widget.enabled`                 | `FASTHELP_WIDGET_ENABLED`         | `true`                                     | Toggle the floating widget on/off.                                      |
| `widget.position`                | `FASTHELP_WIDGET_POSITION`        | `bottom-right`                             | `bottom-right` or `bottom-left`.                                        |
| `widget.colors.primary`          | `FASTHELP_WIDGET_PRIMARY`         | `#4f46e5`                                  | Widget primary color.                                                   |
| `widget.colors.on_primary`       | `FASTHELP_WIDGET_ON_PRIMARY`      | `#ffffff`                                  | Text/icon color on top of the primary color.                            |
| `widget.title`                   | `FASTHELP_WIDGET_TITLE`           | `Need help?`                               | Widget header title.                                                    |
| `widget.launcher_icon`           | `FASTHELP_WIDGET_ICON`            | `heroicon-o-chat-bubble-left-right`        | Launcher button icon.                                                   |
| `widget.greeting`                | `FASTHELP_WIDGET_GREETING`        | `Hi! How can we help you today?`           | First message shown to visitors.                                        |
| `ai.enabled`                     | `FASTHELP_AI_ENABLED`             | `false`                                    | Enable the Gemini first-line responder.                                 |
| `ai.driver`                      | `FASTHELP_AI_DRIVER`              | `gemini`                                   | AI driver identifier (currently only `gemini` is implemented).          |
| `ai.api_key`                     | `FASTHELP_GEMINI_KEY`             | `null`                                     | Gemini API key. Never resolved/validated at boot.                       |
| `ai.model`                       | `FASTHELP_GEMINI_MODEL`           | `gemini-1.5-flash`                         | Gemini model name.                                                      |
| `ai.system_prompt`               | `FASTHELP_AI_SYSTEM_PROMPT`       | a default support-assistant prompt         | System prompt prepended to every AI request.                            |
| `ai.handoff_keywords`            | —                                  | `human, agent, representative, person, talk to someone` | Keywords in a visitor message that force a human handoff.    |
| `ai.confidence_threshold`        | `FASTHELP_AI_CONFIDENCE`          | `0.5`                                      | Reserved confidence threshold for future driver use.                    |
| `ai.offline_behavior`            | `FASTHELP_AI_OFFLINE_BEHAVIOR`    | `capture_email`                            | `ai_only` or `capture_email` — behavior when no agent is online.        |
| `routes.prefix`                  | `FASTHELP_ROUTE_PREFIX`           | `fasthelp`                                 | URL prefix for the widget's HTTP endpoints.                             |
| `routes.middleware`              | —                                  | `['web']`                                  | Middleware applied to the widget's HTTP routes.                         |

`widget.*` and `ai.*` keys (except `ai.api_key`/`ai.driver`) are also editable at runtime from the Filament **FastHelp Settings** page; those overrides are stored in the `fasthelp_settings` table and take precedence over the config file.

## Local development / contributing

```bash
cd packages/fasthelp
composer install
vendor/bin/pest
```

Code style is enforced with Laravel Pint:

```bash
vendor/bin/pint --test   # check
vendor/bin/pint          # fix
```

During local development inside this monorepo, the host application consumes the package via a Composer **path repository** rather than Packagist:

```json
{
    "repositories": [
        { "type": "path", "url": "packages/fasthelp", "options": { "symlink": true } }
    ],
    "require": {
        "tabadev/laravel-fasthelp": "@dev"
    }
}
```

This symlinks `vendor/tabadev/laravel-fasthelp` straight to `packages/fasthelp`, so changes are picked up immediately without re-running `composer update`.

## Publishing to Packagist

To make the package installable outside this monorepo:

1. Extract/push `packages/fasthelp` to its own Git repository (the `.gitattributes` in this directory already excludes dev-only files such as `tests/`, `phpunit.xml`, and `.gitignore` from `composer create-project`/archive exports).
2. Tag a release (e.g. `v1.0.0`) following semver.
3. Submit the repository at [packagist.org](https://packagist.org) under the name `tabadev/laravel-fasthelp`. Packagist will pick up new tags automatically via the GitHub webhook (or polling).
4. Consumers then simply run `composer require tabadev/laravel-fasthelp`.

Until it's published, any consumer (including this monorepo) can require it directly from its Git URL via a **VCS repository**:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/your-org/laravel-fasthelp" }
    ]
}
```

or, for a sibling checkout on disk, the **path repository** shown above.

## License

MIT. See [LICENSE](LICENSE).
