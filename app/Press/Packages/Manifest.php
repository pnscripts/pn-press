<?php

namespace App\Press\Packages;

/**
 * Fields shared by plugin.json and theme.json.
 */
abstract class Manifest
{
    public const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public readonly string $slug;

    public readonly string $name;

    public readonly string $version;

    public readonly string $description;

    public readonly string $author;

    public readonly ?string $requiresPress;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(public readonly string $path, public readonly array $data)
    {
        $slug = $data['slug'] ?? null;
        if (! is_string($slug) || strlen($slug) > 64 || ! preg_match(self::SLUG_PATTERN, $slug)) {
            throw new InvalidPackage('The manifest needs a "slug" of lowercase letters, digits and dashes (max 64).');
        }

        $name = $data['name'] ?? null;
        if (! is_string($name) || trim($name) === '' || mb_strlen($name) > 80) {
            throw new InvalidPackage('The manifest needs a "name" (max 80 characters).');
        }

        $version = $data['version'] ?? null;
        if (! is_string($version) || ! preg_match('/^\d+\.\d+(?:\.\d+)?(?:[-+][0-9A-Za-z.-]+)?$/', $version)) {
            throw new InvalidPackage('The manifest needs a "version" like 1.0.0.');
        }

        $requires = $data['requires']['press'] ?? null;
        if ($requires !== null && (! is_string($requires) || ! preg_match('/^(>=|>|\^|~)?\s*\d+\.\d+(?:\.\d+)?$/', $requires))) {
            throw new InvalidPackage('"requires.press" must look like ">=1.0".');
        }

        $this->slug = $slug;
        $this->name = trim($name);
        $this->version = $version;
        $this->description = is_string($data['description'] ?? null) ? mb_substr($data['description'], 0, 500) : '';
        $this->author = is_string($data['author'] ?? null) ? mb_substr($data['author'], 0, 120) : '';
        $this->requiresPress = $requires;
    }

    abstract public static function filename(): string;

    public static function fromDirectory(string $directory): static
    {
        $file = rtrim($directory, '/').'/'.static::filename();

        if (! is_file($file)) {
            throw new InvalidPackage('Missing '.static::filename().'.');
        }

        if (filesize($file) > 64 * 1024) {
            throw new InvalidPackage(static::filename().' is too large.');
        }

        $data = json_decode((string) file_get_contents($file), true);

        if (! is_array($data)) {
            throw new InvalidPackage(static::filename().' is not valid JSON.');
        }

        return new static(rtrim($directory, '/'), $data);
    }

    public function isCompatible(string $pressVersion): bool
    {
        if ($this->requiresPress === null) {
            return true;
        }

        preg_match('/^(>=|>|\^|~)?\s*(.+)$/', $this->requiresPress, $m);
        $operator = $m[1] ?: '>=';
        $version = $m[2];

        return match ($operator) {
            '>' => version_compare($pressVersion, $version, '>'),
            '^', '~' => version_compare($pressVersion, $version, '>=')
                && (int) explode('.', $pressVersion)[0] === (int) explode('.', $version)[0],
            default => version_compare($pressVersion, $version, '>='),
        };
    }

    /** A path inside the package; rejects anything that could leave it. */
    public function relativePath(mixed $value, string $default): string
    {
        $value = is_string($value) && $value !== '' ? $value : $default;
        $value = trim(str_replace('\\', '/', $value), '/');

        foreach (explode('/', $value) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new InvalidPackage("Invalid path \"{$value}\" in ".static::filename().'.');
            }
        }

        return $value;
    }

    public function file(string $relative): string
    {
        return $this->path.'/'.$relative;
    }
}
