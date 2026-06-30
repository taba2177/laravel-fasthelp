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

    protected static ?string $modelLabel = 'Knowledge Page';

    protected static ?string $pluralModelLabel = 'Knowledge Pages';

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
                        'ok' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
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
