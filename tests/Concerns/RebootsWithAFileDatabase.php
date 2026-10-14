<?php

namespace Tests\Concerns;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

/**
 * Some behaviour only shows on a fresh boot: the admin panel is built once per application, and a
 * route cache is read while the application boots. These tests keep their data in a temporary
 * SQLite file (never database/database.sqlite) and a temporary route cache path, so they can boot
 * the application again over the same data.
 */
trait RebootsWithAFileDatabase
{
    private string $scratchDir;

    /** @var array<string, array{0: mixed, 1: mixed, 2: string|false}> */
    private array $savedEnv = [];

    protected function useFileDatabase(): void
    {
        $this->scratchDir = sys_get_temp_dir().'/press-boot-'.Str::random(10);
        mkdir($this->scratchDir, 0700, true);
        touch($this->scratchDir.'/database.sqlite');

        $this->setEnv('DB_DATABASE', $this->scratchDir.'/database.sqlite');
        $this->setEnv('APP_ROUTES_CACHE', $this->scratchDir.'/routes.php');
    }

    protected function scratchPath(string $name): string
    {
        $path = $this->scratchDir.'/'.$name;
        is_dir($path) || mkdir($path, 0700, true);

        return $path;
    }

    protected function routeCachePath(): string
    {
        return $this->scratchDir.'/routes.php';
    }

    /** Boot a new application over the same database file (and route cache, if one was written). */
    protected function reboot(): void
    {
        $this->refreshApplication();
    }

    protected function restoreEnvironment(): void
    {
        foreach ($this->savedEnv as $key => [$env, $server, $putenv]) {
            if ($env === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $env;
            }

            if ($server === null) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $server;
            }

            putenv($putenv === false ? $key : "{$key}={$putenv}");
        }

        $this->savedEnv = [];

        // Runs after the application is gone, so no facades here.
        if (isset($this->scratchDir) && is_dir($this->scratchDir)) {
            (new Filesystem)->deleteDirectory($this->scratchDir);
        }
    }

    protected function setEnv(string $key, string $value): void
    {
        $this->savedEnv[$key] ??= [$_ENV[$key] ?? null, $_SERVER[$key] ?? null, getenv($key)];
        $_ENV[$key] = $_SERVER[$key] = $value;
        putenv("{$key}={$value}");
    }
}
