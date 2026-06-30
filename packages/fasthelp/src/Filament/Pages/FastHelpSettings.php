<?php

namespace Tabadev\FastHelp\Filament\Pages;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Arr;
use Tabadev\FastHelp\Support\Settings;

class FastHelpSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'FastHelp Settings';

    protected static ?string $navigationGroup = 'FastHelp';

    protected static string $view = 'fasthelp::filament.pages.settings';

    protected static ?string $slug = 'fasthelp-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    /**
     * The dotted setting keys managed by this page, in the order the form
     * fields are declared. Kept as a single list so mount()/save() stay in
     * lockstep with the schema below.
     *
     * Each form field is named after its dotted setting key (e.g.
     * `widget.title`). Filament/Livewire resolve dots in a field's name as
     * a nested-array path within the `data` statePath (via
     * Illuminate\Support\Arr::get/set), so `$this->form->getState()`
     * returns a NESTED array (`['widget' => ['title' => ...]]`), not a
     * flat array keyed by the dotted string. mount()/save() therefore use
     * Arr::get()/Arr::set() with these same dotted keys to read/write that
     * nested structure, which keeps the dotted *setting* keys (as stored
     * in `fasthelp_settings`) and the form field names identical.
     *
     * @var list<string>
     */
    protected static array $settingKeys = [
        'widget.title',
        'widget.greeting',
        'widget.position',
        'widget.colors.primary',
        'widget.enabled',
        'ai.enabled',
        'ai.model',
        'ai.system_prompt',
        'ai.handoff_keywords',
        'ai.offline_behavior',
        'kb.enabled',
        'kb.base_url',
        'kb.max_pages',
    ];

    public function mount(): void
    {
        $settings = app(Settings::class);

        $values = [];

        foreach (static::$settingKeys as $key) {
            Arr::set($values, $key, $settings->get($key));
        }

        $this->form->fill($values);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Section::make('Widget')
                    ->schema([
                        TextInput::make('widget.title')
                            ->label('Title'),
                        TextInput::make('widget.greeting')
                            ->label('Greeting'),
                        Select::make('widget.position')
                            ->label('Position')
                            ->options([
                                'bottom-right' => 'Bottom right',
                                'bottom-left' => 'Bottom left',
                            ]),
                        ColorPicker::make('widget.colors.primary')
                            ->label('Primary color'),
                        Toggle::make('widget.enabled')
                            ->label('Enabled'),
                    ]),
                Section::make('AI')
                    ->schema([
                        Toggle::make('ai.enabled')
                            ->label('Enabled'),
                        TextInput::make('ai.model')
                            ->label('Model'),
                        Textarea::make('ai.system_prompt')
                            ->label('System prompt')
                            ->rows(4),
                        TagsInput::make('ai.handoff_keywords')
                            ->label('Handoff keywords'),
                        Select::make('ai.offline_behavior')
                            ->label('Offline behavior')
                            ->options([
                                'ai_only' => 'AI only',
                                'capture_email' => 'Capture email',
                            ]),
                    ]),
                Section::make('Knowledge base')
                    ->schema([
                        Toggle::make('kb.enabled')
                            ->label('Enabled'),
                        TextInput::make('kb.base_url')
                            ->label('Base URL')
                            ->url(),
                        TextInput::make('kb.max_pages')
                            ->label('Max pages')
                            ->numeric(),
                    ]),
            ]);
    }

    public function save(): void
    {
        $values = $this->form->getState();

        $settings = app(Settings::class);

        foreach (static::$settingKeys as $key) {
            $settings->set($key, Arr::get($values, $key));
        }

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }
}
