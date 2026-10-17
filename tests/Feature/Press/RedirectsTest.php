<?php

namespace Tests\Feature\Press;

use App\Filament\Resources\Redirects\Pages\ManageRedirects;
use App\Models\Page;
use App\Models\Post;
use App\Models\Redirect;
use App\Models\User;
use App\Press\Permissions;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RedirectsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_redirect_answers_a_missing_address_and_counts_hits(): void
    {
        $redirect = Redirect::query()->create(['from_path' => '/old-page/', 'to_url' => '/new-page', 'status_code' => 301]);

        $this->assertSame('old-page', $redirect->from_path);
        $this->get('/old-page?utm_source=x')->assertStatus(301)->assertRedirect(url('/new-page?utm_source=x'));
        $this->assertSame(1, $redirect->fresh()->hits);
        $this->assertNotNull($redirect->fresh()->last_hit_at);
    }

    public function test_a_live_page_wins_over_a_redirect(): void
    {
        Page::factory()->published()->create(['title' => 'Live', 'slug' => 'live']);
        Redirect::query()->create(['from_path' => 'live', 'to_url' => '/elsewhere']);

        $this->get('/live')->assertOk()->assertSee('Live');
    }

    public function test_unsafe_targets_are_never_followed(): void
    {
        foreach (['//evil.example', 'javascript:alert(1)', '/\\evil.example', 'data:text/html,x'] as $i => $target) {
            Redirect::query()->create(['from_path' => 'x'.$i, 'to_url' => $target]);
            $this->get('/x'.$i)->assertNotFound();
        }
    }

    public function test_only_get_requests_are_redirected(): void
    {
        Redirect::query()->create(['from_path' => 'form', 'to_url' => '/elsewhere']);

        $this->post('/form')->assertStatus(405); // Fallback route is GET only; never a redirect.
    }

    public function test_renaming_a_published_page_adds_an_automatic_redirect_in_its_language(): void
    {
        $page = Page::factory()->published()->create(['title' => 'Услуги', 'slug' => 'uslugi', 'locale' => 'bg']);
        $page->update(['slug' => 'nashite-uslugi']);

        $this->get('/bg/uslugi')->assertRedirect(url('/bg/nashite-uslugi'));
        $this->assertTrue(Redirect::query()->where('from_path', 'bg/uslugi')->value('automatic'));

        // Renaming back removes the redirect that would hide the page, and the chain follows.
        $page->update(['slug' => 'uslugi']);
        $this->get('/bg/uslugi')->assertOk();
        $this->get('/bg/nashite-uslugi')->assertRedirect(url('/bg/uslugi'));
    }

    public function test_renaming_a_draft_adds_no_redirect(): void
    {
        $page = Page::factory()->create(['slug' => 'draft-a']);
        $page->update(['slug' => 'draft-b']);

        $this->assertSame(0, Redirect::query()->count());
    }

    public function test_renaming_a_published_post_slug_redirects_the_old_url(): void
    {
        $post = Post::factory()->published()->create(['slug' => 'first-name']);
        $post->update(['slug' => 'second-name']);

        $this->get('/blog/first-name')->assertRedirect(url('/blog/second-name'));
    }

    public function test_editors_manage_redirects_in_the_admin(): void
    {
        Permissions::sync();
        $editor = User::factory()->create();
        $editor->assignRole('editor');
        $this->actingAs($editor);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ManageRedirects::class)
            ->callAction('create', ['from_path' => '/Old/', 'to_url' => '//evil.example', 'status_code' => 301])
            ->assertHasActionErrors(['to_url']);

        Livewire::test(ManageRedirects::class)
            ->callAction('create', ['from_path' => '/old/', 'to_url' => '/new', 'status_code' => 302])
            ->assertHasNoActionErrors();

        $this->assertSame(302, Redirect::query()->where('from_path', 'old')->value('status_code'));
    }
}
