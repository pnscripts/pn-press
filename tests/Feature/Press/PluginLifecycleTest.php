<?php

namespace Tests\Feature\Press;

use App\Filament\Pages\Plugins;
use App\Models\Plugin;
use App\Models\User;
use App\Press\Hooks;
use App\Press\Packages\InvalidPackage;
use App\Press\Permissions;
use App\Press\Plugins\PluginManager;
use App\Press\Settings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Concerns\BuildsPackages;
use Tests\TestCase;

class PluginLifecycleTest extends TestCase
{
    use BuildsPackages;
    use RefreshDatabase;

    private string $pluginsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pluginsPath = $this->tempDir();
        config(['press.plugins_path' => $this->pluginsPath]);
    }

    private function manager(): PluginManager
    {
        return app(PluginManager::class);
    }

    public function test_install_activate_deactivate_and_uninstall_from_a_zip(): void
    {
        $plugin = $this->pluginFiles();
        $manifest = $this->manager()->installZip($this->makeZip($plugin['files']));

        $this->assertSame('hello-press', $manifest->slug);
        $this->assertFileExists($this->pluginsPath.'/hello-press/plugin.json');
        $this->assertArrayHasKey('hello-press', $this->manager()->all());
        $this->assertFalse($this->manager()->isActive('hello-press'));
        $this->assertFalse(Schema::hasTable($plugin['table']));

        // Activate: migrations run, the provider boots, hooks and routes work.
        $this->manager()->activate('hello-press');

        $this->assertTrue($this->manager()->isActive('hello-press'));
        $this->assertTrue(Schema::hasTable($plugin['table']));
        $this->assertSame('hi from hello-press', app(Hooks::class)->applyFilters('test.greeting', 'hi'));
        $this->get('/hello-'.$plugin['suffix'])->assertOk()->assertSee('hello route');
        $this->get('/blog')->assertSee('<meta name="hello-press" content="hello-press">', false);
        $this->assertDatabaseHas('plugins', ['slug' => 'hello-press', 'enabled' => true, 'version' => '1.0.0']);

        // Deactivate: data stays, the plugin is no longer loaded on the next request.
        $this->manager()->deactivate('hello-press');
        $this->assertFalse($this->manager()->isActive('hello-press'));
        $this->assertTrue(Schema::hasTable($plugin['table']));
        $this->assertFalse($this->freshManager()->isActive('hello-press'));

        // Uninstall: tables rolled back, settings and folder removed.
        app(Settings::class)->set('plugin.hello-press.colour', 'red');
        $this->manager()->uninstall('hello-press');

        $this->assertFalse(Schema::hasTable($plugin['table']));
        $this->assertDirectoryDoesNotExist($this->pluginsPath.'/hello-press');
        $this->assertDatabaseMissing('plugins', ['slug' => 'hello-press']);
        $this->assertDatabaseMissing('settings', ['key' => 'plugin.hello-press.colour']);
    }

    public function test_active_plugins_are_loaded_on_boot(): void
    {
        $plugin = $this->pluginFiles();
        $this->manager()->installZip($this->makeZip($plugin['files']));
        $this->manager()->activate('hello-press');

        $fresh = $this->freshManager();
        $fresh->bootActive();

        $this->assertTrue($fresh->isLoaded('hello-press'));
    }

    public function test_installing_twice_needs_replace_and_replace_updates_the_version(): void
    {
        $this->manager()->installZip($this->makeZip($this->pluginFiles()['files']));

        try {
            $this->manager()->installZip($this->makeZip($this->pluginFiles()['files']));
            $this->fail('A second install without replace must fail.');
        } catch (InvalidPackage $e) {
            $this->assertStringContainsString('already installed', $e->getMessage());
        }

        $updated = $this->manager()->installZip($this->makeZip($this->pluginFiles('hello-press', '1.1.0')['files']), replace: true);

        $this->assertSame('1.1.0', $updated->version);
        $this->assertSame([], glob($this->pluginsPath.'/*.old-*'));
    }

    public function test_install_from_a_local_folder(): void
    {
        $source = $this->tempDir();
        foreach ($this->pluginFiles('folder-plugin')['files'] as $name => $contents) {
            File::ensureDirectoryExists(dirname($source.'/'.$name));
            File::put($source.'/'.$name, $contents);
        }

        $manifest = $this->manager()->installDirectory($source.'/folder-plugin');

        $this->assertSame('folder-plugin', $manifest->slug);
        $this->assertFileExists($this->pluginsPath.'/folder-plugin/src/HelloServiceProvider.php');
    }

    public function test_invalid_manifests_are_reported_not_loaded(): void
    {
        File::ensureDirectoryExists($this->pluginsPath.'/broken');
        File::put($this->pluginsPath.'/broken/plugin.json', '{"slug": "Bad Slug!"}');
        File::ensureDirectoryExists($this->pluginsPath.'/renamed');
        File::put($this->pluginsPath.'/renamed/plugin.json', json_encode(['slug' => 'other', 'name' => 'X', 'version' => '1.0.0']));

        $this->assertSame([], $this->manager()->all());
        $this->assertArrayHasKey('broken', $this->manager()->errors());
        $this->assertStringContainsString('folder name', $this->manager()->errors()['renamed']);
    }

    public function test_manifests_cannot_escape_the_plugin_folder_or_claim_core_namespaces(): void
    {
        $base = ['slug' => 'evil', 'name' => 'Evil', 'version' => '1.0.0'];

        foreach ([
            ['migrations' => '../../database/migrations'],
            ['autoload' => ['psr-4' => ['App\\' => 'src/']]],
            ['autoload' => ['psr-4' => ['Evil\\' => '../src']]],
            ['provider' => 'not a class'],
            ['requires' => ['press' => 'latest']],
        ] as $extra) {
            try {
                $this->manager()->installZip($this->makeZip(['evil/plugin.json' => json_encode($base + $extra)]));
                $this->fail('Manifest should be rejected: '.json_encode($extra));
            } catch (InvalidPackage) {
                $this->assertDirectoryDoesNotExist($this->pluginsPath.'/evil');
            }
        }
    }

    public function test_incompatible_plugins_cannot_be_activated(): void
    {
        $this->manager()->installZip($this->makeZip(['future/plugin.json' => json_encode([
            'slug' => 'future', 'name' => 'Future', 'version' => '1.0.0', 'requires' => ['press' => '>=9.0'],
        ])]));

        $this->expectException(InvalidPackage::class);
        $this->manager()->activate('future');
    }

    public function test_only_users_who_can_manage_plugins_see_the_plugins_page(): void
    {
        Permissions::sync();

        $editor = User::factory()->create();
        $editor->assignRole('editor');
        $this->actingAs($editor)->get('/admin/plugins')->assertForbidden();

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->manager()->installZip($this->makeZip($this->pluginFiles()['files']));
        $this->actingAs($admin)->get('/admin/plugins')->assertOk()->assertSee('Hello Press');
    }

    public function test_the_admin_page_activates_and_uninstalls(): void
    {
        Permissions::sync();
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $plugin = $this->pluginFiles();
        $this->manager()->installZip($this->makeZip($plugin['files']));

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(Plugins::class)
            ->callTableAction('activate', 'hello-press');
        $this->assertTrue(Plugin::query()->where('slug', 'hello-press')->value('enabled'));

        Livewire::test(Plugins::class)
            ->callTableAction('uninstall', 'hello-press');
        $this->assertDirectoryDoesNotExist($this->pluginsPath.'/hello-press');
    }

    public function test_the_artisan_command_installs_and_activates(): void
    {
        $zip = $this->makeZip($this->pluginFiles()['files']);

        $this->artisan('press:plugin', ['action' => 'install', 'target' => $zip])->assertSuccessful();
        $this->artisan('press:plugin', ['action' => 'activate', 'target' => 'hello-press'])->assertSuccessful();
        $this->artisan('press:plugin', ['action' => 'list'])->expectsOutputToContain('active')->assertSuccessful();
        $this->artisan('press:plugin', ['action' => 'install', 'target' => $zip])->assertFailed();
        $this->artisan('press:plugin', ['action' => 'uninstall', 'target' => 'hello-press'])
            ->expectsConfirmation('Uninstall hello-press? Its tables, settings and folder are deleted.', 'yes')
            ->assertSuccessful();
        $this->assertDirectoryDoesNotExist($this->pluginsPath.'/hello-press');
    }

    public function test_route_files_load_on_activation_and_answer_404_once_deactivated(): void
    {
        $plugin = $this->pluginFiles();
        $files = $plugin['files'];
        $files['hello-press/routes/web.php'] = "<?php\nuse Illuminate\\Support\\Facades\\Route;\nRoute::get('/route-file-{$plugin['suffix']}', fn () => 'from the route file')->name('hello-press.page');\n";
        $this->manager()->installZip($this->makeZip($files));

        $this->manager()->activate('hello-press');

        $this->assertSame(url('/route-file-'.$plugin['suffix']), route('hello-press.page'));
        $this->get('/route-file-'.$plugin['suffix'])->assertOk()->assertSee('from the route file');

        // The route stays registered for the rest of this request (and in a stale route cache),
        // but the press.plugin middleware refuses it.
        $this->manager()->deactivate('hello-press');
        $this->get('/route-file-'.$plugin['suffix'])->assertNotFound();
    }

    public function test_a_replaced_plugin_runs_its_new_migrations_on_the_next_request(): void
    {
        $plugin = $this->pluginFiles();
        $this->manager()->installZip($this->makeZip($plugin['files']));
        $this->manager()->activate('hello-press');

        // Same code (PHP cannot load a class twice in one process), new version and a new migration.
        $files = $plugin['files'];
        $files['hello-press/plugin.json'] = str_replace('"1.0.0"', '"1.1.0"', $files['hello-press/plugin.json']);
        $extra = $plugin['table'].'_extra';
        $files["hello-press/database/migrations/2026_02_01_000000_create_{$extra}_table.php"] = "<?php\nuse Illuminate\\Database\\Migrations\\Migration;\nuse Illuminate\\Support\\Facades\\Schema;\nreturn new class extends Migration { public function up(): void { Schema::create('{$extra}', fn (\$t) => \$t->id()); } public function down(): void { Schema::dropIfExists('{$extra}'); } };\n";
        $this->manager()->installZip($this->makeZip($files), replace: true);
        $this->assertFalse(Schema::hasTable($extra));

        $this->freshManager()->bootActive();

        $this->assertTrue(Schema::hasTable($extra));
        $this->assertDatabaseHas('plugins', ['slug' => 'hello-press', 'version' => '1.1.0']);
    }

    public function test_admin_classes_must_be_filament_plugins(): void
    {
        $plugin = $this->pluginFiles();
        $files = $plugin['files'];
        $manifest = json_decode($files['hello-press/plugin.json'], true);
        $manifest['admin'] = $plugin['namespace'].'\\HelloServiceProvider';
        $files['hello-press/plugin.json'] = json_encode($manifest);
        $this->manager()->installZip($this->makeZip($files));
        $this->manager()->activate('hello-press');

        $this->assertSame([], $this->freshManager()->filamentPlugins());

        $manifest['admin'] = 'not a class';
        File::put($this->pluginsPath.'/hello-press/plugin.json', json_encode($manifest));
        $this->assertStringContainsString('admin', $this->freshManager()->errors()['hello-press']);
    }

    private function freshManager(): PluginManager
    {
        app()->forgetInstance(PluginManager::class);

        return app(PluginManager::class);
    }
}
