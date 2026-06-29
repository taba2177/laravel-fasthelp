# FastHelp — Laravel Live-Support Package — Design

- **Date:** 2026-06-29
- **Package:** `tabadev/laravel-fasthelp`
- **Namespace:** `Tabadev\FastHelp\`
- **Status:** Approved (design phase)

## 1. Summary

Convert the existing `chatbot-service` Laravel application into a distributable,
Packagist-ready Laravel package named **FastHelp**. FastHelp adds a floating help
widget to any Laravel application, enabling live conversations between site
clients (guests or authenticated users) and support "helper" employees (agents),
with real-time online presence, an AI first-line responder that hands off to a
human, and a Filament v3 admin/agent experience.

During development the package lives at `packages/fasthelp/` inside this repo and
is consumed by the existing Laravel app (the **test host**) via a Composer `path`
repository. The existing chatbot application code is left intact; the Gemini AI
logic is **copied** into the package, not deleted.

## 2. Goals

- Drop-in package: `composer require tabadev/laravel-fasthelp`, publish config,
  migrate, add a Filament plugin line, render the widget — done.
- Floating launcher icon on any page (guests + authenticated users).
- Real-time client ⇄ agent conversations (Reverb + Laravel Echo).
- Live agent presence ("who's online") and typing indicators.
- AI first-line responder (Gemini) with handoff to a human agent.
- Filament v3 plugin: conversation inbox + live chat, agent management, settings.
- Local path-package layout that is ready to publish to Packagist later.

## 3. Non-Goals (v1)

- No JS SPA widget (Vue/React). Widget is Livewire + Blade.
- No multi-channel ingestion (WhatsApp/email importers) — out of scope for v1.
- Deferred smart features: CSAT rating, AI conversation summaries, tags,
  analytics dashboard.

## 4. Key Decisions (confirmed)

| Area              | Decision |
|-------------------|----------|
| Realtime          | Laravel Reverb + Echo (WebSockets) |
| AI chatbot        | Keep as smart first-line responder with human handoff (reuse Gemini) |
| Client identity   | Both anonymous guests (cookie token) and authenticated host users |
| Admin/agent UI    | Filament v3 plugin (hard dependency) |
| Package name      | `tabadev/laravel-fasthelp`, namespace `Tabadev\FastHelp\` |
| Local layout      | `packages/fasthelp/` + existing app as test host (path repository) |
| Implementation    | Livewire 3 components (widget + agent chat) |

## 5. Architecture

### 5.1 Package layout

```
packages/fasthelp/
  composer.json                      # tabadev/laravel-fasthelp
  config/fasthelp.php
  database/migrations/
  resources/
    views/                           # widget + blade components
    js/  css/                        # Echo client glue, widget assets
  routes/web.php                     # guest token, upload, polling fallback
  src/
    FastHelpServiceProvider.php
    Filament/
      FastHelpPlugin.php
      Resources/ConversationResource.php (+ Pages)
      Resources/AgentResource.php
      Pages/FastHelpSettings.php
    Livewire/
      Widget.php
      AgentChat.php
    Models/
      Conversation.php
      Message.php
      Visitor.php
      AgentStatus.php
    Events/
      MessageSent.php
      ConversationStarted.php
      AgentPresenceChanged.php
      ParticipantTyping.php
    Contracts/SmartReply.php
    Services/Gemini/GeminiSmartReply.php   # ported from ChatbotGeminiService
    Support/IdentityResolver.php
  tests/                             # Orchestra Testbench + Pest
