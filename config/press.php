<?php

return [

    /*
    | PN Scripts Press engine settings.
    |
    | Plugins and themes are folders with a manifest (plugin.json / theme.json).
    | Uploading a .zip in the admin unpacks it into these folders after the
    | package passes the checks in App\Press\Packages\ZipValidator.
    */

    'version' => '1.0.0',

    'plugins_path' => env('PRESS_PLUGINS_PATH', base_path('plugins')),

    'themes_path' => env('PRESS_THEMES_PATH', base_path('themes')),

    'default_theme' => env('PRESS_DEFAULT_THEME', 'folio'),

    // Uploading packages from the admin. Remote downloads are not supported on purpose.
    'uploads' => [
        'enabled' => env('PRESS_PACKAGE_UPLOADS', true),
        'max_zip_kb' => 10 * 1024,
        'max_uncompressed_kb' => 50 * 1024,
        'max_entries' => 2000,
        'max_ratio' => 100,
    ],

    'revisions' => [
        'keep' => 25,
    ],

    'media' => [
        'disk' => 'public',
        'directory' => 'media',
        'max_kb' => 8 * 1024,
        'accepted' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'application/pdf'],
    ],

    // Single-segment paths that pages may never take (core routes).
    'reserved_slugs' => ['admin', 'blog', 'category', 'livewire', 'filament', 'storage', 'theme-assets', 'up', 'login', 'logout', 'build', 'vendor'],
];
