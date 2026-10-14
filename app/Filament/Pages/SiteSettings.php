<?php

namespace App\Filament\Pages;

use App\Models\Page as CmsPage;
use App\Press\Permissions;
use App\Press\Settings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Settings → General and Reading. Plugins add sections through the "settings.sections" filter;
 * their field names should start with "plugin.<slug>.".
 *
 * @property-read Schema $form
 */
class SiteSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Settings';

    protected static ?string $title = 'Settings';

    protected static ?string $slug = 'settings';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->can(Permissions::MANAGE_SETTINGS);
    }

    public function mount(): void
    {
        $values = [];

        foreach (app(Settings::class)->all() as $key => $value) {
            if (preg_match('/^(site|reading|plugin)\./', $key)) {
                Arr::set($values, $key, $value);
            }
        }

        $this->form->fill($values);
    }

    public function form(Schema $schema): Schema
    {
        $sections = [
            Section::make('Site')->columns(2)->schema([
                TextInput::make('site.name')->label('Site name')->placeholder(config('app.name'))->maxLength(80),
                TextInput::make('site.tagline')->label('Tagline')->maxLength(160),
                Textarea::make('site.footer_text')->label('Footer text')->rows(2)->maxLength(400)->columnSpanFull(),
            ]),
            Section::make('Reading')->columns(2)->schema([
                Select::make('reading.home_page_id')
                    ->label('Home page')
                    ->options(fn () => CmsPage::query()->orderBy('title')->pluck('title', 'id'))
                    ->placeholder('Latest posts (the blog)')
                    ->searchable(),
                TextInput::make('reading.posts_per_page')->label('Posts per page')->numeric()->minValue(1)->maxValue(50)->placeholder('10'),
            ]),
        ];

        return $schema
            ->components(apply_filters('settings.sections', $sections))
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([Action::make('save')->label('Save settings')->submit('save')->keyBindings(['mod+s'])]),
                ]),
        ]);
    }

    public function save(): void
    {
        $values = $this->flatten($this->form->getState());

        app(Settings::class)->setMany($values);
        do_action('settings.saved', $values);

        Notification::make()->title('Settings saved')->success()->send();
    }

    /**
     * Nested form state to dotted setting keys. Lists (repeaters, multi-selects) stay values.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function flatten(array $state, string $prefix = ''): array
    {
        $out = [];

        foreach ($state as $key => $value) {
            $name = $prefix.$key;

            if (is_array($value) && ! array_is_list($value) && $value !== []) {
                $out += $this->flatten($value, $name.'.');
            } else {
                $out[$name] = $value === '' ? null : $value;
            }
        }

        return $out;
    }
}
