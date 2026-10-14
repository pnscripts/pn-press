<?php

namespace App\Press\Themes;

use App\Press\Hooks;
use App\Press\Packages\InvalidPackage;
use App\Press\Packages\PackageInstaller;
use App\Press\Settings;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;

/**
 * Themes: folders in config('press.themes_path') with a theme.json.
 *
 * The current theme's views/ folder is put in front of resources/views, so a theme
 * overrides any default view by shipping a file with the same name. Its settings
 * become CSS custom properties (colours) and data-* attributes (choices) on <html>.
 */
class ThemeManager
{
    /** @var array<string, ThemeManifest>|null */
    private ?array $manifests = null;

    /** @var array<string, string> */
    private array $errors = [];

    private ?string $current = null;

    /** @var list<string>|null */
    private ?array $basePaths = null;

    public function __construct(
        private readonly Filesystem $files,
        private readonly Settings $settings,
        private readonly Hooks $hooks,
        private readonly PackageInstaller $installer,
    ) {}

    public function path(): string
    {
        return rtrim((string) config('press.themes_path'), '/');
    }

    /** @return array<string, ThemeManifest> */
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
                $manifest = ThemeManifest::fromDirectory($dir);

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

    public function find(string $slug): ?ThemeManifest
    {
        return $this->all()[$slug] ?? null;
    }

    /** The theme visitors see. */
    public function activeSlug(): ?string
    {
        foreach ([$this->settings->get('theme.active'), config('press.default_theme')] as $slug) {
            if (is_string($slug) && $this->find($slug)) {
                return $slug;
            }
        }

        return null;
    }

    /** The theme of this request (the active one, or a preview). */
    public function current(): ?ThemeManifest
    {
        $slug = $this->current ?? $this->activeSlug();

        return $slug ? $this->find($slug) : null;
    }

    /**
     * Use a theme for the rest of this request. Pass null for the active theme.
     */
    public function apply(?string $slug = null): void
    {
        $slug ??= $this->activeSlug();
        $finder = View::getFinder();
        $this->basePaths ??= $finder->getPaths();

        $theme = $slug ? $this->find($slug) : null;
        $this->current = $theme?->slug;

        $paths = $this->basePaths;
        if ($theme && is_dir($theme->viewsPath())) {
            array_unshift($paths, $theme->viewsPath());
        }

        $finder->setPaths($paths);
        $finder->flush();

        if ($theme && is_dir($theme->viewsPath())) {
            View::replaceNamespace('theme', [$theme->viewsPath()]);
        }

        $this->hooks->doAction('theme.applied', $theme);
    }

    public function activate(string $slug): ThemeManifest
    {
        $theme = $this->find($slug) ?? throw new InvalidPackage("Theme \"{$slug}\" is not installed.");

        if (! $theme->isCompatible((string) config('press.version'))) {
            throw new InvalidPackage("{$theme->name} needs PN Scripts Press {$theme->requiresPress}.");
        }

        $this->settings->set('theme.active', $slug);
        $this->hooks->doAction('theme.activated', $theme);

        return $theme;
    }

    public function uninstall(string $slug): void
    {
        $theme = $this->find($slug) ?? throw new InvalidPackage("Theme \"{$slug}\" is not installed.");

        if ($slug === $this->activeSlug()) {
            throw new InvalidPackage('Activate another theme before deleting the active one.');
        }

        if ($slug === config('press.default_theme')) {
            throw new InvalidPackage('The default theme cannot be deleted.');
        }

        $this->settings->forget('theme.'.$slug);
        $this->files->deleteDirectory($theme->path);
        $this->manifests = null;
    }

    public function installZip(string $zipPath, bool $replace = false): ThemeManifest
    {
        /** @var ThemeManifest $theme */
        $theme = $this->installer->installZip($zipPath, 'theme', ThemeManifest::class, $this->path(), $replace);
        $this->manifests = null;

        return $theme;
    }

