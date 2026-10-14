<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Temporary plugin/theme folders and zips for lifecycle tests. Never touches the repo's plugins/ or themes/.
 */
trait BuildsPackages
{
    /** @var list<string> */
    private array $tempDirs = [];

    protected function tempDir(): string
    {
        $dir = sys_get_temp_dir().'/press-test-'.Str::random(10);
        File::ensureDirectoryExists($dir);
        $this->tempDirs[] = $dir;

        return $dir;
    }

    protected function tearDownBuildsPackages(): void
    {
        foreach ($this->tempDirs as $dir) {
            File::deleteDirectory($dir);
        }
    }

    /**
     * @param  array<string, string>  $files  entry name => contents
     * @param  array<string, int>  $unixModes  entry name => st_mode (e.g. symlink 0120777)
     */
    protected function makeZip(array $files, array $unixModes = []): string
    {
        $path = $this->tempDir().'/package.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($files as $name => $contents) {
            str_ends_with($name, '/') ? $zip->addEmptyDir(rtrim($name, '/')) : $zip->addFromString($name, $contents);

            if (isset($unixModes[$name])) {
                $zip->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, $unixModes[$name] << 16);
            }
        }

        $zip->close();

        return $path;
    }

    /**
     * A working plugin with a provider, a hook, a route and a migration.
     *
     * @return array{files: array<string, string>, namespace: string, table: string}
     */
    protected function pluginFiles(string $slug = 'hello-press', string $version = '1.0.0'): array
    {
        $suffix = Str::studly(Str::random(8));
        $namespace = 'PressTest'.$suffix;
        $table = 'hello_'.strtolower($suffix);

        $files = [
            "{$slug}/plugin.json" => json_encode([
                'slug' => $slug,
                'name' => 'Hello Press',
                'version' => $version,
                'description' => 'Test plugin',
                'provider' => "{$namespace}\\HelloServiceProvider",
                'autoload' => ['psr-4' => ["{$namespace}\\" => 'src/']],
            ]),
            "{$slug}/src/HelloServiceProvider.php" => <<<PHP
<?php
namespace {$namespace};

use Illuminate\\Support\\Facades\\Route;
use Illuminate\\Support\\ServiceProvider;

class HelloServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        add_filter('test.greeting', fn (string \$value) => \$value.' from {$slug}');
        add_action('head', fn () => '<meta name="hello-press" content="{$slug}">');
        Route::middleware('web')->get('/hello-{$suffix}', fn () => 'hello route');
    }
}
PHP,
            "{$slug}/database/migrations/2026_01_01_000000_create_{$table}_table.php" => <<<PHP
<?php
use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void { Schema::create('{$table}', function (Blueprint \$t) { \$t->id(); }); }
    public function down(): void { Schema::dropIfExists('{$table}'); }
};
PHP,
            "{$slug}/README.md" => '# Hello',
        ];

        return ['files' => $files, 'namespace' => $namespace, 'table' => $table, 'suffix' => $suffix];
    }

    /** @return array<string, string> */
    protected function themeFiles(string $slug = 'paperback', string $marker = 'PAPERBACK-THEME-MARKER'): array
    {
        return [
            "{$slug}/theme.json" => json_encode([
                'slug' => $slug,
                'name' => 'Paperback',
                'version' => '1.2.0',
                'screenshot' => 'screenshot.svg',
                'stylesheet' => 'assets/theme.css',
                'templates' => ['wide' => 'Wide'],
                'settings' => [
                    ['key' => 'accent', 'label' => 'Accent', 'type' => 'color', 'default' => '#123456'],
                    ['key' => 'layout', 'label' => 'Layout', 'type' => 'select', 'default' => 'cozy', 'options' => ['cozy' => 'Cozy', 'airy' => 'Airy']],
                    ['key' => 'footer_note', 'label' => 'Footer note', 'type' => 'text', 'default' => 'Hi'],
                ],
            ]),
            "{$slug}/screenshot.svg" => '<svg xmlns="http://www.w3.org/2000/svg"/>',
            "{$slug}/assets/theme.css" => 'body{}',
            "{$slug}/views/posts/index.blade.php" => "@extends('layouts.app') @section('content') {$marker} @endsection",
        ];
    }
}