```

The repo root remains a working Laravel app and is the test host. It references
the package via a `path` repository in the root `composer.json`:

```json
"repositories": [
  { "type": "path", "url": "packages/fasthelp", "options": { "symlink": true } }
]
```

### 5.2 Data model

`fasthelp_conversations`
- `id`, `uuid`
- `client_type`, `client_id` (nullable morph → host User when authenticated)
- `visitor_id` (nullable FK → fasthelp_visitors)
- `visitor_name`, `visitor_email` (captured for guests/offline)
- `status` enum: `open` | `pending` | `assigned` | `resolved`
- `assigned_agent_id` (nullable FK → host User)
- `current_url`, `meta` (json), `rating` (nullable)
- `last_message_at`, timestamps

`fasthelp_messages`
- `id`, `conversation_id`
- `sender_type` enum: `client` | `agent` | `bot` | `system`
- `sender_id` (nullable; host User id for agent, null for bot/guest)
- `body` (text), `attachments` (json), `read_at` (nullable), timestamps

`fasthelp_visitors`
- `id`, `token` (cookie, unique), `name`, `email`
- `user_id` (nullable FK → host User, set on login linkage)
- `last_seen_at`, `meta` (json), timestamps

`fasthelp_agent_statuses`
- `id`, `user_id` (FK → host User, unique)
- `status` enum: `online` | `away` | `offline`
- `last_seen_at`, timestamps

**Agent eligibility** is resolved via config: `config('fasthelp.user_model')`
plus a configurable resolver (a flag/column or a pivot, or a callback returning
whether a given user may act as an agent). Managed through the Filament Agents
resource.

### 5.3 Realtime channels & events

- Presence channel `fasthelp.agents` — agent online roster ("who's online").
- Private channel `fasthelp.conversation.{uuid}` — messages + typing indicators.
- Events:
  - `ConversationStarted` → broadcast to agents (new pending conversation).
  - `MessageSent` → broadcast to the conversation channel.
  - `AgentPresenceChanged` → broadcast to widget + agents (online count).
  - `ParticipantTyping` → broadcast to the conversation channel.

A polling fallback route exists for hosts without a running WebSocket server.

### 5.4 Identity resolution

`Support/IdentityResolver` resolves the current actor:
- Authenticated host user → linked `client`. Backfills/links any prior guest
  visitor row by cookie token.
- Guest → `fasthelp_visitors` row keyed by a signed cookie token.

### 5.5 Client flow

1. Floating launcher renders on every page via a Blade component
   `<x-fasthelp::widget />` (or an auto-inject Blade directive / published layout
   include), gated by `config('fasthelp.widget.enabled')`.
2. Client opens the panel → Livewire `Widget` component starts/continues a
   conversation; guest receives a cookie token.
3. AI (`GeminiSmartReply`) answers the first line. On a handoff keyword,
   "talk to a human," or AI low-confidence, the conversation becomes `pending`
   and online agents are notified.
4. If no agent is online: AI-only mode or offline email capture, per config.

### 5.6 Agent flow

- Filament `ConversationResource`: inbox list (filter by status/assignment) and a
  live chat page (Livewire `AgentChat`) bound to the conversation channel.
- Presence toggle (online / away / offline) updates `fasthelp_agent_statuses`
  and broadcasts `AgentPresenceChanged`.
- Assignment, status changes, and canned/quick replies.

### 5.7 Filament plugin

`FastHelpPlugin implements Plugin` registers:
- `ConversationResource` (inbox + live chat page).
- `AgentResource` (mark host users as agents).
- `FastHelpSettings` page (widget appearance, AI on/off + key + system prompt,
  offline message, handoff keywords).

Registered by the host in its panel provider: `->plugin(FastHelpPlugin::make())`.

### 5.8 AI smart-reply

- `Contracts\SmartReply` interface: `reply(Conversation $c, string $message): SmartReplyResult`.
- `Services\Gemini\GeminiSmartReply` adapter ported from `ChatbotGeminiService`.
- Configurable system prompt, handoff keywords, confidence threshold, and
  offline behavior. Bound in the container so hosts can swap implementations.

## 6. Configuration (`config/fasthelp.php`)

- `user_model` — host User model class.
- `agent_resolver` — how to determine agent eligibility.
- `widget` — `enabled`, `position`, `colors`, `title`, `launcher_icon`,
  `greeting`.
- `ai` — `enabled`, `driver`, `api_key`, `system_prompt`, `handoff_keywords`,
  `offline_behavior` (`ai_only` | `capture_email`).
- `broadcasting` — channel prefix.
- `routes` — prefix, middleware.

## 7. Smart features

**v1:** AI first-line + human handoff · agent presence ("who's online") ·
typing indicators · canned/quick replies · offline email capture · conversation
status & assignment · visitor page-context (current URL/referrer) · unread
badges.

**Deferred:** CSAT rating · AI conversation summary for agents · conversation
tags · analytics dashboard.

## 8. Testing

- Orchestra Testbench + Pest package tests:
  - conversation lifecycle (start → message → handoff → resolve)
  - identity resolution (guest cookie, auth linkage)
  - AI handoff with Gemini mocked
  - presence + broadcast events asserted
- The in-repo host app is the manual/live test harness with Reverb running.

## 9. Migration / transition plan

1. Scaffold `packages/fasthelp/` with composer.json, service provider, config.
2. Wire the path repository in the root app and `composer require` the package.
3. Build models + migrations, identity resolver, events, channels.
4. Port Gemini logic into `GeminiSmartReply`.
5. Build Livewire widget + agent chat; wire Reverb/Echo.
6. Build Filament plugin (resources + settings).
7. Tests (Testbench/Pest) + manual verification in the host app.
8. Existing chatbot app code remains intact throughout.

## 10. Risks / open questions

- Reverb must be installed/configured in the host; provide clear docs + a polling
  fallback so the widget degrades gracefully.
- Filament v3 hard dependency limits hosts without Filament; accepted per design.
- Agent-eligibility model must stay decoupled from any specific host schema
  (resolved via config/callback).
```
