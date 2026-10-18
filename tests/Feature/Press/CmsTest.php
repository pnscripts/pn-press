<?php

namespace Tests\Feature\Press;

use App\Enums\PostStatus;
use App\Filament\Pages\SiteSettings;
use App\Filament\Resources\CmsPages\Pages\CreatePage;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use App\Press\Permissions;
use App\Press\Settings;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CmsTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        Permissions::sync();
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_a_published_page_renders_its_blocks_and_seo_tags(): void
    {
        Page::factory()->published()->create([
            'title' => 'Our studio',
            'slug' => 'studio',
            'meta_description' => 'All about the studio.',
            'blocks' => [
                ['type' => 'hero', 'data' => ['heading' => 'Hello hero', 'button_label' => 'Go', 'button_url' => 'javascript:alert(1)']],
                ['type' => 'quote', 'data' => ['text' => 'Quoted words', 'cite' => 'Someone']],
                ['type' => 'unknown_block', 'data' => ['x' => 'NOT RENDERED']],
            ],
        ]);

        $this->get('/studio')
            ->assertOk()
            ->assertSee('<title>Our studio · ', false)
            ->assertSee('<meta name="description" content="All about the studio.">', false)
            ->assertSee('Hello hero')
            ->assertSee('Quoted words')
            ->assertDontSee('NOT RENDERED')
            ->assertDontSee('javascript:alert(1)', false);
    }

    public function test_drafts_and_scheduled_pages_are_hidden_from_visitors_but_previewable(): void
    {
        Page::factory()->create(['slug' => 'draft-page', 'title' => 'Draft page']);
        Page::factory()->create(['slug' => 'later', 'status' => PostStatus::Published, 'published_at' => now()->addDay()]);

        $this->get('/draft-page')->assertNotFound();
        $this->get('/later')->assertNotFound();

        $this->actingAs($this->user('editor'))
            ->get('/draft-page')
            ->assertOk()
            ->assertSee('Preview: this page is not published.')
            ->assertSee('noindex', false);
    }

    public function test_child_pages_live_under_their_parent_path(): void
    {
        $parent = Page::factory()->published()->create(['slug' => 'services']);
        $child = Page::factory()->published()->create(['slug' => 'design', 'parent_id' => $parent->id, 'title' => 'Design work']);

        $this->assertSame('services/design', $child->path);
        $this->get('/services/design')->assertOk()->assertSee('Design work');

        $parent->update(['slug' => 'offer']);
        $this->assertSame('offer/design', $child->fresh()->path);
    }

    public function test_the_home_page_setting_puts_a_page_at_the_root(): void
    {
        $post = Post::factory()->published()->create(['title' => 'A blog post']);
        $this->get('/')->assertOk()->assertSee('A blog post');

        $page = Page::factory()->published()->create(['slug' => 'welcome', 'blocks' => [['type' => 'hero', 'data' => ['heading' => 'Front page hero']]]]);
        app(Settings::class)->set('reading.home_page_id', $page->id);

        $this->get('/')->assertOk()->assertSee('Front page hero');
        $this->get('/welcome')->assertRedirect('/');
        $this->get('/blog')->assertOk()->assertSee('A blog post');
    }

    public function test_saving_a_page_keeps_revisions_that_can_be_restored(): void
    {
        $page = Page::factory()->published()->create(['title' => 'First title']);

        $page->update(['title' => 'Second title']);
        $page->update(['title' => 'Third title']);
        $page->update(['sort_order' => 5]); // Not revisioned.

        $this->assertSame(2, $page->revisions()->count());
        $first = $page->revisions()->oldest('id')->first();
        $this->assertSame('First title', $first->data['title']);

        $page->restoreRevision($first);

        $this->assertSame('First title', $page->fresh()->title);
        $this->assertSame(3, $page->revisions()->count());
    }

    public function test_revisions_are_capped(): void
    {
        config(['press.revisions.keep' => 3]);
        $page = Page::factory()->create();

        foreach (range(1, 6) as $i) {
            $page->update(['title' => "Title {$i}"]);
        }

        $this->assertSame(3, $page->revisions()->count());
    }

    public function test_menus_render_and_skip_unpublished_targets(): void
    {
        $about = Page::factory()->published()->create(['slug' => 'about', 'title' => 'About us']);
        $draft = Page::factory()->create(['slug' => 'secret', 'title' => 'Secret page']);
        $menu = Menu::query()->create(['name' => 'Main', 'location' => 'primary']);
        $parent = $menu->items()->create(['label' => 'About us', 'type' => 'page', 'page_id' => $about->id, 'sort_order' => 1]);
        $parent->children()->create(['label' => 'Team', 'type' => 'url', 'url' => '/about#team']);
        $menu->items()->create(['label' => 'Secret page', 'type' => 'page', 'page_id' => $draft->id, 'sort_order' => 2]);
        $menu->items()->create(['label' => 'Bad link', 'type' => 'url', 'url' => 'javascript:alert(1)', 'sort_order' => 3]);
        $menu->items()->create(['label' => 'Journal', 'type' => 'blog', 'sort_order' => 4]);

        $this->get('/about')
            ->assertOk()
            ->assertSee('href="'.url('/about').'"', false)
            ->assertSee('/about#team', false)
            ->assertSee('Journal')
            ->assertDontSee('Secret page')
            ->assertDontSee('Bad link');
    }

    public function test_the_demo_site_seeds_pages_menus_theme_and_plugins(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->get('/')->assertOk()->assertSee('A small press for your own corner of the web')->assertSee('Main', false);
        $this->get('/about')->assertOk()->assertSee('How pages work');
        $this->get('/contact')->assertOk()->assertSee('Send a message');
        $this->get('/sitemap.xml')->assertOk()->assertSee(url('/about'), false)->assertSee('<urlset', false);
        $this->get('/blog')->assertSee('rel="sitemap"', false);
        $this->assertSame('folio', app(Settings::class)->get('theme.active'));

        // Re-seeding changes nothing.
        $this->seed(DatabaseSeeder::class);
        $this->assertSame(3, Page::query()->where('locale', 'en')->count());
        $this->assertSame(2, Menu::query()->whereNull('locale')->count());
        // Bulgarian demo (PRESS_LOCALES=en,bg): three linked pages and two menus of its own.
        $this->assertSame(3, Page::query()->where('locale', 'bg')->whereNotNull('translation_group')->count());
        $this->assertSame(2, Menu::query()->where('locale', 'bg')->count());
    }

    public function test_the_contact_form_plugin_stores_messages(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->from('/contact')->post('/contact-form/send', [
            'name' => 'Reader',
            'email' => 'reader@example.com',
            'message' => 'Hello there, nice site.',
        ])->assertRedirect();

        $this->assertDatabaseHas('contact_messages', ['email' => 'reader@example.com']);

        // Bots that fill the honeypot are thanked and ignored.
        $this->from('/contact')->post('/contact-form/send', ['website' => 'spam', 'name' => 'Bot', 'email' => 'bot@example.com', 'message' => 'Buy things now']);
        $this->assertDatabaseMissing('contact_messages', ['email' => 'bot@example.com']);
    }

    public function test_the_nocturne_theme_overrides_the_header(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs($this->user('admin'));

        $this->get('/blog?preview_theme=nocturne')
            ->assertSee('data-press-theme="nocturne"', false)
            ->assertSee('flex flex-col items-center gap-4 py-8 text-center', false);
    }

    public function test_admin_screens_render_for_admins(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::query()->where('email', 'admin@example.com')->first());
        $page = Page::query()->first();
        $menu = Menu::query()->first();

        foreach (['/admin/pages', '/admin/pages/create', "/admin/pages/{$page->id}/edit", '/admin/menus', "/admin/menus/{$menu->id}/edit",
            '/admin/media', '/admin/media/create', '/admin/users', '/admin/settings', '/admin/plugins', '/admin/themes'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_editors_manage_content_but_not_the_site(): void
    {
        $editor = $this->user('editor');
        $this->actingAs($editor);

        foreach (['/admin/pages', '/admin/media', '/admin/menus'] as $url) {
            $this->get($url)->assertOk();
        }

        foreach (['/admin/users', '/admin/settings', '/admin/plugins', '/admin/themes'] as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_settings_page_saves_values(): void
    {
        $this->actingAs($this->user('admin'));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(SiteSettings::class)
            ->fillForm(['site' => ['name' => 'Ink & Paper', 'tagline' => 'A tiny press'], 'reading' => ['posts_per_page' => 5]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Ink & Paper', app(Settings::class)->get('site.name'));
        $this->get('/blog')->assertSee('Ink &amp; Paper', false)->assertSee('A tiny press');
    }

    public function test_reserved_slugs_cannot_be_used_for_top_level_pages(): void
    {
        $this->actingAs($this->user('admin'));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(CreatePage::class)
            ->fillForm(['title' => 'Admin', 'slug' => 'admin', 'status' => 'draft', 'template' => 'default'])
            ->call('create')
            ->assertHasFormErrors(['slug']);

        Livewire::test(CreatePage::class)
            ->fillForm(['title' => 'Pricing', 'slug' => 'pricing', 'status' => 'published', 'template' => 'default'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertTrue(Page::query()->where('path', 'pricing')->exists());
    }
}
