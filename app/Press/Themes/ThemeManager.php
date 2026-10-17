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
            if (is_string($slug) && ($theme = $this->find($slug)) && $this->problem($theme) === null) {
                return $slug;
            }
        }

        return null;
    }

    /** The parent of a child theme, if it is installed. */
    public function parentOf(ThemeManifest $theme): ?ThemeManifest
    {
        return $theme->parent ? $this->find($theme->parent) : null;
    }

    /**
     * The theme followed by its parent: where views, assets, templates and settings are looked up, in order.
     *
     * @return list<ThemeManifest>
     */
    public function lineage(ThemeManifest $theme): array
    {
        $parent = $this->parentOf($theme);

        return $parent ? [$theme, $parent] : [$theme];
    }

    /** Why a theme cannot be used (missing or nested parent), or null when it can. */
    public function problem(ThemeManifest $theme): ?string
    {
        if ($theme->parent === null) {
            return null;
        }

        $parent = $this->find($theme->parent);

        if ($parent === null) {
            return "{$theme->name} is a child theme of \"{$theme->parent}\", which is not installed.";
        }

        if ($parent->parent !== null) {
            return "{$theme->name} uses {$parent->name} as its parent, but {$parent->name} is itself a child theme.";
        }

        return null;
    }

    /** Child themes that depend on this theme. @return list<ThemeManifest> */
    public function childrenOf(string $slug): array
    {
        return array_values(array_filter($this->all(), fn (ThemeManifest $theme) => $theme->parent === $slug));
    }

    /**
     * Setting fields of a theme including its parent's; the child's field wins on the same key.
     *
     * @return list<array{key: string, label: string, type: string, default: mixed, options: array<string, string>}>
     */
    public function fields(ThemeManifest $theme): array
    {
        $fields = [];
        foreach (array_reverse($this->lineage($theme)) as $layer) {
            foreach ($layer->settings as $field) {
                $fields[$field['key']] = $field;
            }
        }

        return array_values($fields);
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

        $themePaths = [];
        foreach ($theme ? $this->lineage($theme) : [] as $layer) {
            if (is_dir($layer->viewsPath())) {
                $themePaths[] = $layer->viewsPath();
            }
        }

        $finder->setPaths(array_merge($themePaths, $this->basePaths));
        $finder->flush();

        if ($themePaths !== []) {
            View::replaceNamespace('theme', $themePaths);
        }

        $this->hooks->doAction('theme.applied', $theme);
    }

    public function activate(string $slug): ThemeManifest
    {
        $theme = $this->find($slug) ?? throw new InvalidPackage("Theme \"{$slug}\" is not installed.");

        if (! $theme->isCompatible((string) config('press.version'))) {
            throw new InvalidPackage("{$theme->name} needs PN Scripts Press {$theme->requiresPress}.");
        }

        if ($problem = $this->problem($theme)) {
            throw new InvalidPackage($problem.' Install the parent theme first.');
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

        if ($children = $this->childrenOf($slug)) {
            throw new InvalidPackage("{$theme->name} is the parent of ".implode(', ', array_map(fn (ThemeManifest $child) => $child->name, $children)).'. Delete the child themes first.');
        }

        $this->settings->forget('theme.'.$slug);
        $this->files->deleteDirectory($theme->path);
        $this->manifests = null;
        $this->hooks->doAction('theme.uninstalled', $theme);
    }

    public function installZip(string $zipPath, bool $replace = false): ThemeManifest
    {
        /** @var ThemeManifest $theme */
        $theme = $this->installer->installZip($zipPath, 'theme', ThemeManifest::class, $this->path(), $replace);
        $this->manifests = null;
        $this->hooks->doAction('theme.installed', $theme);

        return $theme;
    }

    public function installDirectory(string $directory, bool $replace = false): ThemeManifest
    {
        /** @var ThemeManifest $theme */
        $theme = $this->installer->installDirectory($directory, 'theme', ThemeManifest::class, $this->path(), $replace);
        $this->manifests = null;
        $this->hooks->doAction('theme.installed', $theme);

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

        foreach ($this->fields($theme) as $field) {
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
        foreach ($this->fields($theme) as $field) {
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
        $values = $this->settingsFor($theme);
        foreach ($this->fields($theme) as $field) {
            $value = $values[$field['key']];
            if ($field['type'] === 'color' && is_string($value) && preg_match('/^#[0-9a-f]{3}(?:[0-9a-f]{3})?$/i', $value)) {
                $vars[] = '--press-'.str_replace('_', '-', $field['key']).':'.$value;
            }
        }

        $css = $vars ? ':root{'.implode(';', $vars).'}' : '';

        // Parent stylesheet first, so the child's rules win.
        $links = '';
        foreach (array_reverse($this->lineage($theme)) as $layer) {
            if ($layer->stylesheet && is_file($layer->file($layer->stylesheet))) {
                $links .= '<link rel="stylesheet" href="'.e($this->assetUrl($layer->stylesheet, $layer)).'">';
            }
        }

        return new HtmlString(($css ? '<style id="press-theme-vars">'.$css.'</style>' : '').$links);
    }

    /** data-press-* attributes for <html>, from select and toggle settings. */
    public function htmlAttributes(): HtmlString
    {
        $theme = $this->current();
        $attributes = ['data-press-theme="'.e($theme?->slug ?? 'none').'"'];

        $values = $theme ? $this->settingsFor($theme) : [];
        foreach ($theme ? $this->fields($theme) : [] as $field) {
            if (in_array($field['type'], ['select', 'toggle'], true)) {
                $value = $values[$field['key']];
                $value = is_bool($value) ? ($value ? 'on' : 'off') : (string) $value;
                $attributes[] = 'data-press-'.str_replace('_', '-', $field['key']).'="'.e($value).'"';
            }
        }

        return new HtmlString(implode(' ', $attributes));
    }

    /**
     * The file behind a theme asset path, looked up in the theme and then its parent,
     * so theme_asset('assets/…') in a child theme also finds files only the parent ships.
     */
    public function assetFile(ThemeManifest $theme, string $path): ?string
    {
        foreach ($this->lineage($theme) as $layer) {
            $root = realpath($layer->path);
            $file = realpath($layer->path.'/'.$path);

            if ($root !== false && $file !== false && str_starts_with($file, $root.DIRECTORY_SEPARATOR) && is_file($file)) {
                return $file;
            }
        }

        return null;
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

        $current = $this->current();
        foreach ($current ? array_reverse($this->lineage($current)) : [] as $layer) {
            $templates = array_merge($templates, $layer->templates);
        }

        return $this->hooks->applyFilters('page.templates', $templates);
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
