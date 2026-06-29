# FastHelp Laravel Package Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build `tabadev/laravel-fasthelp`, a distributable Laravel package providing a floating live-support widget with real-time client⇄agent chat, online presence, an AI first-line responder with human handoff, and a Filament v3 admin/agent UI — developed as a local path package consumed by the existing app as the test host.

**Architecture:** Package lives at `packages/fasthelp/` with its own `composer.json`, autoloaded under `Tabadev\FastHelp\`. The root Laravel app requires it via a Composer `path` repository (symlinked) and acts as the live test host. Realtime via Laravel Reverb + Echo (private + presence channels). Client widget and agent chat are Livewire 3 components; admin/agent management is a Filament v3 plugin. AI replies via a `SmartReply` contract with a `GeminiSmartReply` adapter ported (and simplified) from the existing `ChatbotGeminiService`.

**Tech Stack:** PHP 8.2+, Laravel 12, Livewire 3, Filament 3, Laravel Reverb + Echo, Guzzle (Gemini HTTP), Orchestra Testbench + Pest, Pint.

---

## Spec

Design doc: `docs/superpowers/specs/2026-06-29-fasthelp-laravel-package-design.md`

## Conventions for every task

- **TDD:** write the failing test first (Pest + Orchestra Testbench), watch it fail, implement minimally, watch it pass, then commit. Reference @superpowers:test-driven-development.
- **Run package tests from the package dir:** `cd packages/fasthelp && vendor/bin/pest` (the package has its own composer install for Testbench).
- **Run host-app smoke checks from repo root:** `php artisan ...`.
- **Style:** run `vendor/bin/pint packages/fasthelp` before each commit.
- **Commits:** small and frequent. Conventional commit messages.
- **Namespace:** all package PHP classes are under `Tabadev\FastHelp\`. Config key + publish tag + view namespace + translation namespace are all `fasthelp`.

---

## Task 0: Initialize git (prerequisite)

**Files:** repo root.

- [ ] **Step 1: Confirm repo is not yet versioned**

Run: `git rev-parse --is-inside-work-tree`
Expected: error / "not a git repository".

- [ ] **Step 2: Initialize and make a baseline commit**

```bash
git init
git add -A
git commit -m "chore: baseline before fasthelp package extraction"
```

Expected: a commit is created. (`.gitignore` already exists.)

---

## Task 1: Scaffold the package skeleton + path repository

**Files:**
- Create: `packages/fasthelp/composer.json`
- Create: `packages/fasthelp/src/FastHelpServiceProvider.php`
- Create: `packages/fasthelp/config/fasthelp.php`
- Modify: root `composer.json` (add `repositories` + require)

- [ ] **Step 1: Write the package `composer.json`**

```json
{
    "name": "tabadev/laravel-fasthelp",
    "description": "FastHelp — drop-in live-support widget with real-time agent chat, presence, AI first-line and a Filament admin for Laravel.",
    "type": "library",
    "license": "MIT",
    "keywords": ["laravel", "filament", "livewire", "support", "live-chat", "reverb"],
    "require": {
        "php": "^8.2",
        "illuminate/contracts": "^12.0",
        "illuminate/support": "^12.0",
        "livewire/livewire": "^3.0",
        "filament/filament": "^3.3",
        "guzzlehttp/guzzle": "^7.9"
    },
    "require-dev": {
        "orchestra/testbench": "^10.0",
        "pestphp/pest": "^3.0",
        "pestphp/pest-plugin-laravel": "^3.0",
        "laravel/pint": "^1.13"
    },
    "autoload": {
        "psr-4": {
            "Tabadev\\FastHelp\\": "src/",
            "Tabadev\\FastHelp\\Database\\Factories\\": "database/factories/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Tabadev\\FastHelp\\Tests\\": "tests/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "Tabadev\\FastHelp\\FastHelpServiceProvider"
            ]
        }
    },
    "config": {
        "allow-plugins": {
            "pestphp/pest-plugin": true
        },
        "sort-packages": true
    },
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

- [ ] **Step 2: Write a minimal service provider**

`packages/fasthelp/src/FastHelpServiceProvider.php`:

