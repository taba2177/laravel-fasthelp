<?php

namespace Tabadev\FastHelp\Filament\Resources\AgentResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Tabadev\FastHelp\Filament\Resources\AgentResource;

class ListAgents extends ListRecords
{
    protected static string $resource = AgentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
