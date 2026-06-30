# FastHelp Knowledge Base + URL Guidance + SEO Previews (v0.2.0) — Plan

> Executed subagent-driven (fresh agent per task, spec + quality review each). TDD throughout. Package: `tabadev/laravel-fasthelp` at `packages/fasthelp/`, namespace `Tabadev\FastHelp\`. Tests: `cd packages/fasthelp && vendor/bin/pest`. Commit each task; no `vendor/`.

**Goal:** Make the AI helper "smart" about the host site: crawl the live website into a knowledge base, ground AI answers in it, let the AI guide clients with relevant URLs, and render those URLs as rich SEO preview cards in the chat.

**Design (approved):** Embeddings RAG (Gemini embeddings + cosine similarity in DB). Crawl via sitemap.xml + same-domain BFS with a max-pages cap, respecting robots/noindex; triggered by artisan command + a Filament "Scan website" action (queued), optionally scheduled. One crawl captures both page **text** (for RAG) and **SEO metadata** (title/description/og:image) — the latter doubles as link-preview data. Link previews: internal links use crawled metadata; external URLs are fetched (OpenGraph) on demand and cached. The AI writes natural replies containing relevant URLs (pulled into its prompt as context); the chat view detects URLs and renders SEO cards beneath the message.

**Tech:** PHP 8.2+, Laravel 12, Guzzle, Gemini (generative + embeddings), sqlite/MySQL, Livewire 3, Filament 3, Pest/Testbench.

---

## Task 1 — KB data model + config
- Migrations: `fasthelp_kb_pages` (url unique, title, description, og_image, content longText, content_hash, embedding longText/json nullable, status, indexed_at, timestamps), `fasthelp_link_previews` (url unique, title, description, image, fetched_at, timestamps).
- Models `KbPage`, `LinkPreview` (+ factories). Enum `KbStatus` (ok/failed/pending) optional.
- Config section `fasthelp.kb`: `enabled`, `base_url` (default `env('FASTHELP_KB_BASE_URL', config('app.url'))`), `max_pages` (default 100), `same_domain_only` (true), `respect_robots` (true), `embedding_model` (`text-embedding-004`), `retrieve_top_k` (4), `min_similarity` (0.65), `user_agent`, `schedule` (off/daily/weekly).
- TDD: model/migration tests. Commit `feat(fasthelp): knowledge-base data model + config`.

## Task 2 — Gemini embedder
- `Contracts/Embedder` (`embed(string $text): ?array`), `Services/Gemini/GeminiEmbedder` (port `generateEmbedding` from the old `ChatbotGeminiService`; model from `fasthelp.kb.embedding_model`; injectable Guzzle client; boot-safe; never throw → returns null on failure). Bind in provider. Helper `cosine(array,array): float` in a `Support/Vectors` util.
- TDD with mocked Guzzle (normal vector; failure → null) + cosine math test. Commit `feat(fasthelp): Gemini embedder + cosine util`.

## Task 3 — Site crawler
- `Services/Crawl/SiteCrawler`: discover URLs (parse `sitemap.xml` if reachable; else BFS same-domain links from `base_url`), cap at `max_pages`, skip non-HTML/binary, respect `<meta name="robots" content="noindex">` and (basic) robots.txt when `respect_robots`. For each page: fetch (Guzzle, configurable UA + timeout), extract `<title>`, meta description, `og:image`, and main text (strip script/style/nav/footer tags then strip remaining tags + collapse whitespace). Upsert `KbPage` by url; skip re-embedding when `content_hash` unchanged; otherwise call `Embedder` and store the vector. Mark status ok/failed. Return a summary (indexed/skipped/failed counts).
- Injectable HTTP client for tests. TDD with a `MockHandler` serving a tiny fake sitemap + 2 pages; assert pages stored with metadata + embedding (embedder mocked). Commit `feat(fasthelp): site crawler -> knowledge base`.

## Task 4 — Knowledge retriever
- `Services/Knowledge/KnowledgeRetriever::retrieve(string $query, ?int $k): Collection` — embed the query, cosine-compare to all `KbPage` embeddings, filter by `min_similarity`, return top-K (url, title, description, content excerpt, score). Degrades to empty collection when embedder/KB unavailable (never throws).
- TDD: seed KbPages with known vectors, stub embedder, assert ranking + threshold. Commit `feat(fasthelp): knowledge retriever (RAG)`.

## Task 5 — Ground AI replies in the KB (+ URL guidance)
- Update `GeminiSmartReply::reply()`: when `fasthelp.kb.enabled`, call `KnowledgeRetriever`, and if hits exist, prepend a context block to the prompt: each page as `Title — <url>\n<excerpt>`, plus an instruction: *answer using this site content; when a page helps, include its URL in the reply so the customer can go straight there.* Keep handoff-keyword short-circuit first. No KB hits → behave as today.
- TDD (mock retriever + Guzzle): assert the prompt sent to Gemini contains the page URLs/context; assert a normal answer still returns. Commit `feat(fasthelp): ground AI replies in site knowledge base`.

## Task 6 — Crawl job + artisan command + schedule
- `Jobs/CrawlSiteJob` (queued) wrapping `SiteCrawler`. `Console/ScanSiteCommand` (`fasthelp:scan {--url=} {--max=} {--sync}`) dispatches the job (or runs sync). Register command + optional scheduled run (per `fasthelp.kb.schedule`) in the provider.
- TDD: command dispatches job (Bus::fake); job runs crawler (crawler mocked/bound). Commit `feat(fasthelp): scan command + queued crawl job + schedule`.

## Task 7 — Link preview service
- `Services/Links/LinkPreviewService::for(string $url): ?array` — return `{url,title,description,image,internal}`: look up `KbPage` by url (internal) → else `LinkPreview` cache → else fetch the URL, parse OG/title/description/image, store in `LinkPreview`, return. Honor a short timeout; never throw (return null/bare). Injectable HTTP client. A `previewsFor(string $body): array` helper extracts URLs from a message body (regex) and maps to previews.
- TDD with mocked HTTP: internal hit from KbPage, external fetch+cache, malformed URL safe. Commit `feat(fasthelp): link preview service (internal + external OG)`.

## Task 8 — SEO cards in chat (widget + agent)
- In `Widget::loadMessages()` and `AgentChat::loadMessages()`, attach `previews` per message via `LinkPreviewService::previewsFor($body)` (cached, so cheap). 
- Update `widget.blade.php` and `agent-chat.blade.php`: linkify URLs in the body text and render a compact **SEO card** (image thumb, title, description, host) per preview beneath the bubble, RTL-aware, matching the existing styles. Cards open in a new tab (`rel="noopener"`).
- TDD: Livewire test asserting `messages[i]['previews']` is populated for a body containing a KB url (stub LinkPreviewService). Visual check in browser. Commit `feat(fasthelp): render SEO link preview cards in chat`.

## Task 9 — Filament knowledge admin
- `Filament/Resources/KnowledgePageResource` — list indexed pages (url, title, status, indexed_at), view content, delete; a header **"Scan website"** action dispatching `CrawlSiteJob` with a success notification; a per-row "re-index" action. Register in `FastHelpPlugin` under the FastHelp group. Add KB toggles to the Settings page (`kb.enabled`, `base_url`, `max_pages`).
- Structural tests (model/pages/plugin). Commit `feat(fasthelp): Filament knowledge-base admin + scan action`.

## Task 10 — Docs + publish v0.2.0
- README: new "Knowledge base & smart URL guidance" section (scan command, Filament action, schedule, RAG/embeddings note, link previews, config reference for `fasthelp.kb`). Bump notes. Full suite + `pint --test` green. Commit `docs(fasthelp): knowledge base docs`. Then subtree split → push `main` + tag **v0.2.0**.

## Cross-cutting constraints
- Boot-safe: nothing reads/validates the Gemini key at construct/register; embedder/retriever/crawler never throw (degrade gracefully).
- No FK to host `users`. Keep files focused. Injectable HTTP clients everywhere for tests.
- KB features are gated by `fasthelp.kb.enabled` (default false) so existing behavior/tests are unchanged when off.
