<?php

namespace App\Press\Updates;

use App\Press\Packages\Manifest;
use App\Press\Plugins\PluginManager;
use App\Press\Themes\ThemeManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Update notices. Reads small JSON files and tells the admin when a newer version exists.
 * It never downloads or installs code: updating stays an admin upload (or git pull).
 *
 * A plugin or theme opts in with "update_url" in its manifest; the URL answers
 *
 *     { "version": "1.2.0", "url": "https://example.com/contact-form/changelog" }
 *
 * PN Scripts Press itself is checked against config('press.updates.core_url') (the GitHub
 * "latest release" API by default; "tag_name" and "html_url" are understood too).
 * Only a GET is sent, with no site data. Turn it off with PRESS_UPDATE_CHECKS=false.
 */
class UpdateChecker
{
    public const CACHE_KEY = 'press.updates';

    private const MAX_BYTES = 64 * 1024;

    public function __construct(
        private readonly PluginManager $plugins,
        private readonly ThemeManager $themes,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('press.updates.enabled', true);
    }

    /**
     * Fetch every feed and remember what is newer. Returns the notices found.
     *
     * @return array<string, array{name: string, installed: string, version: string, url: ?string}>
     */
    public function check(): array
    {
        $found = [];

        $core = config('press.updates.core_url');
        if (is_string($core) && $core !== '') {
            $this->compare($found, 'core', 'PN Scripts Press', (string) config('press.version'), $core);
        }

        foreach ($this->plugins->all() as $plugin) {
            $this->checkManifest($found, 'plugin', $plugin);
        }

        foreach ($this->themes->all() as $theme) {
            $this->checkManifest($found, 'theme', $theme);
        }

        Cache::forever(self::CACHE_KEY, ['checked_at' => now()->toIso8601String(), 'items' => $found]);

        return $found;
    }

    /**
     * Notices from the last check that are still newer than what is installed now.
     *
     * @return array<string, array{name: string, installed: string, version: string, url: ?string}>
     */
    public function available(): array
    {
        if (! $this->enabled()) {
            return [];
        }

        $items = (array) (Cache::get(self::CACHE_KEY)['items'] ?? []);
        $current = ['core' => (string) config('press.version')];
        foreach ($this->plugins->all() as $plugin) {
            $current['plugin:'.$plugin->slug] = $plugin->version;
        }
        foreach ($this->themes->all() as $theme) {
            $current['theme:'.$theme->slug] = $theme->version;
        }

        return array_filter($items, fn ($item, $key) => is_array($item) && isset($current[$key], $item['version'])
            && version_compare((string) $item['version'], $current[$key], '>'), ARRAY_FILTER_USE_BOTH);
    }

    /** @return array{name: string, installed: string, version: string, url: ?string}|null */
    public function for(string $key): ?array
    {
        return $this->available()[$key] ?? null;
    }

    public function lastChecked(): ?string
    {
        $checked = Cache::get(self::CACHE_KEY)['checked_at'] ?? null;

        return is_string($checked) ? $checked : null;
    }

    /** @param  array<string, array{name: string, installed: string, version: string, url: ?string}>  $found */
    private function checkManifest(array &$found, string $kind, Manifest $manifest): void
    {
        if ($manifest->updateUrl !== null) {
            $this->compare($found, $kind.':'.$manifest->slug, $manifest->name, $manifest->version, $manifest->updateUrl);
        }
    }

    /** @param  array<string, array{name: string, installed: string, version: string, url: ?string}>  $found */
    private function compare(array &$found, string $key, string $name, string $installed, string $url): void
    {
        $latest = $this->fetch($url);

        if ($latest !== null && version_compare($latest['version'], $installed, '>')) {
            $found[$key] = ['name' => $name, 'installed' => $installed] + $latest;
        }
    }

    /** @return array{version: string, url: ?string}|null */
    private function fetch(string $url): ?array
    {
        if (! str_starts_with($url, 'https://')) {
            return null;
        }

        try {
            $response = Http::timeout(5)
                ->connectTimeout(3)
                ->withoutRedirecting()
                ->accept('application/json')
                ->withUserAgent('PN-Scripts-Press/'.config('press.version'))
                ->get($url);
        } catch (Throwable) {
            return null;
        }

        $body = $response->body();
        if (! $response->successful() || strlen($body) > self::MAX_BYTES) {
            return null;
        }

        $data = json_decode($body, true);
        if (! is_array($data)) {
            return null;
        }

        $version = $data['version'] ?? (is_string($data['tag_name'] ?? null) ? ltrim($data['tag_name'], 'vV') : null);
        if (! is_string($version) || ! preg_match('/^\d+\.\d+(?:\.\d+)?(?:-[0-9A-Za-z.-]+)?$/', $version)) {
            return null;
        }

        // Pre-releases are not offered.
        if (str_contains($version, '-') || ($data['prerelease'] ?? false) === true) {
            return null;
        }

        $link = $data['url'] ?? $data['html_url'] ?? null;
        $link = is_string($link) && str_starts_with($link, 'https://') && filter_var($link, FILTER_VALIDATE_URL) ? $link : null;

        return ['version' => $version, 'url' => $link];
    }
}
