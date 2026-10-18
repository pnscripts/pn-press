<?php

namespace Tests\Feature\Press;

use App\Enums\PostStatus;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use App\Press\Cache\PageCache;
use App\Press\Permissions;
use App\Press\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PageCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['press.page_cache.enabled' => true, 'press.page_cache.ttl' => 3600]);
    }

    public function test_anonymous_visitors_get_the_second_view_from_the_cache(): void
    {
        Page::factory()->published()->create(['slug' => 'about', 'title' => 'About us']);

        $this->get('/about')->assertOk()->assertHeader('X-Press-Cache', 'MISS');
        $this->get('/about')->assertOk()->assertHeader('X-Press-Cache', 'HIT')->assertSee('About us')->assertSee('</html>', false);
        $this->get('/about?utm_source=x')->assertOk()->assertHeaderMissing('X-Press-Cache');
        $this->get('/blog?page=1')->assertHeader('X-Press-Cache', 'MISS');
        $this->get('/blog?page=1')->assertHeader('X-Press-Cache', 'HIT');
    }

    public function test_editing_content_flushes_the_cache(): void
    {
        $page = Page::factory()->published()->create(['slug' => 'about', 'title' => 'About us']);
        $this->get('/about');
        $this->get('/about')->assertHeader('X-Press-Cache', 'HIT');

        $page->update(['title' => 'About the team']);

        $this->get('/about')->assertHeader('X-Press-Cache', 'MISS')->assertSee('About the team');
    }

    public function test_a_scheduled_post_appears_on_time_even_with_cached_pages(): void
    {
        Post::factory()->create([
            'title' => 'Launch announcement',
            'slug' => 'launch',
            'status' => PostStatus::Published,
            'published_at' => now()->addMinutes(10),
        ]);

        $this->assertLessThanOrEqual(600, app(PageCache::class)->ttl());

        $this->get('/blog')->assertHeader('X-Press-Cache', 'MISS')->assertDontSee('Launch announcement');
        $this->get('/blog')->assertHeader('X-Press-Cache', 'HIT')->assertDontSee('Launch announcement');

        $this->travel(11)->minutes();

        $this->get('/blog')->assertHeader('X-Press-Cache', 'MISS')->assertSee('Launch announcement');
        $this->get('/blog/launch')->assertOk();
        $this->assertSame(3600, app(PageCache::class)->ttl());
    }

    public function test_signed_in_users_theme_previews_and_errors_bypass_the_cache(): void
    {
        Page::factory()->published()->create(['slug' => 'about']);
        Permissions::sync();
        $editor = User::factory()->create();
        $editor->assignRole('editor');

        $this->actingAs($editor)->get('/about')->assertOk()->assertHeaderMissing('X-Press-Cache');
        auth()->logout();

        $this->get('/about?preview_theme=nocturne')->assertHeaderMissing('X-Press-Cache');
        $this->get('/no-such-page')->assertNotFound()->assertHeaderMissing('X-Press-Cache');
        $this->get('/admin/login')->assertHeaderMissing('X-Press-Cache');
    }

    public function test_pages_with_a_form_token_are_never_stored(): void
    {
        Route::middleware('web')->get('/form-probe', fn () => response('<form><input type="hidden" name="_token" value="'.csrf_token().'"></form>'));

        $this->get('/form-probe')->assertOk()->assertHeaderMissing('X-Press-Cache');
        $this->get('/form-probe')->assertOk()->assertHeaderMissing('X-Press-Cache');

        Route::middleware('web')->get('/livewire-probe', fn () => response('<div wire:snapshot="{}" wire:id="x"></div>'));
        $this->get('/livewire-probe')->assertOk()->assertHeaderMissing('X-Press-Cache');
    }

    public function test_settings_changes_and_the_command_flush_the_cache(): void
    {
        Page::factory()->published()->create(['slug' => 'about']);
        $this->get('/about');
        $this->get('/about')->assertHeader('X-Press-Cache', 'HIT');

        app(Settings::class)->set('site.tagline', 'New tagline');
        $this->get('/about')->assertHeader('X-Press-Cache', 'MISS');
        $this->get('/about')->assertHeader('X-Press-Cache', 'HIT');

        $this->artisan('press:page-cache', ['action' => 'clear'])->assertSuccessful();
        $this->get('/about')->assertHeader('X-Press-Cache', 'MISS');
    }

    public function test_the_cache_is_off_when_disabled(): void
    {
        config(['press.page_cache.enabled' => false]);
        Page::factory()->published()->create(['slug' => 'about']);

        $this->get('/about')->assertOk()->assertHeaderMissing('X-Press-Cache');
        $this->get('/about')->assertOk()->assertHeaderMissing('X-Press-Cache');
    }
}
