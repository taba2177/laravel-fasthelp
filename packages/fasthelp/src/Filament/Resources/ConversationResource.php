<?php

namespace Tabadev\FastHelp\Filament\Resources;

use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Tabadev\FastHelp\Enums\ConversationStatus;
use Tabadev\FastHelp\Filament\Resources\ConversationResource\Pages;
use Tabadev\FastHelp\Models\Conversation;

class ConversationResource extends Resource
{
    protected static ?string $model = Conversation::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Conversations';

    protected static ?string $navigationGroup = 'FastHelp';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (ConversationStatus $state): string => match ($state) {
                        ConversationStatus::Open => 'gray',
                        ConversationStatus::Pending => 'warning',
                        ConversationStatus::Assigned => 'info',
                        ConversationStatus::Resolved => 'success',
                    }),
                Tables\Columns\TextColumn::make('visitor_name')
                    ->label('Visitor')
                    ->default('Guest'),
                Tables\Columns\TextColumn::make('assigned_agent_id')
                    ->label('Agent'),
                Tables\Columns\TextColumn::make('last_message_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('last_message_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(array_combine(
                        array_map(fn (ConversationStatus $status) => $status->value, ConversationStatus::cases()),
                        array_map(fn (ConversationStatus $status) => ucfirst($status->value), ConversationStatus::cases()),
                    )),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListConversations::route('/'),
            'view' => Pages\ViewConversation::route('/{record}'),
        ];
    }
}
