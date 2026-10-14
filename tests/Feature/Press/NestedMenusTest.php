<?php

namespace Tests\Feature\Press;

use App\Filament\Resources\Menus\Pages\CreateMenu;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\User;
use App\Press\Menus;
use App\Press\Permissions;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NestedMenusTest extends TestCase
{
    use RefreshDatabase;

    /** Main › Guides › Basics › Deep › Too deep (four levels below the menu bar). */
    private function deepMenu(): Menu
    {
        $menu = Menu::query()->create(['name' => 'Main', 'location' => 'primary']);
        $guides = $menu->items()->create(['label' => 'Guides', 'type' => 'url', 'url' => '/guides', 'sort_order' => 1]);
        $basics = $guides->children()->create(['label' => 'Basics', 'type' => 'url', 'url' => '/guides/basics', 'sort_order' => 1]);
        $guides->children()->create(['label' => 'Advanced', 'type' => 'url', 'url' => '/guides/advanced', 'sort_order' => 2]);
        $deep = $basics->children()->create(['label' => 'Deep link', 'type' => 'page', 'page_id' => Page::factory()->published()->create(['slug' => 'deep'])->id]);
        $deep->children()->create(['label' => 'Too deep', 'type' => 'url', 'url' => '/too-deep']);
        $menu->items()->create(['label' => 'Journal', 'type' => 'blog', 'sort_order' => 2]);

        return $menu;
    }

    public function test_menus_nest_down_to_the_configured_depth(): void
    {
        $this->deepMenu();

        $tree = app(Menus::class)->tree('primary');

        $this->assertSame(['Guides', 'Journal'], array_column($tree, 'label'));
        $this->assertSame(['Basics', 'Advanced'], array_column($tree[0]['children'], 'label'));
        $this->assertSame(['Deep link'], array_column($tree[0]['children'][0]['children'], 'label'));
        $this->assertSame([], $tree[0]['children'][0]['children'][0]['children'], 'Level 4 is beyond press.menus.max_depth = 3.');

        config(['press.menus.max_depth' => 4]);
        app()->forgetInstance(Menus::class);
        $this->assertSame('Too deep', app(Menus::class)->tree('primary')[0]['children'][0]['children'][0]['children'][0]['label']);
    }

    public function test_nested_dropdowns_render_as_keyboard_reachable_disclosures(): void
    {
        $this->deepMenu();

        $html = $this->get('/deep')->assertOk()->getContent();

        $this->assertStringContainsString('data-press-nav', $html);
        $this->assertSame(2, substr_count($html, '<details class="group/menu relative" data-press-menu'), 'Guides and Basics open menus; Deep link is a link.');
        $this->assertStringContainsString('data-level="1"', $html);
        $this->assertStringContainsString('href="'.url('/deep').'"', $html);
        $this->assertStringNotContainsString('/too-deep', $html);
        $this->assertMatchesRegularExpression('#aria-current="page"[^>]*>Deep link</a>|href="'.preg_quote(url('/deep'), '#').'"[^>]*aria-current="page"#s', $html);
        // The menu script handles Escape and the arrow keys.
        $this->assertStringContainsString("case 'Escape'", $html);
        $this->assertStringContainsString("case 'ArrowRight'", $html);
    }

    public function test_the_current_pages_ancestors_are_marked_active(): void
    {
        $this->deepMenu();

        $tree = app(Menus::class)->tree('primary');
        $this->assertFalse($tree[0]['active']);

        app()->forgetInstance(Menus::class);
        $this->app->instance('request', request()->create(url('/deep')));
        $tree = app(Menus::class)->tree('primary');

        $this->assertTrue($tree[0]['active'], 'Guides contains the current page.');
        $this->assertFalse($tree[0]['current']);
        $this->assertTrue($tree[0]['children'][0]['children'][0]['current']);
        $this->assertFalse($tree[1]['active']);
    }

    public function test_the_admin_saves_three_levels(): void
    {
        Permissions::sync();
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $undoRepeaterFake = Repeater::fake();

        Livewire::test(CreateMenu::class)
            ->fillForm([
                'name' => 'Docs',
                'location' => 'footer',
                'items' => [[
                    'label' => 'Docs', 'type' => 'url', 'url' => '/docs', 'open_in_new_tab' => false,
                    'children' => [[
                        'label' => 'Install', 'type' => 'url', 'url' => '/docs/install', 'open_in_new_tab' => false,
                        'children' => [[
                            'label' => 'On a server', 'type' => 'url', 'url' => '/docs/install/server', 'open_in_new_tab' => false,
                        ]],
                    ]],
                ]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $undoRepeaterFake();

        $leaf = MenuItem::query()->where('label', 'On a server')->firstOrFail();
        $this->assertSame('Install', $leaf->parent->label);
        $this->assertSame('Docs', $leaf->parent->parent->label);
        $this->assertSame('Docs', $leaf->parent->parent->menu->name);
        $this->get('/admin/menus/'.$leaf->parent->parent->menu_id.'/edit')->assertOk()->assertSee('Sub-items (level 3)');
    }
}
