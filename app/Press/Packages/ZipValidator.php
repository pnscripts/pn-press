<?php

namespace App\Press\Packages;

use ZipArchive;

/**
 * Checks an uploaded plugin/theme zip before anything is written to disk.
 *
 * Rejects: oversized archives, too many entries, zip bombs (uncompressed total and
 * compression ratio), absolute paths, "..", backslashes, drive letters, NUL bytes,
 * symlinks, dotfiles (.htaccess, .env, .git), file types outside the allowlist,
 * PHP in themes outside *.blade.php, and archives without a single package root
 * that holds the manifest.
 */
class ZipValidator
{
    public const PLUGIN_EXTENSIONS = ['php', 'json', 'md', 'txt', 'css', 'js', 'map', 'svg', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'ico', 'woff', 'woff2', 'ttf', 'otf', 'xml', 'csv'];

    public const THEME_EXTENSIONS = ['php', 'json', 'md', 'txt', 'css', 'js', 'map', 'svg', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'ico', 'woff', 'woff2', 'ttf', 'otf'];

    /** Entries silently skipped (created by macOS Finder). */
    private const IGNORED = ['__MACOSX/', '.DS_Store'];

    /**
     * @param  'plugin'|'theme'  $kind
     * @return array{root: string, files: list<string>} root is '' or 'folder/'; files are entry names to extract
     */
    public function validate(string $zipPath, string $kind): array
    {
        $limits = config('press.uploads');
        $manifest = $kind === 'theme' ? 'theme.json' : 'plugin.json';
        $allowed = $kind === 'theme' ? self::THEME_EXTENSIONS : self::PLUGIN_EXTENSIONS;

        if (! is_file($zipPath)) {
            throw new InvalidPackage('The upload is missing.');
        }

        if (filesize($zipPath) > $limits['max_zip_kb'] * 1024) {
            throw new InvalidPackage('The zip is larger than '.$limits['max_zip_kb'].' KB.');
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::RDONLY) !== true) {
            throw new InvalidPackage('The file is not a readable zip archive.');
        }

        try {
            if ($zip->numFiles === 0) {
                throw new InvalidPackage('The zip is empty.');
            }

            if ($zip->numFiles > $limits['max_entries']) {
                throw new InvalidPackage('The zip has more than '.$limits['max_entries'].' entries.');
            }

            $total = 0;
            $names = [];

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i, ZipArchive::FL_UNCHANGED);
                $name = (string) $stat['name'];

                if ($this->ignored($name)) {
                    continue;
                }

                $this->assertSafeName($name);

                if ($zip->getExternalAttributesIndex($i, $opsys, $attributes) && $opsys === ZipArchive::OPSYS_UNIX) {
                    $type = ($attributes >> 16) & 0170000;
                    if ($type === 0120000) {
                        throw new InvalidPackage("Symbolic links are not allowed ({$name}).");
                    }
                }

                $isDir = str_ends_with($name, '/');
                $size = (int) $stat['size'];
                $total += $size;

                if ($total > $limits['max_uncompressed_kb'] * 1024) {
                    throw new InvalidPackage('The unpacked package would be larger than '.$limits['max_uncompressed_kb'].' KB.');
                }

                if ($size > 1024 * 1024 && $size / max(1, (int) $stat['comp_size']) > $limits['max_ratio']) {
                    throw new InvalidPackage("Suspicious compression ratio ({$name}).");
                }

                if (! $isDir) {
                    $this->assertAllowedFile($name, $allowed, $kind);
                    $names[] = $name;
                }
            }

            $root = $this->detectRoot($names, $manifest);

            return ['root' => $root, 'files' => $names];
        } finally {
            $zip->close();
        }
    }

    private function ignored(string $name): bool
    {
        foreach (self::IGNORED as $ignored) {
            if (str_starts_with($name, $ignored) || basename($name) === $ignored) {
                return true;
            }
        }

        return false;
    }

    private function assertSafeName(string $name): void
    {
        if ($name === '' || str_contains($name, "\0") || str_contains($name, '\\')
            || str_starts_with($name, '/') || preg_match('/^[A-Za-z]:/', $name) || strlen($name) > 255) {
            throw new InvalidPackage('Unsafe path in zip: '.$this->printable($name));
        }

        $segments = explode('/', rtrim($name, '/'));

        if (count($segments) > 12) {
            throw new InvalidPackage('Folders are nested too deeply: '.$this->printable($name));
        }

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new InvalidPackage('Unsafe path in zip: '.$this->printable($name));
            }

            if (str_starts_with($segment, '.')) {
                throw new InvalidPackage('Hidden files and folders are not allowed: '.$this->printable($name));
            }

            if (! preg_match('/^[A-Za-z0-9 _.@()+-]+$/', $segment)) {
                throw new InvalidPackage('Unsupported characters in file name: '.$this->printable($name));
            }
        }
    }

    /**
     * @param  list<string>  $allowed
     */
    private function assertAllowedFile(string $name, array $allowed, string $kind): void
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if (! in_array($extension, $allowed, true)) {
            throw new InvalidPackage("File type .{$extension} is not allowed ({$this->printable($name)}).");
        }

        if ($kind === 'theme' && $extension === 'php' && ! str_ends_with(strtolower($name), '.blade.php')) {
            throw new InvalidPackage("Themes may only contain PHP as Blade views (*.blade.php): {$this->printable($name)}");
        }
    }

    /**
     * The manifest must sit at the zip root or inside one top-level folder that holds everything.
     *
     * @param  list<string>  $names
     */
    private function detectRoot(array $names, string $manifest): string
    {
        if (in_array($manifest, $names, true)) {
            return '';
        }

        $tops = array_unique(array_map(fn ($name) => explode('/', $name)[0], $names));

        if (count($tops) === 1) {
            $root = reset($tops).'/';

            if (in_array($root.$manifest, $names, true)) {
                return $root;
            }
        }

        throw new InvalidPackage("No {$manifest} found at the top of the package.");
    }

    private function printable(string $name): string
    {
        return mb_substr(preg_replace('/[^\x20-\x7E]/', '?', $name) ?? '', 0, 120);
    }
}
