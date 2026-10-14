<?php

namespace Tests\Feature\Press;

use App\Models\Post;
use App\Models\User;
use App\Press\Packages\InvalidPackage;
use App\Press\Permissions;
use App\Press\Settings;
use App\Press\Themes\ThemeManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPackages;
use Tests\TestCase;

class ThemeLifecycleTest extends TestCase
{
    use BuildsPackages;
    use RefreshDatabase;

    private string $themesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->themesPath = $this->tempDir();
        config(['press.themes_path' => $this->themesPath, 'press.default_theme' => 'none']);
    }

    private function manager(): ThemeManager
    {
        return app(ThemeManager::class);
    }

    private function admin(): User
    {
        Permissions::sync();
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_install_activate_override_views_and_delete(): void
    {
        $theme = $this->manager()->installZip($this->makeZip($this->themeFiles()));
        $this->assertSame('paperback', $theme->slug);
        $this->assertSame('1.2.0', $theme->version);

        $this->get('/blog')->assertOk()->assertDontSee('PAPERBACK-THEME-MARKER');

        $this->manager()->activate('paperback');
        $this->assertSame('paperback', app(Settings::class)->get('theme.active'));

        $this->get('/blog')
            ->assertOk()
            ->assertSee('PAPERBACK-THEME-MARKER')
            ->assertSee('data-press-theme="paperback"', false)
            ->assertSee('data-press-layout="cozy"', false)
            ->assertSee('--press-accent:#123456', false)
            ->assertSee('/theme-assets/paperback/assets/theme.css', false);

        // Views the theme does not override still come from the core.
        $post = Post::factory()->published()->create(['title' => 'Core view still works']);
        $this->get('/blog/'.$post->slug)->assertOk()->assertSee('Core view still works');

        // The active theme cannot be deleted; another one can.
        $this->manager()->installZip($this->makeZip($this->themeFiles('second', 'SECOND')));
        $this->expectException(InvalidPackage::class);
        try {
            $this->manager()->uninstall('paperback');
        } finally {
            $this->manager()->uninstall('second');
            $this->assertDirectoryDoesNotExist($this->themesPath.'/second');
        }
    }

    public function test_only_theme_managers_can_preview_a_theme(): void
    {
        $this->manager()->installZip($this->makeZip($this->themeFiles()));

        $this->get('/blog?preview_theme=paperback')->assertDontSee('PAPERBACK-THEME-MARKER');

        $this->actingAs($this->admin())->get('/blog?preview_theme=paperback')->assertSee('PAPERBACK-THEME-MARKER');

        $this->assertNull(app(Settings::class)->get('theme.active'));
    }

    public function test_settings_are_sanitised_before_they_reach_the_page(): void
    {
        $theme = $this->manager()->installZip($this->makeZip($this->themeFiles()));
        $this->manager()->activate('paperback');

        $this->manager()->saveSettings($theme, [
            'accent' => 'red;}</style><script>alert(1)</script>',
            'layout' => 'not-an-option',
            'footer_note' => '  hello  ',
        ]);

        $this->assertSame(['accent' => '#123456', 'layout' => 'cozy', 'footer_note' => 'hello'], $this->manager()->settingsFor($theme));
        $this->get('/blog')->assertDontSee('<script>alert(1)</script>', false);

        $this->manager()->saveSettings($theme, ['accent' => '#ABCDEF', 'layout' => 'airy']);
        $this->get('/blog')->assertSee('--press-accent:#abcdef', false)->assertSee('data-press-layout="airy"', false);
    }

    public function test_theme_templates_are_offered_for_pages(): void
    {
        $this->manager()->installZip($this->makeZip($this->themeFiles()));
        $this->manager()->activate('paperback');

        $this->assertArrayHasKey('wide', $this->manager()->templates());
        $this->assertArrayHasKey('default', $this->manager()->templates());
    }

    public function test_theme_assets_are_served_safely(): void
    {
        $this->manager()->installZip($this->makeZip($this->themeFiles() + ['paperback/views/secret.blade.php' => 'x']));

        $this->get('/theme-assets/paperback/assets/theme.css')->assertOk()->assertHeader('Content-Type', 'text/css; charset=UTF-8');
        $this->get('/theme-assets/paperback/screenshot.svg')->assertOk()->assertHeader('Content-Security-Policy');
        $this->get('/theme-assets/paperback/views/secret.blade.php')->assertNotFound();
        $this->get('/theme-assets/paperback/theme.json')->assertNotFound();
        $this->get('/theme-assets/paperback/assets/../theme.json')->assertNotFound();
        $this->get('/theme-assets/paperback/assets/%2e%2e/theme.json')->assertNotFound();
        $this->get('/theme-assets/missing/assets/theme.css')->assertNotFound();
    }

    public function test_a_theme_with_php_outside_views_is_refused(): void
    {
        $this->expectException(InvalidPackage::class);

        $this->manager()->installZip($this->makeZip($this->themeFiles() + ['paperback/functions.php' => '<?php']));
    }

    public function test_only_theme_managers_reach_the_themes_page(): void
    {
        Permissions::sync();
        $editor = User::factory()->create();
        $editor->assignRole('editor');
        $this->actingAs($editor)->get('/admin/themes')->assertForbidden();

        $this->manager()->installZip($this->makeZip($this->themeFiles()));
        $this->actingAs($this->admin())->get('/admin/themes')->assertOk()->assertSee('Paperback');
    }
}
