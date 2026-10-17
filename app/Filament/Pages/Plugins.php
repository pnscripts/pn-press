<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\InstallsPackages;
use App\Press\Packages\InvalidPackage;
use App\Press\Permissions;
use App\Press\Plugins\PluginManager;
use App\Press\Plugins\PluginManifest;
use App\Press\Updates\UpdateChecker;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class Plugins extends Page implements HasTable
{
    use InstallsPackages;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static string|UnitEnum|null $navigationGroup = 'Extend';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'plugins';

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->can(Permissions::MANAGE_PLUGINS);
    }

    protected function manager(): PluginManager
    {
        return app(PluginManager::class);
    }

    /** Number of plugin updates (and a core update) waiting, shown next to "Plugins" in the menu. */
    public static function getNavigationBadge(): ?string
    {
        $count = count(array_filter(array_keys(app(UpdateChecker::class)->available()), fn (string $key) => ! str_starts_with($key, 'theme:')));

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Updates available';
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->uploadAction('plugin', fn (string $zip, bool $replace) => $this->manager()->installZip($zip, $replace)),
        ];
    }

    public function content(Schema $schema): Schema
    {
        $errors = collect($this->manager()->errors())
            ->map(fn ($error, $folder) => Text::make("plugins/{$folder}: {$error}")->color('danger'))
            ->merge(collect($this->manager()->bootProblems())->map(fn ($problem, $slug) => Text::make("{$slug}: {$problem}")->color('danger')))
            ->values()
            ->all();

        $core = app(UpdateChecker::class)->for('core');
        if ($core) {
            $errors[] = Text::make("PN Scripts Press {$core['version']} is available (this site runs {$core['installed']}).".($core['url'] ? " Release notes: {$core['url']}" : ''))->color('warning');
        }

        return $schema->components([...$errors, EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn () => collect($this->manager()->all())
                ->map(fn (PluginManifest $plugin) => [
                    'key' => $plugin->slug,
                    'name' => $plugin->name,
                    'description' => $plugin->description,
                    'version' => $plugin->version,
                    'author' => $plugin->author,
                    'active' => $this->manager()->isActive($plugin->slug),
                    'needs' => $this->manager()->unmetRequirements($plugin) ? 'Needs '.implode('; ', $this->manager()->unmetRequirements($plugin)) : null,
                    'requires' => $plugin->requiresPlugins ? 'Requires '.implode(', ', array_map(fn ($slug) => $this->manager()->find($slug)->name ?? $slug, array_keys($plugin->requiresPlugins))) : null,
                    'update' => app(UpdateChecker::class)->for('plugin:'.$plugin->slug),
                ])
                ->all())
            ->columns([
                TextColumn::make('name')->weight('semibold')
                    ->description(fn (array $record) => trim(implode(' ', array_filter([$record['description'], $record['requires'] ? $record['requires'].'.' : null, $record['active'] ? null : $record['needs']]))))
                    ->wrap(),
                TextColumn::make('version')->badge()->color('gray'),
                TextColumn::make('update')
                    ->label('Update')
                    ->badge()
                    ->color('warning')
                    ->state(fn (array $record) => $record['update'] ? $record['update']['version'].' available' : null)
                    ->url(fn (array $record) => $record['update']['url'] ?? null, shouldOpenInNewTab: true)
                    ->placeholder('—'),
                TextColumn::make('author')->placeholder('—'),
                TextColumn::make('active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'Active' : 'Inactive')
                    ->color(fn ($state) => $state ? 'success' : 'gray'),
            ])
            ->recordActions([
                Action::make('activate')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->visible(fn (array $record) => ! $record['active'])
                    ->action(fn (array $record) => $this->run(fn () => $this->manager()->activate($record['key']), "{$record['name']} activated")),
                Action::make('deactivate')
                    ->icon('heroicon-o-pause')
                    ->color('warning')
                    ->visible(fn (array $record) => $record['active'])
                    ->action(fn (array $record) => $this->run(fn () => $this->manager()->deactivate($record['key']), "{$record['name']} deactivated")),
                Action::make('uninstall')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('This rolls back the plugin\'s database tables (their data is lost), removes its settings and deletes its folder.')
                    ->action(fn (array $record) => $this->run(fn () => $this->manager()->uninstall($record['key']), "{$record['name']} uninstalled")),
            ])
            ->emptyStateHeading('No plugins installed')
            ->emptyStateDescription('Upload a plugin .zip or copy a plugin folder into plugins/.')
            ->paginated(false);
    }

    private function run(\Closure $operation, string $success): void
    {
        add_action('routes.cache_cleared', fn () => Notification::make()
            ->title('Route cache cleared')
            ->body('The active plugins changed, so the cached routes were out of date. Run php artisan route:cache to rebuild them.')
            ->warning()
            ->send());

        try {
            $operation();
            Notification::make()->title($success)->success()->send();
        } catch (InvalidPackage $e) {
            Notification::make()->title('Something is wrong with this plugin')->body($e->getMessage())->danger()->send();
        }

        // Activation changes navigation, blocks and routes: reload the page.
        $this->redirect(static::getUrl());
    }
}
