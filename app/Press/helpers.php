<?php

use App\Press\Hooks;
use App\Press\Settings;
use App\Press\Themes\ThemeManager;

/*
| WordPress-familiar helper functions for plugin and theme authors.
| They are thin wrappers around App\Press\Hooks and App\Press\Settings.
*/

if (! function_exists('add_action')) {
    function add_action(string $hook, callable $callback, int $priority = 10): void
    {
        app(Hooks::class)->addAction($hook, $callback, $priority);
    }
}

if (! function_exists('do_action')) {
    /** @return list<mixed> */
    function do_action(string $hook, mixed ...$args): array
    {
        return app(Hooks::class)->doAction($hook, ...$args);
    }
}

if (! function_exists('add_filter')) {
    function add_filter(string $hook, callable $callback, int $priority = 10): void
    {
        app(Hooks::class)->addFilter($hook, $callback, $priority);
    }
}

if (! function_exists('apply_filters')) {
    function apply_filters(string $hook, mixed $value, mixed ...$args): mixed
    {
        return app(Hooks::class)->applyFilters($hook, $value, ...$args);
    }
}

if (! function_exists('press_setting')) {
    function press_setting(string $key, mixed $default = null): mixed
    {
        return app(Settings::class)->get($key, $default);
    }
}

if (! function_exists('theme_asset')) {
    /** URL of a file in the current theme's folder (e.g. "assets/logo.svg"); child themes fall back to the parent's file. */
    function theme_asset(string $path): string
    {
        return app(ThemeManager::class)->assetUrl($path);
    }
}
