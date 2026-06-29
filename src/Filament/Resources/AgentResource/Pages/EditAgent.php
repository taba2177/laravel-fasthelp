<?php

namespace Tabadev\FastHelp\Filament\Resources\AgentResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Tabadev\FastHelp\Filament\Resources\AgentResource;

class EditAgent extends EditRecord
{
    protected static string $resource = AgentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
