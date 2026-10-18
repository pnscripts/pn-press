<?php

namespace Tests\Feature\Press;

use App\Filament\Pages\Plugins;
use App\Models\User;
use App\Press\Packages\InvalidPackage;
use App\Press\Packages\Manifest;
use App\Press\Permissions;
use App\Press\Plugins\PluginManager;
use App\Press\Updates\UpdateChecker;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Concerns\BuildsPackages;
use Tests\TestCase;

class PluginDependenciesTest extends TestCase
{
    use BuildsPackages;
    use RefreshDatabase;

    private string $pluginsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pluginsPath = $this->tempDir();
        config([
            'press.plugins_path' => $this->pluginsPath,
            'press.themes_path' => $this->tempDir(),
            'press.updates.core_url' => 'https://updates.example.test/core.json',
        ]);
    }

    private function manager(): PluginManager
    {
        return app(PluginManager::class);
    }

    /** @param  array<string, mixed>  $extra */
    private function plugin(string $slug, string $version = '1.0.0', array $extra = []): void
    {
        File::ensureDirectoryExists("{$this->pluginsPath}/{$slug}");
        File::put("{$this->pluginsPath}/{$slug}/plugin.json", json_encode(['slug' => $slug, 'name' => ucfirst($slug), 'version' => $version] + $extra));
    }

    public function test_a_plugin_needs_its_dependencies_active_in_the_right_version(): void
    {
        $this->plugin('forms', '1.0.0');
        $this->plugin('payments', '1.0.0', ['requires' => ['plugins' => ['forms' => '>=1.2']]]);

        try {
            $this->manager()->activate('payments');
            $this->fail('payments must not activate without forms >= 1.2');
        } catch (InvalidPackage $e) {
            $this->assertStringContainsString('Forms >=1.2 (installed: 1.0.0)', $e->getMessage());
        }

        $this->plugin('forms', '1.3.0');
        app()->forgetInstance(PluginManager::class); // re-read the manifests

        try {
            $this->manager()->activate('payments');
            $this->fail('payments must not activate while forms is inactive');
        } catch (InvalidPackage $e) {
            $this->assertStringContainsString('Forms >=1.2 to be active', $e->getMessage());
        }

        $this->manager()->activate('forms');
        $this->manager()->activate('payments');
        $this->assertTrue($this->manager()->isActive('payments'));
        $this->assertSame(['forms', 'payments'], array_map(fn ($m) => $m->slug, $this->manager()->bootOrder()));
    }

    public function test_dependencies_cannot_be_deactivated_or_uninstalled_under_a_dependent(): void
    {
        $this->plugin('forms');
        $this->plugin('payments', '1.0.0', ['requires' => ['plugins' => ['forms' => '*']]]);
        $this->manager()->activate('forms');
        $this->manager()->activate('payments');

        try {
            $this->manager()->deactivate('forms');
            $this->fail('forms is needed by payments');
        } catch (InvalidPackage $e) {
            $this->assertStringContainsString('needed by Payments', $e->getMessage());
        }

        $this->manager()->deactivate('payments');
        $this->manager()->deactivate('forms');

        $this->expectException(InvalidPackage::class);
        $this->expectExceptionMessage('Uninstall those first');
        $this->manager()->uninstall('forms');
    }

    public function test_a_dependency_removed_behind_the_admins_back_skips_the_dependent_at_boot(): void
    {
        $this->plugin('forms');
        $this->plugin('payments', '1.0.0', ['requires' => ['plugins' => ['forms' => '*']]]);
        $this->manager()->activate('forms');
        $this->manager()->activate('payments');

        File::deleteDirectory("{$this->pluginsPath}/forms");
        app()->forgetInstance(PluginManager::class);

        $this->manager()->bootActive();

        $this->assertFalse($this->manager()->isLoaded('payments'));
        $this->assertStringContainsString('not installed', $this->manager()->bootProblems()['payments']);
    }

    public function test_server_requirements_and_manifest_validation(): void
    {
        $this->plugin('needs-ext', '1.0.0', ['requires' => ['php' => '>=99.0', 'extensions' => ['surely_missing_ext']]]);

        try {
            $this->manager()->activate('needs-ext');
            $this->fail('requirements are not met');
        } catch (InvalidPackage $e) {
            $this->assertStringContainsString('PHP >=99.0', $e->getMessage());
            $this->assertStringContainsString('surely_missing_ext', $e->getMessage());
        }

        $this->plugin('bad-deps', '1.0.0', ['requires' => ['plugins' => ['forms']]]);
        $this->plugin('self-dep', '1.0.0', ['requires' => ['plugins' => ['self-dep' => '*']]]);
        $this->plugin('bad-update', '1.0.0', ['update_url' => 'http://insecure.example.test/x.json']);
        app()->forgetInstance(PluginManager::class);
        $errors = $this->manager()->errors();
        $this->assertArrayHasKey('bad-deps', $errors);
        $this->assertArrayHasKey('self-dep', $errors);
        $this->assertStringContainsString('https://', $errors['bad-update']);

        $this->assertTrue(Manifest::satisfies('1.4.0', '^1.2'));
        $this->assertFalse(Manifest::satisfies('2.0.0', '^1.2'));
        $this->assertTrue(Manifest::satisfies('0.1.0', '*'));
    }

    public function test_update_notices_come_from_feeds_and_never_install_anything(): void
    {
        $this->plugin('forms', '1.0.0', ['update_url' => 'https://updates.example.test/forms.json']);
        $this->plugin('quiet', '1.0.0', ['update_url' => 'https://updates.example.test/quiet.json']);
        $this->plugin('broken', '1.0.0', ['update_url' => 'https://updates.example.test/broken.json']);

        Http::fake([
            'updates.example.test/core.json' => Http::response(['tag_name' => 'v9.1.0', 'html_url' => 'https://github.com/pnscripts/pn-press/releases/tag/v9.1.0']),
            'updates.example.test/forms.json' => Http::response(['version' => '1.4.0', 'url' => 'https://updates.example.test/forms/changelog']),
            'updates.example.test/quiet.json' => Http::response(['version' => '1.0.0']),
            'updates.example.test/broken.json' => Http::response('<html>not json</html>'),
        ]);

        $this->artisan('press:check-updates')->assertSuccessful();

        $available = app(UpdateChecker::class)->available();
        $this->assertSame(['core', 'plugin:forms'], array_keys($available));
        $this->assertSame('1.4.0', $available['plugin:forms']['version']);
        $this->assertSame('9.1.0', $available['core']['version']);
        Http::assertSentCount(4);
        Http::assertNotSent(fn ($request) => $request->method() !== 'GET');
        $this->assertFileDoesNotExist($this->pluginsPath.'/forms/.update');

        // Once the newer version is installed, the notice disappears without a new check.
        $this->plugin('forms', '1.4.0', ['update_url' => 'https://updates.example.test/forms.json']);
        app()->forgetInstance(PluginManager::class);
        app()->forgetInstance(UpdateChecker::class);
        $this->assertArrayNotHasKey('plugin:forms', app(UpdateChecker::class)->available());
    }

    public function test_the_plugins_page_shows_updates_and_missing_requirements(): void
    {
        $this->plugin('forms', '1.0.0', ['update_url' => 'https://updates.example.test/forms.json']);
        $this->plugin('payments', '1.0.0', ['requires' => ['plugins' => ['forms' => '*']]]);
        Http::fake(['*' => Http::response(['version' => '2.0.0'])]);
        app(UpdateChecker::class)->check();

        Permissions::sync();
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(Plugins::class)
            ->assertSee('2.0.0 available')
            ->assertSee('Requires Forms')
            ->assertSee('Needs the plugin Forms to be active')
            ->assertSee('PN Scripts Press 2.0.0 is available');

        $this->assertSame('2', Plugins::getNavigationBadge());
    }
}
