<?php

namespace Tests\Feature\Press;

use App\Press\Packages\InvalidPackage;
use App\Press\Themes\ThemeManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPackages;
use Tests\TestCase;

class ChildThemeTest extends TestCase
{
    use BuildsPackages;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['press.themes_path' => $this->tempDir(), 'press.default_theme' => 'none']);
    }

    private function manager(): ThemeManager
    {
        return app(ThemeManager::class);
    }

    /** @return array<string, string> */
    private function childFiles(string $parent = 'paperback'): array
    {
        return [
            'paperback-kid/theme.json' => json_encode([
                'slug' => 'paperback-kid',
                'name' => 'Paperback Kid',
                'version' => '1.0.0',
                'parent' => $parent,
                'stylesheet' => 'assets/child.css',
                'templates' => ['gallery' => 'Gallery'],
                'settings' => [
                    ['key' => 'accent', 'label' => 'Accent', 'type' => 'color', 'default' => '#abcdef'],
                    ['key' => 'badge', 'label' => 'Badge', 'type' => 'toggle', 'default' => true],
                ],
            ]),
            'paperback-kid/assets/child.css' => 'h1{}',
            'paperback-kid/views/categories/show.blade.php' => "@extends('layouts.app') @section('content') KID-CATEGORY-MARKER @endsection",
        ];
    }

    public function test_a_child_theme_inherits_views_assets_templates_and_settings_from_its_parent(): void
    {
        $this->manager()->installZip($this->makeZip($this->themeFiles()));
        $this->manager()->installZip($this->makeZip($this->childFiles()));
        $this->manager()->activate('paperback-kid');

        // The parent's view override still applies; the child adds its own.
        $this->get('/blog')
            ->assertOk()
            ->assertSee('PAPERBACK-THEME-MARKER')
            ->assertSee('data-press-theme="paperback-kid"', false)
            ->assertSee('data-press-layout="cozy"', false)   // parent setting
            ->assertSee('data-press-badge="on"', false)      // child setting
            ->assertSee('--press-accent:#abcdef', false)     // child overrides the parent default
            ->assertSeeInOrder(['theme-assets/paperback/assets/theme.css', 'theme-assets/paperback-kid/assets/child.css'], false);

        // Assets the child does not ship come from the parent; the child's own come from the child.
        $this->get('/theme-assets/paperback-kid/assets/theme.css')->assertOk();
        $this->get('/theme-assets/paperback-kid/assets/child.css')->assertOk();
        $this->get('/theme-assets/paperback-kid/assets/../../paperback/theme.json')->assertNotFound();
        $this->assertStringContainsString('/theme-assets/paperback-kid/assets/logo.svg', theme_asset('assets/logo.svg'));

        $this->assertArrayHasKey('wide', $this->manager()->templates());
        $this->assertArrayHasKey('gallery', $this->manager()->templates());
        $this->assertSame(['accent', 'layout', 'footer_note', 'badge'], array_column($this->manager()->fields($this->manager()->find('paperback-kid')), 'key'));
    }

    public function test_a_child_without_its_parent_cannot_be_activated_and_the_site_keeps_working(): void
    {
        $this->manager()->installZip($this->makeZip($this->childFiles('missing-parent')));

        $kid = $this->manager()->find('paperback-kid');
        $this->assertNotNull($kid);
        $this->assertStringContainsString('not installed', (string) $this->manager()->problem($kid));

        try {
            $this->manager()->activate('paperback-kid');
            $this->fail('Activation should fail without the parent.');
        } catch (InvalidPackage $e) {
            $this->assertStringContainsString('Install the parent theme first', $e->getMessage());
        }

        $this->get('/blog')->assertOk();
    }

    public function test_a_parent_with_children_cannot_be_deleted_and_parents_cannot_nest(): void
    {
        $this->manager()->installZip($this->makeZip($this->themeFiles()));
        $this->manager()->installZip($this->makeZip($this->childFiles()));

        $this->expectException(InvalidPackage::class);
        $this->expectExceptionMessage('Delete the child themes first');

        try {
            $grandchild = json_decode($this->childFiles('paperback-kid')['paperback-kid/theme.json'], true);
            $grandchild['slug'] = 'grandkid';
            $this->manager()->installZip($this->makeZip(['grandkid/theme.json' => json_encode($grandchild)]));
            $this->assertStringContainsString('itself a child theme', (string) $this->manager()->problem($this->manager()->find('grandkid')));
        } finally {
            $this->manager()->uninstall('paperback');
        }
    }

    public function test_a_theme_cannot_be_its_own_parent(): void
    {
        $this->expectException(InvalidPackage::class);
        $this->expectExceptionMessage('"parent" must be the slug of another theme');

        $files = $this->childFiles();
        $data = json_decode($files['paperback-kid/theme.json'], true);
        $data['parent'] = 'paperback-kid';
        $this->manager()->installZip($this->makeZip(['paperback-kid/theme.json' => json_encode($data)]));
    }
}