    public function installDirectory(string $directory, bool $replace = false): ThemeManifest
    {
        /** @var ThemeManifest $theme */
        $theme = $this->installer->installDirectory($directory, 'theme', ThemeManifest::class, $this->path(), $replace);
        $this->manifests = null;

        return $theme;
    }

    /**
     * Saved values merged over the manifest defaults, each one checked against its type.
     *
     * @return array<string, mixed>
     */
    public function settingsFor(ThemeManifest $theme): array
    {
        $saved = (array) $this->settings->get('theme.'.$theme->slug, []);
        $values = [];

        foreach ($theme->settings as $field) {
            $values[$field['key']] = $this->sanitize($field, $saved[$field['key']] ?? $field['default']);
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function saveSettings(ThemeManifest $theme, array $values): void
    {
        $clean = [];
        foreach ($theme->settings as $field) {
            $clean[$field['key']] = $this->sanitize($field, $values[$field['key']] ?? $field['default']);
        }

        $this->settings->set('theme.'.$theme->slug, $clean);
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        $theme = $this->current();

        return $theme ? ($this->settingsFor($theme)[$key] ?? $default) : $default;
    }

    /** <style> with the colour settings as --press-* custom properties. */
    public function styleTag(): HtmlString
    {
        $theme = $this->current();
        if (! $theme) {
            return new HtmlString('');
        }

        $vars = [];
        foreach ($theme->settings as $field) {
            $value = $this->settingsFor($theme)[$field['key']];
            if ($field['type'] === 'color' && is_string($value) && preg_match('/^#[0-9a-f]{3}(?:[0-9a-f]{3})?$/i', $value)) {
                $vars[] = '--press-'.str_replace('_', '-', $field['key']).':'.$value;
            }
        }

        $css = $vars ? ':root{'.implode(';', $vars).'}' : '';
        $link = $theme->stylesheet && is_file($theme->file($theme->stylesheet))
            ? '<link rel="stylesheet" href="'.e($this->assetUrl($theme->stylesheet, $theme)).'">'
            : '';

        return new HtmlString(($css ? '<style id="press-theme-vars">'.$css.'</style>' : '').$link);
    }

    /** data-press-* attributes for <html>, from select and toggle settings. */
    public function htmlAttributes(): HtmlString
    {
        $theme = $this->current();
        $attributes = ['data-press-theme="'.e($theme?->slug ?? 'none').'"'];

        foreach ($theme?->settings ?? [] as $field) {
            if (in_array($field['type'], ['select', 'toggle'], true)) {
                $value = $this->settingsFor($theme)[$field['key']];
                $value = is_bool($value) ? ($value ? 'on' : 'off') : (string) $value;
                $attributes[] = 'data-press-'.str_replace('_', '-', $field['key']).'="'.e($value).'"';
            }
        }

        return new HtmlString(implode(' ', $attributes));
    }

    public function assetUrl(string $path, ?ThemeManifest $theme = null): string
    {
        $theme ??= $this->current();

        return $theme ? route('press.theme-asset', ['theme' => $theme->slug, 'path' => ltrim($path, '/')]).'?v='.urlencode($theme->version) : '';
    }

    /**
     * Page templates: the built-in ones plus those the current theme declares.
     *
     * @return array<string, string>
     */
    public function templates(): array
    {
        $templates = ['default' => 'Default', 'landing' => 'Landing (full width, no title)'];

        return $this->hooks->applyFilters('page.templates', array_merge($templates, $this->current()?->templates ?? []));
    }

    /**
     * @param  array{type: string, default: mixed, options: array<string, string>}  $field
     */
    private function sanitize(array $field, mixed $value): mixed
    {
        return match ($field['type']) {
            'color' => is_string($value) && preg_match('/^#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?$/', $value) ? strtolower($value) : $field['default'],
            'toggle' => (bool) $value,
            'select' => is_string($value) && array_key_exists($value, $field['options']) ? $value : $field['default'],
            default => is_scalar($value) ? mb_substr(trim((string) $value), 0, 500) : $field['default'],
        };
    }
}
