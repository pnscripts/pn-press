<?php

namespace App\Press\Plugins;

/**
 * PSR-4 autoloading for plugin folders, so plugins work without touching composer.json.
 */
class PluginAutoloader
{
    /** @var array<string, list<string>> */
    private static array $prefixes = [];

    private static bool $registered = false;

    public static function add(string $prefix, string $directory): void
    {
        self::$prefixes[$prefix][] = rtrim($directory, '/').'/';
        self::$prefixes[$prefix] = array_values(array_unique(self::$prefixes[$prefix]));

        if (! self::$registered) {
            spl_autoload_register([self::class, 'load']);
            self::$registered = true;
        }
    }

    public static function load(string $class): void
    {
        foreach (self::$prefixes as $prefix => $directories) {
            if (! str_starts_with($class, $prefix)) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($class, strlen($prefix))).'.php';

            foreach ($directories as $directory) {
                if (is_file($directory.$relative)) {
                    require_once $directory.$relative;

                    return;
                }
            }
        }
    }
}
