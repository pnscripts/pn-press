<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\InstallsPackages;
use App\Press\Packages\InvalidPackage;
use App\Press\Permissions;
use App\Press\Plugins\PluginManager;
use App\Press\Plugins\PluginManifest;
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
            ->values()
            ->all();

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
                ])
                ->all())
            ->columns([
                TextColumn::make('name')->weight('semibold')->description(fn (array $record) => $record['description'])->wrap(),
                TextColumn::make('version')->badge()->color('gray'),
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
