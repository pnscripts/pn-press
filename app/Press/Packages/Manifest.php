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

    public readonly ?string $requiresPhp;

    /** @var list<string> PHP extensions that must be loaded */
    public readonly array $requiresExtensions;

    /** Optional HTTPS URL of a small JSON file announcing the latest version (see App\Press\Updates\UpdateChecker). */
    public readonly ?string $updateUrl;

    public const CONSTRAINT_PATTERN = '/^(>=|>|\^|~)?\s*\d+\.\d+(?:\.\d+)?$/';

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
        if ($requires !== null && (! is_string($requires) || ! preg_match(self::CONSTRAINT_PATTERN, $requires))) {
            throw new InvalidPackage('"requires.press" must look like ">=1.0".');
        }

        $php = $data['requires']['php'] ?? null;
        if ($php !== null && (! is_string($php) || ! preg_match(self::CONSTRAINT_PATTERN, $php))) {
            throw new InvalidPackage('"requires.php" must look like ">=8.3".');
        }

        $extensions = $data['requires']['extensions'] ?? [];
        if (! is_array($extensions) || ! array_is_list($extensions)) {
            throw new InvalidPackage('"requires.extensions" must be a list like ["gd", "intl"].');
        }
        foreach ($extensions as $extension) {
            if (! is_string($extension) || ! preg_match('/^[A-Za-z][A-Za-z0-9_]{0,40}$/', $extension)) {
                throw new InvalidPackage('"requires.extensions" must be a list like ["gd", "intl"].');
            }
        }

        $updateUrl = $data['update_url'] ?? null;
        if ($updateUrl !== null && (! is_string($updateUrl) || strlen($updateUrl) > 500 || ! str_starts_with($updateUrl, 'https://') || filter_var($updateUrl, FILTER_VALIDATE_URL) === false)) {
            throw new InvalidPackage('"update_url" must be an https:// URL.');
        }

        $this->slug = $slug;
        $this->name = trim($name);
        $this->version = $version;
        $this->description = is_string($data['description'] ?? null) ? mb_substr($data['description'], 0, 500) : '';
        $this->author = is_string($data['author'] ?? null) ? mb_substr($data['author'], 0, 120) : '';
        $this->requiresPress = $requires;
        $this->requiresPhp = $php;
        $this->requiresExtensions = $extensions;
        $this->updateUrl = $updateUrl;
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
        return $this->requiresPress === null || self::satisfies($pressVersion, $this->requiresPress);
    }

    /**
     * Requirements of the server that are not met: PN Scripts Press, PHP and extensions.
     *
     * @return list<string>
     */
    public function unmetPlatformRequirements(string $pressVersion, string $phpVersion = PHP_VERSION): array
    {
        $unmet = [];

        if (! $this->isCompatible($pressVersion)) {
            $unmet[] = "PN Scripts Press {$this->requiresPress} (this site runs {$pressVersion})";
        }

        if ($this->requiresPhp !== null && ! self::satisfies($phpVersion, $this->requiresPhp)) {
            $unmet[] = "PHP {$this->requiresPhp} (this server runs {$phpVersion})";
        }

        foreach ($this->requiresExtensions as $extension) {
            if (! extension_loaded($extension)) {
                $unmet[] = "the PHP extension {$extension}";
            }
        }

        return $unmet;
    }

    /** Whether a version meets a constraint: ">=1.2", ">1.2", "^1.2" / "~1.2" (same major, at least), "1.2" (at least). */
    public static function satisfies(string $installed, string $constraint): bool
    {
        if (trim($constraint) === '*') {
            return true;
        }

        preg_match('/^(>=|>|\^|~)?\s*(.+)$/', trim($constraint), $m);
        $operator = ($m[1] ?? '') ?: '>=';
        $version = $m[2] ?? '0';

        return match ($operator) {
            '>' => version_compare($installed, $version, '>'),
            '^', '~' => version_compare($installed, $version, '>=')
                && (int) explode('.', $installed)[0] === (int) explode('.', $version)[0],
            default => version_compare($installed, $version, '>='),
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
