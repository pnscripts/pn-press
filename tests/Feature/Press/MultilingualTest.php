<?php

namespace Tests\Feature\Press;

use App\Enums\PostStatus;
use App\Filament\Resources\CmsPages\Pages\CreatePage;
use App\Filament\Resources\CmsPages\Pages\ListPages;
use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Filament\Support\Translatable;
use App\Models\Category;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use App\Press\I18n\Locales;
use App\Press\Permissions;
use App\Press\Settings;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PnPress\ContactForm\Http\SendMessageController;
use PnPress\SeoSitemap\Http\SitemapController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/** phpunit.xml runs the suite with PRESS_LOCALES=en,bg: English at "/", Bulgarian at "/bg". */
class MultilingualTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Permissions::sync();
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    /** @return array{0: Page, 1: Page} */
    private function aboutPages(): array
    {
        $en = Page::factory()->published()->create(['title' => 'About us', 'slug' => 'about']);
        $bg = Page::factory()->published()->create(['title' => 'За нас', 'slug' => 'za-nas', 'locale' => 'bg']);
        $bg->linkTranslationTo($en);

        return [$en->fresh(), $bg->fresh()];
    }

    public function test_locales_come_from_config_and_the_default_has_no_prefix(): void
    {
        $locales = app(Locales::class);

        $this->assertSame(['en', 'bg'], $locales->codes());
        $this->assertSame('en', $locales->default());
        $this->assertSame(['bg'], $locales->prefixed());
        $this->assertSame('/bg/about', $locales->path('/about', 'bg'));
        $this->assertSame('/about', $locales->path('/about', 'en'));
        $this->assertSame('/bg', $locales->path('/', 'bg'));
        $this->assertSame(['bg', 'blog/x'], $locales->split('bg/blog/x'));
        $this->assertSame(['en', 'bgx/page'], $locales->split('bgx/page'));
    }

    public function test_invalid_locale_codes_in_config_are_ignored(): void
    {
        config(['press.locales' => ['en', '../etc', 'BG', 'bg', '<x>']]);

        $this->assertSame(['en', 'bg'], app(Locales::class)->codes());
    }

    public function test_new_content_takes_the_default_locale(): void
    {
        $this->assertSame('en', Post::factory()->create()->locale);
        $this->assertSame('en', Page::factory()->create(['locale' => 'xx'])->locale);
    }

    public function test_each_language_has_its_own_page_address_html_lang_and_hreflang(): void
    {
        [$en, $bg] = $this->aboutPages();

        $this->get('/bg/za-nas')
            ->assertOk()
            ->assertSee('<html lang="bg"', false)
            ->assertSee('За нас')
            ->assertSee('<link rel="alternate" hreflang="en" href="'.url('/about').'">', false)
            ->assertSee('<link rel="alternate" hreflang="bg" href="'.url('/bg/za-nas').'">', false)
            ->assertSee('<link rel="alternate" hreflang="x-default" href="'.url('/about').'">', false)
            ->assertSee('Към съдържанието');

        $this->get('/about')->assertOk()->assertSee('<html lang="en"', false)->assertSee('Skip to content');

        // A page lives only under its own language.
        $this->get('/za-nas')->assertNotFound();
        $this->get('/bg/about')->assertNotFound();
    }

    public function test_the_same_slug_may_exist_in_two_languages(): void
    {
        Page::factory()->published()->create(['title' => 'Contact EN', 'slug' => 'contact']);
        Page::factory()->published()->create(['title' => 'Контакт BG', 'slug' => 'contact', 'locale' => 'bg']);
        Post::factory()->published()->create(['title' => 'Hello EN', 'slug' => 'hello']);
        Post::factory()->published()->create(['title' => 'Здравей BG', 'slug' => 'hello', 'locale' => 'bg']);

        $this->get('/contact')->assertSee('Contact EN')->assertDontSee('Контакт BG');
        $this->get('/bg/contact')->assertSee('Контакт BG')->assertDontSee('Contact EN');
        $this->get('/blog/hello')->assertSee('Hello EN');
        $this->get('/bg/blog/hello')->assertSee('Здравей BG');
    }

    public function test_language_switcher_links_to_the_translation_or_that_languages_home(): void
    {
        [$en] = $this->aboutPages();
        Page::factory()->published()->create(['title' => 'Only English', 'slug' => 'only-en']);

        $this->get('/about')
            ->assertSee('href="'.url('/bg/za-nas').'" hreflang="bg"', false)
            ->assertSee('aria-current="true" lang="en"', false);

        $this->get('/only-en')
            ->assertSee('href="'.url('/bg').'" hreflang="bg"', false)
            ->assertDontSee('<link rel="alternate" hreflang', false);
    }

    public function test_a_draft_translation_is_not_linked(): void
    {
        [$en, $bg] = $this->aboutPages();
        $bg->update(['status' => PostStatus::Draft]);

        $this->get('/about')->assertDontSee('/bg/za-nas', false);
    }

    public function test_blog_lists_only_posts_of_the_current_language_with_translated_ui(): void
    {
        $catEn = Category::factory()->create(['name' => 'News', 'slug' => 'news']);
        $catBg = Category::factory()->create(['name' => 'Новини', 'slug' => 'novini', 'locale' => 'bg']);
        $catBg->linkTranslationTo($catEn);
        Post::factory()->published()->create(['title' => 'English post', 'category_id' => $catEn->id]);
        Post::factory()->published()->create(['title' => 'Българска публикация', 'locale' => 'bg', 'category_id' => $catBg->id]);

        $this->get('/blog')->assertSee('English post')->assertDontSee('Българска публикация')->assertSee('Latest writing');
        $this->get('/bg/blog')
            ->assertSee('Българска публикация')
            ->assertDontSee('English post')
            ->assertSee('Последни публикации')
            ->assertSee(url('/bg/category/novini'), false)
            ->assertSee('<link rel="alternate" hreflang="en" href="'.url('/blog').'">', false);

        $this->get('/bg/category/novini')->assertOk()->assertSee('<link rel="alternate" hreflang="en" href="'.url('/category/news').'">', false);
        $this->get('/bg/category/news')->assertNotFound();
    }

    public function test_dates_are_written_in_the_page_language(): void
    {
        Post::factory()->published()->create(['title' => 'Пост', 'slug' => 'post', 'locale' => 'bg', 'published_at' => '2026-03-05 10:00:00']);

        $this->get('/bg/blog/post')->assertSee('5 март 2026');
    }

    public function test_each_language_has_its_own_home_page_and_site_name(): void
    {
        $homeEn = Page::factory()->published()->create(['title' => 'Welcome home', 'slug' => 'welcome']);
        $homeBg = Page::factory()->published()->create(['title' => 'Добре дошли', 'slug' => 'nachalo', 'locale' => 'bg']);
        app(Settings::class)->setMany([
            'reading.home_page_id' => $homeEn->id,
            'reading.i18n.bg.home_page_id' => $homeBg->id,
            'site.name' => 'Acme',
            'site.i18n.bg.name' => 'Акме',
            'site.i18n.bg.tagline' => 'Нашият сайт',
        ]);

        $this->get('/')->assertSee('Welcome home')->assertSee('Acme')->assertDontSee('Акме');
        $this->get('/bg')->assertSee('Добре дошли')->assertSee('Акме')->assertSee('Нашият сайт');
        $this->get('/bg/nachalo')->assertRedirect(url('/bg'));

        // A Bulgarian page chosen as the English home page is ignored.
        app(Settings::class)->set('reading.home_page_id', $homeBg->id);
        $this->get('/')->assertDontSee('Добре дошли');
    }

    public function test_menus_per_language_fall_back_to_the_every_language_menu(): void
    {
        $all = Menu::query()->create(['name' => 'Main', 'location' => 'primary']);
        MenuItem::query()->create(['menu_id' => $all->id, 'label' => 'Everyone link', 'type' => 'url', 'url' => '/x']);
        $bg = Menu::query()->create(['name' => 'Главно', 'location' => 'primary', 'locale' => 'bg']);
        MenuItem::query()->create(['menu_id' => $bg->id, 'label' => 'Блог линк', 'type' => 'blog']);

        $this->get('/blog')->assertSee('Everyone link')->assertDontSee('Блог линк');
        $this->get('/bg/blog')->assertSee('Блог линк')->assertSee('href="'.url('/bg/blog').'"', false)->assertDontSee('Everyone link');
    }

    public function test_sitemap_controller_adds_xhtml_alternates(): void
    {
        require_once base_path('plugins/seo-sitemap/src/Http/SitemapController.php');
        app('view')->addNamespace('seo-sitemap', base_path('plugins/seo-sitemap/resources/views'));
        $this->aboutPages();

        $xml = (string) (new SitemapController)()->getContent();

        $this->assertStringContainsString('<loc>'.url('/bg/za-nas').'</loc>', $xml);
        $this->assertStringContainsString('<xhtml:link rel="alternate" hreflang="en" href="'.url('/about').'"/>', $xml);
        $this->assertStringContainsString('<loc>'.url('/bg/blog').'</loc>', $xml);
        $this->assertNotFalse(simplexml_load_string($xml));
    }

    public function test_translate_action_creates_a_linked_draft_with_mapped_parent(): void
    {
        $parentEn = Page::factory()->published()->create(['title' => 'Services', 'slug' => 'services']);
        $parentBg = Page::factory()->published()->create(['title' => 'Услуги', 'slug' => 'uslugi', 'locale' => 'bg']);
        $parentBg->linkTranslationTo($parentEn);
        $child = Page::factory()->published()->create(['title' => 'Design', 'slug' => 'design', 'parent_id' => $parentEn->id]);

        $copy = Translatable::translate($child, 'bg');

        $this->assertSame('bg', $copy->locale);
        $this->assertSame(PostStatus::Draft, $copy->status);
        $this->assertSame($parentBg->id, $copy->parent_id);
        $this->assertSame('uslugi/design', $copy->path);
        $this->assertSame($child->fresh()->translation_group, $copy->translation_group);
        $this->assertSame([], $child->fresh()->missingLocales());
    }

    public function test_translate_maps_the_post_category_and_refuses_twice(): void
    {
        $catEn = Category::factory()->create();
        $catBg = Category::factory()->create(['locale' => 'bg']);
        $catBg->linkTranslationTo($catEn);
        $post = Post::factory()->published()->create(['category_id' => $catEn->id]);

        $copy = Translatable::translate($post, 'bg');
        $this->assertSame($catBg->id, $copy->category_id);
        $this->assertSame($post->slug, $copy->slug);

        $this->expectException(HttpException::class);
        Translatable::translate($post->fresh(), 'bg');
    }

    public function test_translate_action_in_the_admin_redirects_to_the_new_draft(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $page = Page::factory()->published()->create(['title' => 'Pricing', 'slug' => 'pricing']);

        Livewire::test(ListPages::class)
            ->assertSee('EN')
            ->callAction(TestAction::make('translate')->table($page), ['target' => 'bg'])
            ->assertHasNoActionErrors();

        $copy = Page::query()->where('locale', 'bg')->firstOrFail();
        $this->assertSame('pricing', $copy->path);
        $this->assertSame($page->fresh()->translation_group, $copy->translation_group);
    }

    public function test_post_slug_is_unique_per_language_in_the_admin(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Post::factory()->create(['slug' => 'taken']);

        $fill = ['title' => 'T', 'slug' => 'taken', 'excerpt' => 'e', 'body' => '<p>b</p>', 'status' => PostStatus::Draft->value];

        Livewire::test(CreatePost::class)->fillForm($fill + ['locale' => 'en'])->call('create')->assertHasFormErrors(['slug' => 'unique']);
        Livewire::test(CreatePost::class)->fillForm($fill + ['locale' => 'bg'])->call('create')->assertHasNoFormErrors();
        $this->assertSame(1, Post::query()->where('locale', 'bg')->where('slug', 'taken')->count());
    }

    public function test_a_top_level_page_cannot_take_a_language_prefix_as_slug(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(CreatePage::class)
            ->fillForm(['title' => 'BG', 'slug' => 'bg', 'status' => PostStatus::Draft->value, 'template' => 'default', 'locale' => 'en'])
            ->call('create')
            ->assertHasFormErrors(['slug']);
    }

    public function test_contact_form_errors_follow_the_page_language(): void
    {
        app('router')->post('/_test-contact', [SendMessageController::class, '__invoke'])->middleware('web');
        require_once base_path('plugins/contact-form/src/Http/SendMessageController.php');

        $this->from('/bg/kontakt')->post('/_test-contact', ['locale' => 'bg', 'name' => '', 'email' => 'x', 'message' => 'hello there'])
            ->assertSessionHasErrors(['name' => 'Полето „Име“ е задължително.']);
    }

    public function test_a_single_language_site_has_no_switcher_or_hreflang(): void
    {
        config(['press.locales' => ['en']]);
        $this->aboutPages();

        $this->get('/about')->assertOk()->assertDontSee('hreflang', false)->assertDontSee('press-languages', false);
    }
}
