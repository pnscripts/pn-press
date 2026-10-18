<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\InstallsPackages;
use App\Press\Packages\InvalidPackage;
use App\Press\Permissions;
use App\Press\Themes\ThemeManager;
use App\Press\Themes\ThemeManifest;
use App\Press\Updates\UpdateChecker;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class Themes extends Page implements HasTable
{
    use InstallsPackages;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-swatch';

    protected static string|UnitEnum|null $navigationGroup = 'Appearance';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'themes';

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->can(Permissions::MANAGE_THEMES);
    }

    protected function manager(): ThemeManager
    {
        return app(ThemeManager::class);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->uploadAction('theme', fn (string $zip, bool $replace) => $this->manager()->installZip($zip, $replace)),
        ];
    }

    public function content(Schema $schema): Schema
    {
        $errors = collect($this->manager()->errors())
            ->map(fn ($error, $folder) => Text::make("themes/{$folder}: {$error}")->color('danger'))
            ->values()
            ->all();

        return $schema->components([...$errors, EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        $active = $this->manager()->activeSlug();

        return $table
            ->records(fn () => collect($this->manager()->all())
                ->map(fn (ThemeManifest $theme) => [
                    'key' => $theme->slug,
                    'name' => $theme->name,
                    'description' => trim(($theme->parent ? 'Child theme of '.($this->manager()->parentOf($theme)?->name ?? $theme->parent).'. ' : '').($this->manager()->problem($theme) ?? '').' '.$theme->description),
                    'version' => $theme->version,
                    'author' => $theme->author,
                    'screenshot' => $theme->screenshot ? $this->manager()->assetUrl($theme->screenshot, $theme) : null,
                    'active' => $theme->slug === $active,
                    'has_settings' => $this->manager()->fields($theme) !== [],
                    'update' => app(UpdateChecker::class)->for('theme:'.$theme->slug),
                ])
                ->sortByDesc('active')
                ->all())
            ->columns([
                ImageColumn::make('screenshot')->label('')->imageWidth(160)->imageHeight(100),
                TextColumn::make('name')->weight('semibold')->description(fn (array $record) => $record['description'])->wrap(),
                TextColumn::make('version')->badge()->color('gray'),
                TextColumn::make('update')
                    ->label('Update')
                    ->badge()
                    ->color('warning')
                    ->state(fn (array $record) => $record['update'] ? $record['update']['version'].' available' : null)
                    ->url(fn (array $record) => $record['update']['url'] ?? null, shouldOpenInNewTab: true)
                    ->placeholder('—'),
                TextColumn::make('active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'Active' : 'Installed')
                    ->color(fn ($state) => $state ? 'success' : 'gray'),
            ])
            ->recordActions([
                Action::make('preview')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (array $record) => url('/').'?preview_theme='.$record['key'], shouldOpenInNewTab: true),
                Action::make('customize')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->visible(fn (array $record) => $record['has_settings'])
                    ->fillForm(fn (array $record) => $this->manager()->settingsFor($this->manager()->find($record['key'])))
                    ->schema(fn (array $record) => $this->settingsFields($this->manager()->find($record['key'])))
                    ->action(function (array $record, array $data): void {
                        $this->manager()->saveSettings($this->manager()->find($record['key']), $data);
                        Notification::make()->title('Theme settings saved')->success()->send();
                    }),
                Action::make('activate')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (array $record) => ! $record['active'])
                    ->action(fn (array $record) => $this->run(fn () => $this->manager()->activate($record['key']), "{$record['name']} is now the active theme")),
                Action::make('delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn (array $record) => ! $record['active'] && $record['key'] !== config('press.default_theme'))
                    ->requiresConfirmation()
                    ->modalDescription('The theme folder and its settings are deleted.')
                    ->action(fn (array $record) => $this->run(fn () => $this->manager()->uninstall($record['key']), "{$record['name']} deleted")),
            ])
            ->paginated(false);
    }

    /** @return array<int, mixed> */
    private function settingsFields(?ThemeManifest $theme): array
    {
        return collect($theme ? $this->manager()->fields($theme) : [])->map(fn (array $field) => match ($field['type']) {
            'color' => ColorPicker::make($field['key'])->label($field['label'])->regex('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/'),
            'select' => Select::make($field['key'])->label($field['label'])->options($field['options'])->selectablePlaceholder(false),
            'toggle' => Toggle::make($field['key'])->label($field['label']),
            default => TextInput::make($field['key'])->label($field['label'])->maxLength(500),
        })->all();
    }

    private function run(\Closure $operation, string $success): void
    {
        try {
            $operation();
            Notification::make()->title($success)->success()->send();
        } catch (InvalidPackage $e) {
            Notification::make()->title('Something is wrong with this theme')->body($e->getMessage())->danger()->send();
        }

        $this->redirect(static::getUrl());
    }
}