```php
<?php

namespace Tabadev\FastHelp;

use Illuminate\Support\ServiceProvider;

class FastHelpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/fasthelp.php', 'fasthelp');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/fasthelp.php' => config_path('fasthelp.php'),
        ], 'fasthelp-config');
    }
}
```

- [ ] **Step 3: Write the config stub**

`packages/fasthelp/config/fasthelp.php` (full config arrives in Task 9; start minimal):

```php
<?php

return [
    'user_model' => env('FASTHELP_USER_MODEL', \App\Models\User::class),
];
```

- [ ] **Step 4: Wire the path repository in the root app**

In root `composer.json`, add:

```json
"repositories": [
    { "type": "path", "url": "packages/fasthelp", "options": { "symlink": true } }
]
```

and add to `require`: `"tabadev/laravel-fasthelp": "*"`.

- [ ] **Step 5: Install and verify discovery**

Run: `composer update tabadev/laravel-fasthelp -W`
Then: `php artisan about`
Expected: no errors; package autoloads. Optionally `php artisan vendor:publish --tag=fasthelp-config` creates `config/fasthelp.php`.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat(fasthelp): scaffold package skeleton + path repository"
```

---

## Task 2: Package test harness (Testbench + Pest)

**Files:**
- Create: `packages/fasthelp/phpunit.xml`
- Create: `packages/fasthelp/tests/TestCase.php`
- Create: `packages/fasthelp/tests/Pest.php`
- Create: `packages/fasthelp/tests/Feature/ServiceProviderTest.php`
- Create: `packages/fasthelp/tests/database/` (sqlite scratch)

- [ ] **Step 1: Install dev deps inside the package**

Run: `cd packages/fasthelp && composer install`
Expected: Testbench + Pest installed under `packages/fasthelp/vendor`.

- [ ] **Step 2: Write the base TestCase**

`tests/TestCase.php`:

```php
<?php

namespace Tabadev\FastHelp\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Tabadev\FastHelp\FastHelpServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            \Livewire\LivewireServiceProvider::class,
            FastHelpServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
```

- [ ] **Step 3: Write `tests/Pest.php`**

```php
<?php

uses(Tabadev\FastHelp\Tests\TestCase::class)->in(__DIR__);
```

- [ ] **Step 4: Write `phpunit.xml`** (standard Pest/Testbench config with a `Feature` and `Unit` testsuite rooted at `tests/`).

- [ ] **Step 5: Write the failing provider test**

`tests/Feature/ServiceProviderTest.php`:

```php
<?php

it('merges the fasthelp config', function () {
    expect(config('fasthelp.user_model'))->not->toBeNull();
});
```

- [ ] **Step 6: Run it**

Run: `cd packages/fasthelp && vendor/bin/pest --filter=ServiceProviderTest`
Expected: PASS (config merge already implemented in Task 1).

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "test(fasthelp): add Testbench + Pest harness"
```

---

## Task 3: Migrations + Eloquent models

Build the four tables and models from the spec. One model per sub-step; TDD each model's casts/relationships.

**Files:**
- Create migrations under `packages/fasthelp/database/migrations/`:
  - `2026_06_29_000001_create_fasthelp_visitors_table.php`
  - `2026_06_29_000002_create_fasthelp_conversations_table.php`
  - `2026_06_29_000003_create_fasthelp_messages_table.php`
  - `2026_06_29_000004_create_fasthelp_agent_statuses_table.php`
- Create models under `packages/fasthelp/src/Models/`:
  - `Visitor.php`, `Conversation.php`, `Message.php`, `AgentStatus.php`
- Create enums under `packages/fasthelp/src/Enums/`:
  - `ConversationStatus.php` (`Open`,`Pending`,`Assigned`,`Resolved`)
  - `MessageSender.php` (`Client`,`Agent`,`Bot`,`System`)
  - `AgentPresence.php` (`Online`,`Away`,`Offline`)

