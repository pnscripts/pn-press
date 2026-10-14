<?php

namespace App\Filament\Pages\Concerns;

use App\Press\Packages\InvalidPackage;
use App\Press\Packages\Manifest;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * The "Upload .zip" action shared by the Plugins and Themes pages.
 */
trait InstallsPackages
{
    /**
     * @param  Closure(string $zipPath, bool $replace): Manifest  $install
     */
    protected function uploadAction(string $kind, Closure $install): Action
    {
        return Action::make('upload')
            ->label('Upload '.$kind)
            ->icon('heroicon-o-arrow-up-tray')
            ->visible(fn () => (bool) config('press.uploads.enabled'))
            ->modalDescription("Upload a {$kind} .zip. It is checked (size, file types, paths) before anything is unpacked. Only install code you trust: a {$kind} runs with full access to this site.")
            ->schema([
                FileUpload::make('package')
                    ->label('.zip file')
                    ->disk('local')
                    ->directory('press-uploads')
                    ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed', 'application/octet-stream'])
                    ->maxSize((int) config('press.uploads.max_zip_kb'))
                    ->required(),
                Toggle::make('replace')->label("Replace the {$kind} if it is already installed (update)"),
            ])
            ->action(function (array $data) use ($install, $kind): void {
                $path = Storage::disk('local')->path($data['package']);

                try {
                    $manifest = $install($path, (bool) ($data['replace'] ?? false));
                    Notification::make()->title("{$manifest->name} {$manifest->version} installed")->success()->send();
                } catch (InvalidPackage $e) {
                    Notification::make()->title("The {$kind} was not installed")->body($e->getMessage())->danger()->persistent()->send();
                } finally {
                    Storage::disk('local')->delete($data['package']);
                }
            });
    }
}
