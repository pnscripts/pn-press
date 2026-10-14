<?php

namespace App\Press\Themes;

use App\Press\Packages\InvalidPackage;
use App\Press\Packages\Manifest;

/**
 * theme.json:
 *
 *     {
 *       "slug": "nocturne",
 *       "name": "Nocturne",
 *       "version": "1.0.0",
 *       "screenshot": "screenshot.svg",
 *       "stylesheet": "assets/theme.css",
 *       "templates": { "landing": "Landing page" },
 *       "settings": [
 *         { "key": "accent", "label": "Accent colour", "type": "color", "default": "#a78bfa" },
 *         { "key": "display_font", "label": "Headings", "type": "select", "default": "serif",
 *           "options": { "serif": "Serif", "sans": "Sans" } }
 *       ]
 *     }
 *
 * Views in views/ override the default views with the same name (layouts/app.blade.php,
 * press/blocks/hero.blade.php, posts/index.blade.php …).
 */
class ThemeManifest extends Manifest
{
    public const SETTING_TYPES = ['color', 'text', 'select', 'toggle'];

    public readonly ?string $screenshot;

    public readonly ?string $stylesheet;

    /** @var array<string, string> */
    public readonly array $templates;

    /** @var list<array{key: string, label: string, type: string, default: mixed, options: array<string, string>}> */
    public readonly array $settings;

    public function __construct(string $path, array $data)
    {
        parent::__construct($path, $data);

        $this->screenshot = isset($data['screenshot']) ? $this->relativePath($data['screenshot'], 'screenshot.svg') : null;
        $this->stylesheet = isset($data['stylesheet']) ? $this->relativePath($data['stylesheet'], 'assets/theme.css') : null;

        $templates = [];
        foreach ((array) ($data['templates'] ?? []) as $key => $label) {
            if (! is_string($key) || ! preg_match('/^[a-z][a-z0-9-]{0,40}$/', $key) || ! is_string($label)) {
                throw new InvalidPackage('"templates" must map lowercase keys to labels.');
            }
            $templates[$key] = mb_substr($label, 0, 80);
        }

        $settings = [];
        foreach ((array) ($data['settings'] ?? []) as $field) {
            $key = $field['key'] ?? null;
            $type = $field['type'] ?? 'text';

            if (! is_string($key) || ! preg_match('/^[a-z][a-z0-9_]{0,40}$/', $key) || ! in_array($type, self::SETTING_TYPES, true)) {
                throw new InvalidPackage('Each theme setting needs a snake_case "key" and a type of '.implode(', ', self::SETTING_TYPES).'.');
            }

            $settings[] = [
                'key' => $key,
                'label' => is_string($field['label'] ?? null) ? mb_substr($field['label'], 0, 80) : $key,
                'type' => $type,
                'default' => $field['default'] ?? null,
                'options' => array_map('strval', (array) ($field['options'] ?? [])),
            ];
        }

        $this->templates = $templates;
        $this->settings = $settings;
    }

    public static function filename(): string
    {
        return 'theme.json';
    }

    public function viewsPath(): string
    {
        return $this->file('views');
    }
}