- [ ] **Step 1: Write migrations** (columns per spec §5.2). Conversations use `uuid` (indexed, unique), nullable morph `client_type`/`client_id`, nullable `visitor_id`, `visitor_name`, `visitor_email`, `status` string default `open`, nullable `assigned_agent_id`, `current_url`, `meta` json nullable, `rating` unsignedTinyInteger nullable, `last_message_at` nullable timestamp. Do NOT add FK constraints to the host `users` table (host schema is unknown) — store `assigned_agent_id`/`user_id` as unsignedBigInteger + index only.

- [ ] **Step 2: Write enums** as PHP 8.1 backed string enums.

- [ ] **Step 3: Write the failing model test**

`tests/Feature/Models/ConversationTest.php`:

```php
<?php

use Tabadev\FastHelp\Models\Conversation;
use Tabadev\FastHelp\Models\Message;
use Tabadev\FastHelp\Enums\ConversationStatus;

it('creates a conversation with a uuid and casts status', function () {
    $c = Conversation::create(['status' => ConversationStatus::Open]);
    expect($c->uuid)->not->toBeNull()
        ->and($c->status)->toBe(ConversationStatus::Open);
});

it('has many messages ordered by creation', function () {
    $c = Conversation::create(['status' => ConversationStatus::Open]);
    $c->messages()->create(['sender_type' => 'client', 'body' => 'hi']);
    expect($c->messages)->toHaveCount(1);
});
```

- [ ] **Step 4: Run, expect FAIL** (models not implemented).

Run: `cd packages/fasthelp && vendor/bin/pest --filter=ConversationTest`
Expected: FAIL.

- [ ] **Step 5: Implement models.** `Conversation`: `$casts` for `status` → `ConversationStatus::class`, `meta` → array, `last_message_at` → datetime; boot hook to assign `(string) Str::uuid()` on creating; `messages()` hasMany ordered; `client()` morphTo; `visitor()` belongsTo. `Message`: casts `sender_type` → `MessageSender::class`, `attachments` → array, `read_at` → datetime; `conversation()` belongsTo. `Visitor` + `AgentStatus` per spec. Add `HasFactory` to all.

- [ ] **Step 6: Run, expect PASS.**

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint ../../packages/fasthelp
git add -A && git commit -m "feat(fasthelp): conversations, messages, visitors, agent statuses"
```

---

## Task 4: Model factories

**Files:** `packages/fasthelp/database/factories/{ConversationFactory,MessageFactory,VisitorFactory,AgentStatusFactory}.php`

- [ ] **Step 1:** Write factories returning valid default attributes. Map each model to its factory via `newFactory()` or the `Database\Factories` namespace convention.
- [ ] **Step 2:** Write `tests/Feature/Models/FactoryTest.php` asserting `Conversation::factory()->has(Message::factory()->count(3))->create()` persists 1 conversation + 3 messages.
- [ ] **Step 3:** Run → FAIL → implement → PASS.
- [ ] **Step 4:** Pint + commit: `test(fasthelp): model factories`.

---

## Task 5: Identity resolver (guest cookie + auth linkage)

**Files:**
- Create: `packages/fasthelp/src/Support/IdentityResolver.php`
- Create: `packages/fasthelp/src/Support/Identity.php` (small DTO: `type`, `visitor`, `user`)
- Test: `tests/Feature/IdentityResolverTest.php`

- [ ] **Step 1: Write failing tests** covering: (a) unauthenticated request with no cookie creates a `Visitor` and returns a signed cookie token; (b) same cookie token resolves the same visitor; (c) authenticated user resolves to a user-typed identity and links/backfills the prior guest visitor's `user_id`.

```php
<?php

use Tabadev\FastHelp\Support\IdentityResolver;
use Tabadev\FastHelp\Models\Visitor;

