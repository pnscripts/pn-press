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

    // Menu items nest up to this many levels (1 = no dropdowns). Deeper items are not shown.
    'menus' => [
        'max_depth' => 3,
    ],

    /*
    | Full-page cache for anonymous visitors (App\Press\Cache\PageCache). Entries expire after
    | "ttl" seconds or when the next scheduled post/page goes live, whichever is first, and every
    | content, menu, settings, plugin or theme change flushes them. Pages with a form are never
    | stored. Off by default while APP_DEBUG is on, so template edits show at once in development.
    | After deploying new theme or view files run: php artisan press:page-cache clear
    */
    'page_cache' => [
        'enabled' => (bool) env('PRESS_PAGE_CACHE', ! env('APP_DEBUG', false)),
        'ttl' => (int) env('PRESS_PAGE_CACHE_TTL', 3600),
        'store' => env('PRESS_PAGE_CACHE_STORE'),
        'max_kb' => 2048,
        // Query keys a cached URL may have; any other key (utm_*, preview_theme…) skips the cache.
        'query' => ['page'],
        // Paths never cached (Request::is patterns).
        'except' => ['admin', 'admin/*', 'livewire/*', 'up'],
    ],

    /*
    | Update notices (App\Press\Updates\UpdateChecker): a daily GET of small JSON files. Nothing is
    | downloaded or installed. Plugins/themes opt in with "update_url" in their manifest.
    */
    'updates' => [
        'enabled' => (bool) env('PRESS_UPDATE_CHECKS', true),
        'core_url' => env('PRESS_UPDATE_URL', 'https://api.github.com/repos/pnscripts/pn-press/releases/latest'),
    ],

    'admin' => [
        // Every admin/editor must set up an authenticator app before using the panel.
        'require_2fa' => (bool) env('PRESS_REQUIRE_2FA', false),
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
