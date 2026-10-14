<?php

namespace Tests\Feature\Press;

use App\Press\Packages\InvalidPackage;
use App\Press\Packages\ZipValidator;
use Tests\Concerns\BuildsPackages;
use Tests\TestCase;

class ZipValidatorTest extends TestCase
{
    use BuildsPackages;

    private function validate(array $files, string $kind = 'plugin', array $modes = []): array
    {
        return (new ZipValidator)->validate($this->makeZip($files, $modes), $kind);
    }

    private function assertRejected(array $files, string $kind = 'plugin', string $contains = '', array $modes = []): void
    {
        try {
            $this->validate($files, $kind, $modes);
            $this->fail('The package should have been rejected.');
        } catch (InvalidPackage $e) {
            $this->assertStringContainsStringIgnoringCase($contains, $e->getMessage());
        }
    }

    public function test_a_valid_plugin_inside_one_folder_passes(): void
    {
        $result = $this->validate($this->pluginFiles()['files']);

        $this->assertSame('hello-press/', $result['root']);
        $this->assertContains('hello-press/plugin.json', $result['files']);
    }

    public function test_a_manifest_at_the_zip_root_passes(): void
    {
        $result = $this->validate(['plugin.json' => '{}', 'src/A.php' => '<?php']);

        $this->assertSame('', $result['root']);
    }

    public function test_path_traversal_is_rejected(): void
    {
        $this->assertRejected(['p/plugin.json' => '{}', 'p/../../evil.php' => '<?php'], 'plugin', 'unsafe path');
        $this->assertRejected(['p/plugin.json' => '{}', 'p/./x.php' => '<?php'], 'plugin', 'unsafe path');
    }

    public function test_absolute_and_windows_paths_are_rejected(): void
    {
        $this->assertRejected(['p/plugin.json' => '{}', '/etc/cron.d/x.txt' => 'x'], 'plugin', 'unsafe path');
        $this->assertRejected(['p/plugin.json' => '{}', 'C:/x.txt' => 'x'], 'plugin', 'unsafe path');
        $this->assertRejected(['p/plugin.json' => '{}', 'p\\..\\x.txt' => 'x'], 'plugin', 'unsafe path');
    }

    public function test_symlinks_are_rejected(): void
    {
        $this->assertRejected(['p/plugin.json' => '{}', 'p/link.txt' => '/etc/passwd'], 'plugin', 'symbolic link', ['p/link.txt' => 0120777]);
    }

    public function test_hidden_files_are_rejected(): void
    {
        $this->assertRejected(['p/plugin.json' => '{}', 'p/.htaccess' => 'x'], 'plugin', 'hidden');
        $this->assertRejected(['p/plugin.json' => '{}', 'p/.env' => 'x'], 'plugin', 'hidden');
    }

    public function test_file_types_outside_the_allowlist_are_rejected(): void
    {
        $this->assertRejected(['p/plugin.json' => '{}', 'p/run.sh' => 'x'], 'plugin', '.sh');
        $this->assertRejected(['p/plugin.json' => '{}', 'p/shell.phtml' => 'x'], 'plugin', '.phtml');
        $this->assertRejected(['p/plugin.json' => '{}', 'p/app.phar' => 'x'], 'plugin', '.phar');
    }

    public function test_themes_may_only_contain_php_as_blade_views(): void
    {
        $this->assertRejected(['t/theme.json' => '{}', 't/functions.php' => '<?php'], 'theme', 'blade');
        $this->assertSame('t/', $this->validate(['t/theme.json' => '{}', 't/views/a.blade.php' => 'x'], 'theme')['root']);
    }

    public function test_a_missing_manifest_or_files_outside_the_root_are_rejected(): void
    {
        $this->assertRejected(['p/readme.md' => 'x'], 'plugin', 'no plugin.json');
        $this->assertRejected(['p/plugin.json' => '{}', 'q/other.md' => 'x'], 'plugin', 'no plugin.json');
        $this->assertRejected(['t/plugin.json' => '{}'], 'theme', 'no theme.json');
    }

    public function test_size_and_entry_limits_are_enforced(): void
    {
        config(['press.uploads.max_entries' => 3]);
        $this->assertRejected(['p/plugin.json' => '{}', 'p/a.md' => '', 'p/b.md' => '', 'p/c.md' => ''], 'plugin', 'more than 3');

        config(['press.uploads.max_entries' => 2000, 'press.uploads.max_uncompressed_kb' => 1]);
        $this->assertRejected(['p/plugin.json' => '{}', 'p/big.txt' => str_repeat('a', 4096)], 'plugin', 'larger than');

        config(['press.uploads.max_uncompressed_kb' => 50 * 1024, 'press.uploads.max_zip_kb' => 1]);
        $this->assertRejected(['p/plugin.json' => '{}', 'p/noise.txt' => random_bytes(4096)], 'plugin', 'larger than 1 KB');
    }

    public function test_zip_bombs_are_rejected_by_compression_ratio(): void
    {
        $this->assertRejected(['p/plugin.json' => '{}', 'p/bomb.txt' => str_repeat("\0", 4 * 1024 * 1024)], 'plugin', 'compression ratio');
    }

    public function test_non_zip_files_are_rejected(): void
    {
        $path = $this->tempDir().'/fake.zip';
        file_put_contents($path, 'not a zip');

        $this->expectException(InvalidPackage::class);
        (new ZipValidator)->validate($path, 'plugin');
    }

    public function test_finder_junk_is_ignored(): void
    {
        $result = $this->validate(['p/plugin.json' => '{}', '__MACOSX/p/._plugin.json' => 'x', 'p/.DS_Store' => 'x']);

        $this->assertSame(['p/plugin.json'], $result['files']);
    }
}
