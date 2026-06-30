# KB-9: Knowledge-Base Admin Resource Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a Filament KnowledgePageResource with a "Scan website" header action, register it in FastHelpPlugin, and extend FastHelpSettings with KB toggle/config fields.

**Architecture:** Mirror ConversationResource/AgentResource conventions exactly — same namespace, same navigation group, same structural patterns. The scan action dispatches the existing CrawlSiteJob queue job; settings page gets a new KB Section wired into the existing dotted-key hydrate/persist loop.

**Tech Stack:** PHP 8.3, Filament v3.3, Pest (structural tests only — no HTTP render tests)

---

### Task 1: Write failing structural tests

**Files:**
- Create: `packages/fasthelp/tests/Feature/Filament/KnowledgePageResourceTest.php`

- [ ] **Step 1: Write the failing test file**

```php
<?php

use Tabadev\FastHelp\Filament\FastHelpPlugin;
use Tabadev\FastHelp\Filament\Resources\KnowledgePageResource;
use Tabadev\FastHelp\Models\KbPage;
use Tabadev\FastHelp\Support\Settings;

it('binds the KbPage model to the resource', function () {
    expect(KnowledgePageResource::getModel())->toBe(KbPage::class);
});

it('registers the index page', function () {
    expect(KnowledgePageResource::getPages())->toHaveKey('index');
});

it('exposes the expected navigation group and label', function () {
    expect(KnowledgePageResource::getNavigationLabel())->toBe('Knowledge Base')
        ->and(KnowledgePageResource::getNavigationGroup())->toBe('FastHelp');
});

it('instantiates the plugin without error', function () {
    $plugin = FastHelpPlugin::make();
    expect($plugin)->toBeInstanceOf(FastHelpPlugin::class)
        ->and($plugin->getId())->toBe('fasthelp');
});

it('round-trips a kb.enabled setting', function () {
    $settings = app(Settings::class);
    $settings->set('kb.enabled', true);
    expect($settings->get('kb.enabled'))->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

```
cd packages/fasthelp && vendor/bin/pest tests/Feature/Filament/KnowledgePageResourceTest.php
```
Expected: FAIL — class `KnowledgePageResource` not found.

---

### Task 2: Create KnowledgePageResource

**Files:**
- Create: `packages/fasthelp/src/Filament/Resources/KnowledgePageResource.php`

- [ ] **Step 3: Write the resource**

```php
<?php

namespace Tabadev\FastHelp\Filament\Resources;

use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Tabadev\FastHelp\Filament\Resources\KnowledgePageResource\Pages;
use Tabadev\FastHelp\Jobs\CrawlSiteJob;
use Tabadev\FastHelp\Models\KbPage;

class KnowledgePageResource extends Resource
{
    protected static ?string $model = KbPage::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'Knowledge Base';

    protected static ?string $navigationGroup = 'FastHelp';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('url')
                    ->limit(60)
                    ->url(fn (KbPage $record) => $record->url)
                    ->openUrlInNewTab(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'ok'      => 'success',
                        'failed'  => 'danger',
                        default   => 'gray',
                    }),
                Tables\Columns\TextColumn::make('indexed_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('indexed_at', 'desc')
            ->headerActions([
                Tables\Actions\Action::make('scan')
                    ->label('Scan website')
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->action(function (): void {
                        CrawlSiteJob::dispatch();
                        Notification::make()
                            ->title('Website scan queued.')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('reindex')
                    ->label('Re-index')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (KbPage $record): void {
                        CrawlSiteJob::dispatch($record->url, 1);
                        Notification::make()
                            ->title('Re-index queued.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListKnowledgePages::route('/'),
        ];
    }
}
```

---

### Task 3: Create ListKnowledgePages page

**Files:**
- Create: `packages/fasthelp/src/Filament/Resources/KnowledgePageResource/Pages/ListKnowledgePages.php`

- [ ] **Step 4: Write the page class**

```php
<?php

namespace Tabadev\FastHelp\Filament\Resources\KnowledgePageResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Tabadev\FastHelp\Filament\Resources\KnowledgePageResource;

class ListKnowledgePages extends ListRecords
{
    protected static string $resource = KnowledgePageResource::class;
}
```

---

### Task 4: Register resource in FastHelpPlugin

**Files:**
- Modify: `packages/fasthelp/src/Filament/FastHelpPlugin.php`

- [ ] **Step 5: Add KnowledgePageResource to resources() array**

Add `use Tabadev\FastHelp\Filament\Resources\KnowledgePageResource;` and add `KnowledgePageResource::class` to the `resources()` array.

---

### Task 5: Add KB section to FastHelpSettings

**Files:**
- Modify: `packages/fasthelp/src/Filament/Pages/FastHelpSettings.php`

- [ ] **Step 6: Add KB keys to $settingKeys and add Section to form()**

Append `'kb.enabled'`, `'kb.base_url'`, `'kb.max_pages'` to the `$settingKeys` list.

Add a new `Section::make('Knowledge base')` to the form schema with:
- `Toggle::make('kb.enabled')->label('Enabled')`
- `TextInput::make('kb.base_url')->label('Base URL')->url()`
- `TextInput::make('kb.max_pages')->label('Max pages')->numeric()`

---

### Task 6: Run full test suite and verify green

- [ ] **Step 7: Run all tests**

```
cd packages/fasthelp && vendor/bin/pest
```
Expected: all tests PASS.

---

### Task 7: Run pint and commit

- [ ] **Step 8: Run pint**

```
cd packages/fasthelp && vendor/bin/pint
```

- [ ] **Step 9: Commit**

```
git add packages/fasthelp/src/Filament/Resources/KnowledgePageResource.php \
    packages/fasthelp/src/Filament/Resources/KnowledgePageResource/Pages/ListKnowledgePages.php \
    packages/fasthelp/src/Filament/FastHelpPlugin.php \
    packages/fasthelp/src/Filament/Pages/FastHelpSettings.php \
    packages/fasthelp/tests/Feature/Filament/KnowledgePageResourceTest.php
git commit -m "feat(fasthelp): Filament knowledge-base admin + scan action

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```
