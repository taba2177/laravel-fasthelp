<?php

namespace Tabadev\FastHelp\Filament\Resources\ConversationResource\Pages;

use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Tabadev\FastHelp\Filament\Resources\ConversationResource;

class ViewConversation extends Page
{
    use InteractsWithRecord;

    protected static string $resource = ConversationResource::class;

    protected static string $view = 'fasthelp::filament.pages.view-conversation';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $this->authorizeAccess();
    }

    protected function authorizeAccess(): void
    {
        abort_unless(static::getResource()::canView($this->getRecord()), 403);
    }

    public function getTitle(): string
    {
        return 'Conversation';
    }
}
