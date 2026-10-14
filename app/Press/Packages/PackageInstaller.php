<?php

namespace App\Press\Packages;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Unpacks a validated zip, or copies a local folder, into the plugins/themes directory.
 *
 * Nothing is downloaded from the network. Files are written one by one from the
 * validated entry list, never with ZipArchive::extractTo on the raw archive.
 */
class PackageInstaller
{
    public function __construct(
        private readonly ZipValidator $validator,
        private readonly Filesystem $files,
    ) {}

    /**
     * @param  'plugin'|'theme'  $kind
     * @param  class-string<Manifest>  $manifestClass
     */
    public function installZip(string $zipPath, string $kind, string $manifestClass, string $targetBase, bool $replace = false): Manifest
    {
        $checked = $this->validator->validate($zipPath, $kind);

        $staging = $this->stagingDirectory();

        try {
            $zip = new ZipArchive;
            if ($zip->open($zipPath, ZipArchive::RDONLY) !== true) {
                throw new InvalidPackage('The file is not a readable zip archive.');
            }

            try {
                foreach ($checked['files'] as $name) {
                    $relative = substr($name, strlen($checked['root']));
                    $target = $staging.'/'.$relative;
                    $this->files->ensureDirectoryExists(dirname($target), 0755);

                    $in = $zip->getStream($name);
                    if ($in === false) {
                        throw new InvalidPackage("Could not read {$name} from the zip.");
                    }

                    $out = fopen($target, 'wb');
                    stream_copy_to_stream($in, $out);
                    fclose($in);
                    fclose($out);
                }
            } finally {
                $zip->close();
            }

            return $this->moveIntoPlace($staging, $manifestClass, $targetBase, $replace);
        } finally {
            $this->files->deleteDirectory($staging);
        }
    }

    /**
     * Install from a folder on this server (for example one copied over SSH).
     *
     * @param  'plugin'|'theme'  $kind
     * @param  class-string<Manifest>  $manifestClass
     */
    public function installDirectory(string $source, string $kind, string $manifestClass, string $targetBase, bool $replace = false): Manifest
    {
        $source = rtrim($source, '/');

        if (! is_dir($source)) {
            throw new InvalidPackage('The folder does not exist.');
        }

        // Re-use the zip rules by zipping the folder first: one set of checks for every install path.
        $zipPath = $this->stagingDirectory().'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $relative = substr($item->getPathname(), strlen($source) + 1);

            if ($item->isLink()) {
                $zip->close();
                @unlink($zipPath);
                throw new InvalidPackage("Symbolic links are not allowed ({$relative}).");
            }

            $item->isDir() ? $zip->addEmptyDir($relative) : $zip->addFile($item->getPathname(), $relative);
        }

        $zip->close();

        try {
            return $this->installZip($zipPath, $kind, $manifestClass, $targetBase, $replace);
        } finally {
            @unlink($zipPath);
        }
    }

    /**
     * @param  class-string<Manifest>  $manifestClass
     */
    private function moveIntoPlace(string $staging, string $manifestClass, string $targetBase, bool $replace): Manifest
    {
        $manifest = $manifestClass::fromDirectory($staging);
        $target = rtrim($targetBase, '/').'/'.$manifest->slug;

        $this->files->ensureDirectoryExists($targetBase, 0755);

        if (is_dir($target) && ! $replace) {
            throw new InvalidPackage("\"{$manifest->slug}\" is already installed. Choose \"replace\" to update it.");
        }

        $backup = null;
        if (is_dir($target)) {
            $backup = $target.'.old-'.Str::random(6);
            $this->files->moveDirectory($target, $backup);
        }

        if (! $this->files->moveDirectory($staging, $target) && ! $this->files->copyDirectory($staging, $target)) {
            if ($backup) {
                $this->files->moveDirectory($backup, $target);
            }

            throw new InvalidPackage('Could not write the package folder.');
        }

        if ($backup) {
            $this->files->deleteDirectory($backup);
        }

        return $manifestClass::fromDirectory($target);
    }

    private function stagingDirectory(): string
    {
        $dir = storage_path('app/press-staging/'.Str::uuid());
        $this->files->ensureDirectoryExists($dir, 0755);

        return $dir;
    }
}
