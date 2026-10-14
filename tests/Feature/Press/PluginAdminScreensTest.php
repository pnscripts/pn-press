<?php

namespace Tests\Feature\Press;

use App\Models\User;
use App\Press\Permissions;
use App\Press\Plugins\PluginManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\Concerns\RebootsWithAFileDatabase;
use Tests\TestCase;

/**
 * A plugin's "admin" class can add Filament pages and navigation items, not only resources.
 */
class PluginAdminScreensTest extends TestCase
{
    use RebootsWithAFileDatabase;

    private string $suffix;

    protected function setUp(): void
    {
        $this->useFileDatabase();
        $this->suffix = Str::studly(Str::random(8));
        $pluginsPath = $this->scratchPath('plugins');
        $this->writePlugin($pluginsPath.'/admin-extras');
        $this->setEnv('PRESS_PLUGINS_PATH', $pluginsPath);

        parent::setUp();

        Artisan::call('migrate', ['--force' => true]);
        Permissions::sync();
        app(PluginManager::class)->activate('admin-extras');
        $this->reboot();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->restoreEnvironment();
    }

    public function test_plugin_pages_and_navigation_items_appear_in_the_panel(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $this->get('/admin')
            ->assertOk()
            ->assertSee('Extras report')
            ->assertSee('Plugin help')
            ->assertSee('https://example.com/plugin-help', false);

        $this->get('/admin/extras-report')->assertOk()->assertSee('Rendered by a plugin page '.$this->suffix);

        $editor = User::factory()->create();
        $editor->assignRole('editor');
        $this->actingAs($editor)->get('/admin/extras-report')->assertForbidden();
    }

    private function writePlugin(string $dir): void
    {
        $ns = 'AdminExtras'.$this->suffix;
        mkdir($dir.'/src', 0700, true);

        file_put_contents($dir.'/plugin.json', json_encode([
            'slug' => 'admin-extras', 'name' => 'Admin Extras', 'version' => '1.0.0',
            'autoload' => ['psr-4' => ["{$ns}\\" => 'src/']],
            'admin' => "{$ns}\\ExtrasAdmin",
        ]));

        file_put_contents($dir.'/src/ExtrasAdmin.php', <<<PHP
<?php
namespace {$ns};

use Filament\\Contracts\\Plugin;
use Filament\\Navigation\\NavigationItem;
use Filament\\Panel;

class ExtrasAdmin implements Plugin
{
    public function getId(): string { return 'admin-extras'; }

    public function register(Panel \$panel): void
    {
        \$panel->pages([ExtrasReport::class])->navigationItems([
            NavigationItem::make('Plugin help')->url('https://example.com/plugin-help', shouldOpenInNewTab: true)
                ->icon('heroicon-o-question-mark-circle')->group('Extend'),
        ]);
    }

    public function boot(Panel \$panel): void {}
}
PHP);

        file_put_contents($dir.'/src/ExtrasReport.php', <<<PHP
<?php
namespace {$ns};

use App\\Press\\Permissions;
use Filament\\Pages\\Page;
use Filament\\Schemas\\Components\\Text;
use Filament\\Schemas\\Schema;

class ExtrasReport extends Page
{
    protected static ?string \$slug = 'extras-report';
    protected static ?string \$title = 'Extras report';
    protected static string|\\UnitEnum|null \$navigationGroup = 'Extend';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(Permissions::MANAGE_SETTINGS);
    }

    public function content(Schema \$schema): Schema
    {
        return \$schema->components([Text::make('Rendered by a plugin page {$this->suffix}')]);
    }
}
PHP);
    }
}
