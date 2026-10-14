<?php

namespace App\Press\Plugins;

use App\Press\Packages\InvalidPackage;
use App\Press\Packages\Manifest;

/**
 * plugin.json:
 *
 *     {
 *       "slug": "contact-form",
 *       "name": "Contact Form",
 *       "version": "1.0.0",
 *       "description": "…",
 *       "author": "…",
 *       "requires": { "press": ">=1.0" },
 *       "provider": "PnPress\\ContactForm\\ContactFormServiceProvider",
 *       "autoload": { "psr-4": { "PnPress\\ContactForm\\": "src/" } },
 *       "migrations": "database/migrations",
 *       "views": "resources/views",
 *       "routes": "routes/web.php",
 *       "admin": "PnPress\\ContactForm\\ContactFormAdmin"
 *     }
 *
 * "routes" is a route file loaded with the "web" middleware (it is part of `php artisan route:cache`).
 * "admin" is a Filament plugin class (Filament\Contracts\Plugin) that adds resources, pages,
 * widgets or navigation items to the admin panel.
 */
class PluginManifest extends Manifest
{
    public readonly ?string $provider;

    /** @var array<string, string> namespace prefix => relative directory */
    public readonly array $psr4;

    public readonly string $migrations;

    public readonly string $views;

    public readonly string $routes;

    public readonly ?string $admin;

    public function __construct(string $path, array $data)
    {
        parent::__construct($path, $data);

        $provider = $data['provider'] ?? null;
        if ($provider !== null && (! is_string($provider) || ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)+$/', $provider))) {
            throw new InvalidPackage('"provider" must be a namespaced class name.');
        }

        $admin = $data['admin'] ?? null;
        if ($admin !== null && (! is_string($admin) || ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)+$/', $admin))) {
            throw new InvalidPackage('"admin" must be a namespaced class name.');
        }

        $psr4 = [];
        foreach ((array) ($data['autoload']['psr-4'] ?? []) as $namespace => $dir) {
            if (! is_string($namespace) || ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)*\\\\$/', $namespace)) {
                throw new InvalidPackage('autoload.psr-4 keys must be namespaces ending in a backslash.');
            }

            if (in_array(strtok($namespace, '\\'), ['App', 'Illuminate', 'Filament', 'Livewire', 'Database', 'Tests', 'Spatie'], true)) {
                throw new InvalidPackage("Plugins may not register the {$namespace} namespace.");
            }

            $psr4[$namespace] = $this->relativePath($dir, 'src');
        }

        $this->provider = $provider;
        $this->psr4 = $psr4;
        $this->migrations = $this->relativePath($data['migrations'] ?? null, 'database/migrations');
        $this->views = $this->relativePath($data['views'] ?? null, 'resources/views');
        $this->routes = $this->relativePath($data['routes'] ?? null, 'routes/web.php');
        $this->admin = $admin;
    }

    public static function filename(): string
    {
        return 'plugin.json';
    }
}