it('creates a guest visitor when no cookie present', function () {
    $identity = app(IdentityResolver::class)->resolve();
    expect($identity->isGuest())->toBeTrue()
        ->and(Visitor::count())->toBe(1);
});
```

- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3:** Implement `IdentityResolver` using `Request`, the `fasthelp` cookie name from config, `Auth` guard from `config('fasthelp.auth_guard')`. On auth, find-or-create visitor and set `user_id`. Queue the cookie via `Cookie::queue` when newly issued.
- [ ] **Step 4:** Run → PASS.
- [ ] **Step 5:** Pint + commit: `feat(fasthelp): identity resolver for guests and auth users`.

---

## Task 6: Broadcasting — channels + events

**Files:**
- Create: `packages/fasthelp/src/Events/{ConversationStarted,MessageSent,AgentPresenceChanged,ParticipantTyping}.php`
- Create: `packages/fasthelp/routes/channels.php`
- Modify: `FastHelpServiceProvider` to load channel routes
- Test: `tests/Feature/BroadcastingTest.php`

- [ ] **Step 1: Write failing tests** with `Event::fake()` asserting that creating a message dispatches `MessageSent` on the right channel, and that `MessageSent implements ShouldBroadcast` with `broadcastOn()` returning `PrivateChannel('fasthelp.conversation.{uuid}')`.
- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3:** Implement events (`ShouldBroadcast`, `broadcastWith()` payloads small + serializable). Define channels in `routes/channels.php`:
  - presence `fasthelp.agents` → authorize when the user passes the agent resolver (Task 8); return `['id','name']`.
  - private `fasthelp.conversation.{uuid}` → authorize agents always; authorize the owning client (match visitor cookie or auth user id).
  Load via `$this->loadRoutesFrom`/`Broadcast::routes()` + `require channels.php` in `boot()`.
- [ ] **Step 4:** Run → PASS.
- [ ] **Step 5:** Pint + commit: `feat(fasthelp): broadcast events and channel authorization`.

---

## Task 7: Conversation service (start, post message, AI handoff, resolve)

This is the domain core — keep it framework-light and fully unit-tested.

**Files:**
- Create: `packages/fasthelp/src/Services/ConversationService.php`
- Test: `tests/Feature/ConversationServiceTest.php`

- [ ] **Step 1: Write failing tests** for:
  - `start(Identity, ?string $url): Conversation` creates an `open` conversation tied to the identity, broadcasts `ConversationStarted`.
  - `postClientMessage(Conversation, string): Message` stores a `client` message, updates `last_message_at`, broadcasts `MessageSent`.
  - `postAgentMessage(Conversation, int $agentId, string): Message` stores an `agent` message and sets status `assigned` + `assigned_agent_id` if previously unassigned.
  - `requestHumanHandoff(Conversation)` sets status `pending` and broadcasts `ConversationStarted` to agents.
  - `resolve(Conversation, ?int $rating)` sets status `resolved`.
- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3:** Implement `ConversationService` with constructor-injected event dispatch. Use DB transactions for multi-write operations.
- [ ] **Step 4:** Run → PASS.
- [ ] **Step 5:** Pint + commit: `feat(fasthelp): conversation service`.

---

## Task 8: Agent eligibility + presence service

**Files:**
- Create: `packages/fasthelp/src/Support/AgentResolver.php` (resolves whether a host user may act as an agent, via `config('fasthelp.agents.resolver')` callback OR a pivot table fallback)
- Create: `packages/fasthelp/database/migrations/2026_06_29_000005_create_fasthelp_agents_table.php` (pivot: `user_id` unique — default eligibility store when no resolver configured)
- Create: `packages/fasthelp/src/Services/PresenceService.php` (`markOnline/markAway/markOffline(int $userId)`, `onlineAgents(): Collection`, broadcasts `AgentPresenceChanged`)
- Test: `tests/Feature/AgentResolverTest.php`, `tests/Feature/PresenceServiceTest.php`

- [ ] **Step 1:** Write failing tests: default resolver returns true only for users present in `fasthelp_agents`; a config closure overrides it; `PresenceService::markOnline` upserts an `AgentStatus` row and broadcasts; `onlineAgents()` returns only `online` within a freshness window.
- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3:** Implement. `AgentResolver::isAgent($user): bool`. `PresenceService` uses `AgentStatus` + `last_seen_at`.
- [ ] **Step 4:** Run → PASS.
- [ ] **Step 5:** Pint + commit: `feat(fasthelp): agent eligibility + presence service`.

---

## Task 9: Full config + publishing (assets, views, migrations, translations)

**Files:**
- Rewrite: `packages/fasthelp/config/fasthelp.php` (full schema, spec §6)
- Modify: `FastHelpServiceProvider::boot()` — publish groups + `loadMigrationsFrom` + `loadViewsFrom('fasthelp')` + `loadTranslationsFrom('fasthelp')` + `loadRoutesFrom`
- Create: `packages/fasthelp/resources/lang/en/fasthelp.php`
- Test: `tests/Feature/ConfigPublishingTest.php`

- [ ] **Step 1:** Write the full config: `user_model`, `auth_guard`, `cookie` name, `agents` (`resolver`), `widget` (`enabled`, `position`, `colors`, `title`, `launcher_icon`, `greeting`), `ai` (`enabled`, `driver`, `api_key` via `env('FASTHELP_GEMINI_KEY')`, `model`, `system_prompt`, `handoff_keywords`, `confidence_threshold`, `offline_behavior`), `broadcasting` (`channel_prefix`), `routes` (`prefix`, `middleware`), `presence` (`stale_after` seconds).
- [ ] **Step 2:** Write failing test asserting all top-level config keys exist and migrations are registered (`Schema::hasTable('fasthelp_conversations')` after `artisan migrate` in Testbench).
- [ ] **Step 3:** Run → FAIL → implement provider loaders + publish tags (`fasthelp-config`, `fasthelp-migrations`, `fasthelp-views`, `fasthelp-assets`, `fasthelp-translations`).
- [ ] **Step 4:** Run → PASS.
- [ ] **Step 5:** Pint + commit: `feat(fasthelp): full config + publishing groups`.

---

## Task 10: AI smart-reply (SmartReply contract + GeminiSmartReply)

Port and SIMPLIFY the existing `ChatbotGeminiService::generateResponse` (drop embeddings/RiveScript/KB; keep Guzzle call + system prompt + safety settings). The smart-reply decides whether to answer or hand off.

**Files:**
- Create: `packages/fasthelp/src/Contracts/SmartReply.php`
- Create: `packages/fasthelp/src/Services/Gemini/GeminiSmartReply.php`
- Create: `packages/fasthelp/src/Services/Gemini/SmartReplyResult.php` (DTO: `?string $reply`, `bool $shouldHandoff`)
- Modify: `FastHelpServiceProvider::register()` — bind `SmartReply` → configured driver
- Test: `tests/Feature/Ai/GeminiSmartReplyTest.php`

- [ ] **Step 1:** Write failing tests using a mocked Guzzle handler (`GuzzleHttp\Handler\MockHandler`) injected via the constructor: (a) a normal Gemini answer returns `reply` with `shouldHandoff=false`; (b) a message containing a configured handoff keyword returns `shouldHandoff=true` WITHOUT calling Gemini; (c) Gemini failure (exception) returns `reply=null, shouldHandoff=true`.
- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3:** Implement `SmartReply::reply(Conversation $c, string $message): SmartReplyResult`. `GeminiSmartReply` accepts an injectable Guzzle `Client` (default built from config base_uri) so tests can mock it. Build the request body from `config('fasthelp.ai.system_prompt')` + recent conversation messages. Apply `handoff_keywords` check first. Log errors via `Log` facade.
- [ ] **Step 4:** Run → PASS.
- [ ] **Step 5:** Pint + commit: `feat(fasthelp): Gemini smart-reply with handoff`.

- [ ] **Step 6:** Wire AI into `ConversationService::postClientMessage`: when conversation status is `open` and `config('fasthelp.ai.enabled')`, call `SmartReply`; if `shouldHandoff`, `requestHumanHandoff()`, else store a `bot` message. Extend the Task 7 tests for this branch (mock `SmartReply` binding). Commit: `feat(fasthelp): AI first-line in conversation flow`.

---

## Task 11: HTTP routes (guest token, polling fallback, upload)

**Files:**
- Create: `packages/fasthelp/routes/web.php`
- Create: `packages/fasthelp/src/Http/Controllers/WidgetController.php`
- Test: `tests/Feature/Http/WidgetRoutesTest.php`

- [ ] **Step 1:** Write failing feature tests for `GET {prefix}/conversation/{uuid}/messages` (polling fallback returns JSON messages, authorized by identity) and `POST {prefix}/conversation` (start) + `POST {prefix}/conversation/{uuid}/messages` (post client message). Prefix + middleware from config.
- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3:** Implement controller delegating to `ConversationService` + `IdentityResolver`. Register routes in `routes/web.php` loaded by the provider with `config('fasthelp.routes.prefix')` and middleware (`web`).
- [ ] **Step 4:** Run → PASS.
- [ ] **Step 5:** Pint + commit: `feat(fasthelp): widget HTTP routes (start, post, poll)`.

---

## Task 12: Livewire widget (client-facing)

**Files:**
- Create: `packages/fasthelp/src/Livewire/Widget.php`
- Create: `packages/fasthelp/resources/views/livewire/widget.blade.php`
- Create: `packages/fasthelp/resources/views/components/widget.blade.php` (the `<x-fasthelp::widget />` mount + launcher)
- Modify: `FastHelpServiceProvider::boot()` — `Livewire::component('fasthelp-widget', Widget::class)` + `Blade::component('fasthelp::widget', ...)` (or anonymous component via view namespace) + register a `@fastHelpWidget` Blade directive
- Test: `tests/Feature/Livewire/WidgetTest.php` (Livewire test helpers)

- [ ] **Step 1:** Write failing Livewire tests: mounting resolves/creates an identity; `sendMessage('hi')` adds a client message and (AI enabled, mocked) a bot reply appears; `requestHuman()` flips status to `pending`; the component listens on the Echo event name for `MessageSent`.
- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3:** Implement `Widget` Livewire component (state: `open`, `conversation`, `messages`, `body`, `onlineCount`). Methods: `startConversation`, `sendMessage`, `requestHuman`, `loadMessages`. Use `getListeners()` for `echo-private:fasthelp.conversation.{uuid},MessageSent`. Blade view: floating launcher button (position/colors/icon from config), chat panel, message list, input. Gate render on `config('fasthelp.widget.enabled')`.
- [ ] **Step 4:** Run → PASS.
- [ ] **Step 5:** Pint + commit: `feat(fasthelp): client live-chat widget (Livewire)`.

---

## Task 13: Frontend assets (Echo bootstrap) + widget embedding

**Files:**
- Create: `packages/fasthelp/resources/js/fasthelp.js` (Echo init guarded so it reuses host's `window.Echo` if present)
- Create: `packages/fasthelp/resources/css/fasthelp.css`
- Create: `packages/fasthelp/resources/views/assets.blade.php` (script/style include partial)
- Modify: provider to publish assets (`fasthelp-assets`) to `public/vendor/fasthelp`

- [ ] **Step 1:** Implement a small JS that ensures Laravel Echo (Reverb broadcaster) exists; if the host already configured Echo, do nothing. Document both paths in README (Task 17).
- [ ] **Step 2:** Provide a `@fastHelpWidget` directive that renders launcher + assets partial so a host adds one line to their layout.
- [ ] **Step 3:** Manual verify in host app (Task 16). No unit test (asset wiring); commit: `feat(fasthelp): publishable Echo assets + embed directive`.

---

## Task 14: Filament plugin — plugin class + Conversation resource + live agent chat

**Files:**
- Create: `packages/fasthelp/src/Filament/FastHelpPlugin.php`
- Create: `packages/fasthelp/src/Filament/Resources/ConversationResource.php` (+ `Pages/ListConversations.php`, `Pages/ViewConversation.php`)
- Create: `packages/fasthelp/src/Livewire/AgentChat.php` + `resources/views/livewire/agent-chat.blade.php` (embedded in ViewConversation, or a Filament custom page)
- Test: `tests/Feature/Filament/ConversationResourceTest.php`

- [ ] **Step 1:** Write failing tests (Livewire/Filament test helpers): the resource lists conversations with status/assignment columns + filters; `AgentChat` `send('hello')` posts an agent message, assigns the conversation, broadcasts `MessageSent`; presence toggle calls `PresenceService`.
- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3:** Implement `FastHelpPlugin implements \Filament\Contracts\Plugin` registering the resources. Implement `ConversationResource` (table: status badge, client, assigned agent, last_message_at; filters by status; actions resolve/assign-to-me). Implement `AgentChat` Livewire bound to the conversation channel with online roster from `PresenceService`.
- [ ] **Step 4:** Run → PASS.
- [ ] **Step 5:** Pint + commit: `feat(fasthelp): Filament plugin + conversation inbox + agent chat`.

---

## Task 15: Filament — Agents resource + Settings page

**Files:**
- Create: `packages/fasthelp/src/Filament/Resources/AgentResource.php` (manage `fasthelp_agents` eligibility over host users)
- Create: `packages/fasthelp/src/Filament/Pages/FastHelpSettings.php` + view (widget appearance, AI toggle/key/system prompt, offline message, handoff keywords) persisted to a `fasthelp_settings` table or cache-backed settings store
- Create: `packages/fasthelp/database/migrations/2026_06_29_000006_create_fasthelp_settings_table.php` (key/value json)
- Create: `packages/fasthelp/src/Support/Settings.php` (read-through: DB settings override config defaults)
- Test: `tests/Feature/Filament/SettingsTest.php`, `tests/Feature/SettingsStoreTest.php`

- [ ] **Step 1:** Write failing tests: `Settings::get('widget.title')` falls back to config when unset and returns the DB value when set; AgentResource can mark/unmark a user as agent.
- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3:** Implement settings store + both Filament screens. Update `Widget`/`GeminiSmartReply`/`PresenceService` to read via `Settings` (which falls back to config) instead of `config()` directly where runtime-editable.
- [ ] **Step 4:** Run → PASS.
- [ ] **Step 5:** Pint + commit: `feat(fasthelp): agents resource + settings page`.

---

## Task 16: Host-app integration smoke test (manual, in-repo)

**Files:** root app config/layout only (no package changes).

- [ ] **Step 1:** Publish + migrate in host: `php artisan vendor:publish --tag=fasthelp-config --tag=fasthelp-migrations` then `php artisan migrate`.
- [ ] **Step 2:** Register the Filament plugin in the host panel provider (document exact line). Add `@fastHelpWidget` to `resources/views/welcome.blade.php` (or the app layout).
- [ ] **Step 3:** Configure Reverb in host (`composer require laravel/reverb` already? if not, install), set `.env` broadcast vars, run `php artisan reverb:start` + `npm run dev`.
- [ ] **Step 4:** Manually verify (use @verify or /run): launcher shows on `/`; sending a message gets an AI reply; marking a user as agent in Filament; agent sees the conversation and replies in real time; presence online count updates. Capture a screenshot.
- [ ] **Step 5:** Commit any host wiring: `chore: wire fasthelp into test host app`.

---

## Task 17: Package README + final polish

**Files:**
- Create: `packages/fasthelp/README.md`
- Create: `packages/fasthelp/LICENSE`
- Create: `packages/fasthelp/.gitattributes` (export-ignore tests/docs for dist)

- [ ] **Step 1:** Write README: install, `vendor:publish` tags, migrate, Reverb setup, register Filament plugin, embed the widget directive, config reference, AI key setup, Packagist publish steps (push to a Git repo + submit on packagist.org, or `composer require` from VCS).
- [ ] **Step 2:** Run the full package suite: `cd packages/fasthelp && vendor/bin/pest` → all green. Run `vendor/bin/pint --test`.
- [ ] **Step 3:** Commit: `docs(fasthelp): package README + license + dist config`.

---

## Verification checklist (definition of done)

- [ ] `cd packages/fasthelp && vendor/bin/pest` — all tests pass.
- [ ] `vendor/bin/pint packages/fasthelp --test` — clean.
- [ ] Host app: widget renders, AI replies, handoff works, agent chat is real-time, presence updates.
- [ ] `composer require tabadev/laravel-fasthelp` resolves from the path repo with no errors.
- [ ] README documents a clean install in a fresh Laravel app.

## Notes / risks

- Reverb must be installed + running in the host; widget degrades to the polling routes (Task 11) when Echo is absent.
- Do not add DB foreign keys to host `users` (unknown schema) — index-only integer columns.
- Keep broadcast payloads minimal and serializable.
- Existing chatbot app code stays intact; only the Gemini *logic* is copied (simplified) into the package.
```
