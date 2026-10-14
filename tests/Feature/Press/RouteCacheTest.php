<?php

namespace Tests\Feature\Press;

use App\Models\Plugin;
use App\Models\User;
use App\Press\Permissions;
use App\Press\Plugins\PluginManager;
use Illuminate\Support\Facades\Artisan;
use PnPress\ContactForm\ContactMessage;
use Tests\Concerns\RebootsWithAFileDatabase;
use Tests\TestCase;

/**
 * `php artisan route:cache` must keep plugin routes and plugin admin screens working.
 */
class RouteCacheTest extends TestCase
{
    use RebootsWithAFileDatabase;

    protected function setUp(): void
    {
        $this->useFileDatabase();
        parent::setUp();

        Artisan::call('migrate', ['--force' => true]);
        Permissions::sync();
        app(PluginManager::class)->activate('contact-form');
        app(PluginManager::class)->activate('seo-sitemap');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->restoreEnvironment();
    }

    private function cacheRoutes(): void
    {
        Artisan::call('route:cache');
        $this->assertFileExists($this->routeCachePath());
        $this->reboot();
        $this->assertTrue(app()->routesAreCached());
    }

    public function test_plugin_routes_and_admin_screens_work_from_the_route_cache(): void
    {
        $this->cacheRoutes();

        $cached = file_get_contents($this->routeCachePath());
        $this->assertStringContainsString('contact-form.send', $cached);
        $this->assertStringContainsString('seo-sitemap', $cached);
        $this->assertStringContainsString('contact-messages', $cached);

        $this->assertSame(url('/contact-form/send'), route('contact-form.send'));
        $this->get('/sitemap.xml')->assertOk()->assertSee('<urlset', false);
        $this->get('/blog')->assertOk()->assertSee('rel="sitemap"', false);

        $this->from('/contact')->post('/contact-form/send', [
            'name' => 'Cached', 'email' => 'cached@example.com', 'message' => 'Sent through a cached route.',
        ])->assertRedirect();
        $this->assertDatabaseHas('contact_messages', ['email' => 'cached@example.com']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $message = ContactMessage::query()->where('email', 'cached@example.com')->firstOrFail();
        $this->actingAs($admin)->get('/admin/contact-messages')->assertOk()->assertSee('Cached');
        $this->get("/admin/contact-messages/{$message->id}")->assertOk();

        // CMS pages still answer through the fallback route.
        $this->get('/no-such-page')->assertNotFound();
    }

    public function test_changing_active_plugins_drops_the_stale_route_cache(): void
    {
        $this->cacheRoutes();

        app(PluginManager::class)->deactivate('seo-sitemap');

        $this->assertFileDoesNotExist($this->routeCachePath());
        $this->reboot();
        $this->assertFalse(app()->routesAreCached());
        $this->get('/sitemap.xml')->assertNotFound();
        $this->post('/contact-form/send', ['website' => 'bot'])->assertRedirect();
    }

    public function test_a_cached_route_of_a_plugin_switched_off_behind_its_back_answers_404(): void
    {
        $this->cacheRoutes();

        // Not through the manager, so the cache is not cleared: the middleware still guards it.
        Plugin::query()->where('slug', 'seo-sitemap')->update(['enabled' => false]);
        $this->reboot();

        $this->assertTrue(app()->routesAreCached());
        $this->get('/sitemap.xml')->assertNotFound();
    }
}
