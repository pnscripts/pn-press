<?php

namespace App\Press\Plugins;

use App\Models\Plugin;
use App\Press\Hooks;
use App\Press\Packages\InvalidPackage;
use App\Press\Packages\PackageInstaller;
use App\Press\Settings;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * Discovers, installs, activates, deactivates and uninstalls plugins.
 *
 * A plugin is a folder in config('press.plugins_path') with a plugin.json. The `plugins`
 * table only records which ones are active. Active plugins are loaded on every request:
 * PSR-4 autoload, service provider, views (namespace = slug) and migrations path.
 *
 * Lifecycle hooks: plugin.loaded, plugin.activated, plugin.deactivated, plugin.uninstalling.
 */
class PluginManager
{
    /** @var array<string, PluginManifest>|null */
    private ?array $manifests = null;

    /** @var array<string, string> folder => error */
    private array $errors = [];

    /** @var array<string, true> */
    private array $loaded = [];

    /** @var array<string, bool>|null */
    private ?array $states = null;

    public function __construct(
        private readonly Application $app,
        private readonly Filesystem $files,
        private readonly Hooks $hooks,
        private readonly PackageInstaller $installer,
    ) {}

    public function path(): string
    {
        return rtrim((string) config('press.plugins_path'), '/');
    }

    /** @return array<string, PluginManifest> */
    public function all(): array
    {
        if ($this->manifests !== null) {
            return $this->manifests;
        }

        $this->manifests = [];
        $this->errors = [];

        foreach (glob($this->path().'/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $folder = basename($dir);

            if (str_contains($folder, '.old-')) {
                continue;
            }

            try {
                $manifest = PluginManifest::fromDirectory($dir);

                if ($manifest->slug !== $folder) {
                    throw new InvalidPackage("The folder name must match the slug \"{$manifest->slug}\".");
                }

                $this->manifests[$manifest->slug] = $manifest;
            } catch (InvalidPackage $e) {
                $this->errors[$folder] = $e->getMessage();
            }
        }

        ksort($this->manifests);

        return $this->manifests;
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        $this->all();

        return $this->errors;
    }

    public function find(string $slug): ?PluginManifest
    {
        return $this->all()[$slug] ?? null;
    }

    public function isActive(string $slug): bool
    {
        return $this->states()[$slug] ?? false;
    }

    /** Load every active plugin. Called once the application has booted. */
    public function bootActive(): void
    {
        foreach ($this->states() as $slug => $enabled) {
            if (! $enabled || ! ($manifest = $this->find($slug))) {
                continue;
            }

            try {
                $this->load($manifest);
            } catch (Throwable $e) {
                // A broken plugin must not take the site down: report it and carry on without it.
                report($e);
            }
        }
    }

    public function activate(string $slug): PluginManifest
    {
        $manifest = $this->find($slug) ?? throw new InvalidPackage("Plugin \"{$slug}\" is not installed.");

        if (! $manifest->isCompatible((string) config('press.version'))) {
            throw new InvalidPackage("{$manifest->name} needs PN Scripts Press {$manifest->requiresPress}.");
        }

        $this->load($manifest);
        $this->migrate($manifest);

        Plugin::query()->updateOrCreate(['slug' => $slug], [
            'name' => $manifest->name,
            'version' => $manifest->version,
            'enabled' => true,
            'activated_at' => now(),
        ]);
        $this->states = null;

        $this->hooks->doAction('plugin.activated', $manifest);

        return $manifest;
    }

    public function deactivate(string $slug): void
    {
        $manifest = $this->find($slug);

        Plugin::query()->where('slug', $slug)->update(['enabled' => false]);
        $this->states = null;

        if ($manifest) {
            $this->hooks->doAction('plugin.deactivated', $manifest);
        }
    }

    /**
     * Deactivate, roll back the plugin's migrations, forget its settings and delete its folder.
     */
    public function uninstall(string $slug): void
    {
        $manifest = $this->find($slug) ?? throw new InvalidPackage("Plugin \"{$slug}\" is not installed.");

        if ($this->isActive($slug)) {
            $this->deactivate($slug);
        }

        $this->hooks->doAction('plugin.uninstalling', $manifest);

        // Migration files may reference plugin classes.
        $this->registerAutoload($manifest);
        $migrations = $manifest->file($manifest->migrations);
        if (is_dir($migrations)) {
            Artisan::call('migrate:reset', ['--path' => [$migrations], '--realpath' => true, '--force' => true]);
        }

        Plugin::query()->where('slug', $slug)->delete();
        app(Settings::class)->forgetTree('plugin.'.$slug);

        $this->files->deleteDirectory($manifest->path);
        $this->manifests = null;
        $this->states = null;
    }

    public function installZip(string $zipPath, bool $replace = false): PluginManifest
    {
        /** @var PluginManifest $manifest */
        $manifest = $this->installer->installZip($zipPath, 'plugin', PluginManifest::class, $this->path(), $replace);
        $this->manifests = null;

        return $manifest;
    }

    public function installDirectory(string $directory, bool $replace = false): PluginManifest
    {
        /** @var PluginManifest $manifest */
        $manifest = $this->installer->installDirectory($directory, 'plugin', PluginManifest::class, $this->path(), $replace);
        $this->manifests = null;

        return $manifest;
    }

    public function load(PluginManifest $manifest): void
    {
        if (isset($this->loaded[$manifest->slug])) {
            return;
        }

        $this->registerAutoload($manifest);

        $views = $manifest->file($manifest->views);
        if (is_dir($views)) {
            View::addNamespace($manifest->slug, $views);
        }

        $migrations = $manifest->file($manifest->migrations);
        if (is_dir($migrations)) {
            $this->app->afterResolving('migrator', fn ($migrator) => $migrator->path($migrations));
            if ($this->app->resolved('migrator')) {
                $this->app->make('migrator')->path($migrations);
            }
        }

        if ($manifest->provider !== null) {
            if (! class_exists($manifest->provider) || ! is_subclass_of($manifest->provider, ServiceProvider::class)) {
                throw new InvalidPackage("{$manifest->name}: provider {$manifest->provider} was not found.");
            }

            $this->app->register($manifest->provider);
            $this->refreshRoutes();
        }

        $this->loaded[$manifest->slug] = true;
        $this->hooks->doAction('plugin.loaded', $manifest);
    }

    public function isLoaded(string $slug): bool
    {
        return isset($this->loaded[$slug]);
    }

    /** Routes added after the router was loaded need their name and action lookups rebuilt. */
    private function refreshRoutes(): void
    {
        $routes = $this->app->make('router')->getRoutes();

        if (method_exists($routes, 'refreshNameLookups')) {
            $routes->refreshNameLookups();
            $routes->refreshActionLookups();
        }
    }

    private function registerAutoload(PluginManifest $manifest): void
    {
        foreach ($manifest->psr4 as $prefix => $dir) {
            PluginAutoloader::add($prefix, $manifest->file($dir));
        }
    }

    private function migrate(PluginManifest $manifest): void
    {
        $migrations = $manifest->file($manifest->migrations);

        if (is_dir($migrations)) {
            Artisan::call('migrate', ['--path' => [$migrations], '--realpath' => true, '--force' => true]);
        }
    }

    /** @return array<string, bool> */
    private function states(): array
    {
        if ($this->states !== null) {
            return $this->states;
        }

        try {
            return $this->states = Plugin::query()->pluck('enabled', 'slug')->map(fn ($v) => (bool) $v)->all();
        } catch (Throwable) {
            return []; // Not migrated yet.
        }
    }
}
