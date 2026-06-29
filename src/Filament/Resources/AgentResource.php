<?php

namespace Tabadev\FastHelp\Filament\Resources;

use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Schema;
use Tabadev\FastHelp\Filament\Resources\AgentResource\Pages;
use Tabadev\FastHelp\Models\Agent;

class AgentResource extends Resource
{
    protected static ?string $model = Agent::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Agents';

    protected static ?string $navigationGroup = 'FastHelp';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('user_id')
                ->label('User')
                ->options(fn () => static::userOptions())
                ->searchable()
                ->required()
                ->unique(ignoreRecord: true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user_id')
                    ->label('User ID'),
                Tables\Columns\TextColumn::make('user_id')
                    ->label('User')
                    ->state(fn (Agent $record) => static::resolveUserLabel($record->user_id)),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAgents::route('/'),
            'create' => Pages\CreateAgent::route('/create'),
            'edit' => Pages\EditAgent::route('/{record}/edit'),
        ];
    }

    /**
     * Options for the user_id Select, sourced from the host application's
     * configured user model. Guarded defensively so the resource class
     * still loads (and the panel still boots) even if the users table
     * doesn't exist yet — the query only runs when the form is rendered.
     *
     * @return array<int|string, string>
     */
    protected static function userOptions(): array
    {
        $userModel = config('fasthelp.user_model');

        if (! $userModel || ! class_exists($userModel)) {
            return [];
        }

        try {
            $query = $userModel::query();

            $column = static::userLabelColumn($userModel);

            return $query->pluck($column, $query->getModel()->getKeyName())->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Best-effort lookup of a single user's display label, used in the
     * table column. Defensive against a missing/misconfigured user model
     * or a deleted user.
     */
    protected static function resolveUserLabel(mixed $userId): string
    {
        $userModel = config('fasthelp.user_model');

        if (! $userModel || ! class_exists($userModel)) {
            return (string) $userId;
        }

        try {
            $user = $userModel::query()->find($userId);

            if ($user === null) {
                return (string) $userId;
            }

            $column = static::userLabelColumn($userModel);

            return (string) ($user->{$column} ?? $userId);
        } catch (\Throwable) {
            return (string) $userId;
        }
    }

    /**
     * Prefer a `name` column for the label; fall back to `email`.
     */
    protected static function userLabelColumn(string $userModel): string
    {
        $table = (new $userModel)->getTable();

        return Schema::hasColumn($table, 'name') ? 'name' : 'email';
    }
}
