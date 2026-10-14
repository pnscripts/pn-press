<?php

namespace Database\Seeders;

use App\Enums\PostStatus;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Plugin;
use App\Models\User;
use App\Press\Plugins\PluginManager;
use App\Press\Settings;
use Illuminate\Database\Seeder;

/**
 * CMS demo: a home page, About and Contact pages, two menus, the Folio theme and the
 * two example plugins. Re-running it leaves existing rows and settings alone.
 */
class DemoSite extends Seeder
{
    public function run(): void
    {
        $settings = app(Settings::class);
        $author = User::query()->where('email', 'admin@example.com')->value('id');

        if ($settings->get('site.tagline') === null) {
            $settings->setMany([
                'site.tagline' => 'Notes, guides and pages, published with PN Scripts Press',
                'theme.active' => $settings->get('theme.active', 'folio'),
            ]);
        }

        $plugins = app(PluginManager::class);
        foreach (['seo-sitemap', 'contact-form'] as $slug) {
            if ($plugins->find($slug) && ! $plugins->isActive($slug) && ! Plugin::query()->where('slug', $slug)->exists()) {
                $plugins->activate($slug);
            }
        }

        $home = $this->page('home', 'Home', $author, 'landing', [
            ['type' => 'hero', 'data' => [
                'eyebrow' => 'Welcome',
                'heading' => 'A small press for your own corner of the web',
                'text' => 'This site runs on PN Scripts Press: pages built from blocks, a blog, menus, a media library, and plugins and themes you can add from the admin.',
                'button_label' => 'Read the journal',
                'button_url' => '/blog',
            ]],
            ['type' => 'features', 'data' => [
                'heading' => 'What you can do from the admin',
                'items' => [
                    ['title' => 'Build pages from blocks', 'text' => 'Hero, text, image, quote, feature list, call to action and the latest posts. Plugins add more.'],
                    ['title' => 'Arrange the menus', 'text' => 'A main menu and a footer menu, with dropdowns, pointing at pages, posts, categories or any link.'],
                    ['title' => 'Switch the look', 'text' => 'Install a theme from a .zip, preview it, then activate it. Colours and fonts are theme settings.'],
                    ['title' => 'Extend it', 'text' => 'Plugins bring blocks, routes, settings and database tables, and hook into actions and filters.'],
                    ['title' => 'Go back in time', 'text' => 'Every page save keeps a revision you can restore.'],
                    ['title' => 'Be found', 'text' => 'Meta titles, descriptions, social images and a sitemap from the SEO Sitemap plugin.'],
                ],
            ]],
            ['type' => 'latest_posts', 'data' => ['heading' => 'From the journal', 'count' => 3]],
            ['type' => 'call_to_action', 'data' => [
                'heading' => 'Have a question about this site?',
                'text' => 'The contact page uses the Contact Form plugin.',
                'button_label' => 'Write to us',
                'button_url' => '/contact',
            ]],
        ]);

        $about = $this->page('about', 'About', $author, 'default', [
            ['type' => 'text', 'data' => ['content' => '<p>This is an example page. Edit it in the admin under <strong>Content → Pages</strong>, or delete it and start your own.</p><h2>How pages work</h2><p>A page is a list of blocks. Add, reorder, duplicate and remove blocks in the editor; the active theme decides how each block looks.</p>']],
            ['type' => 'quote', 'data' => ['text' => 'Write the page you would like to read.', 'cite' => 'PN Scripts Press']],
        ], 'A few words about this site.');

        $contact = $this->page('contact', 'Contact', $author, 'default', [
            ['type' => 'text', 'data' => ['content' => '<p>Send a message with the form below. It is stored in the database, and emailed when a recipient is set in <strong>Settings</strong>.</p>']],
            ['type' => 'contact_form', 'data' => ['heading' => 'Send a message', 'button_label' => 'Send message']],
        ], 'Questions, ideas and corrections are welcome.');

        if ($settings->get('reading.home_page_id') === null) {
            $settings->set('reading.home_page_id', $home->id);
        }

        if (! Menu::query()->where('location', 'primary')->exists()) {
            $menu = Menu::query()->create(['name' => 'Main menu', 'location' => 'primary']);
            $menu->items()->create(['label' => 'Home', 'type' => 'url', 'url' => '/', 'sort_order' => 1]);
            $menu->items()->create(['label' => 'Journal', 'type' => 'blog', 'sort_order' => 2]);
            $menu->items()->create(['label' => 'About', 'type' => 'page', 'page_id' => $about->id, 'sort_order' => 3]);
            $menu->items()->create(['label' => 'Contact', 'type' => 'page', 'page_id' => $contact->id, 'sort_order' => 4]);
        }

        if (! Menu::query()->where('location', 'footer')->exists()) {
            $menu = Menu::query()->create(['name' => 'Footer', 'location' => 'footer']);
            $menu->items()->create(['label' => 'About', 'type' => 'page', 'page_id' => $about->id, 'sort_order' => 1]);
            $menu->items()->create(['label' => 'Contact', 'type' => 'page', 'page_id' => $contact->id, 'sort_order' => 2]);
            $menu->items()->create(['label' => 'Sitemap', 'type' => 'url', 'url' => '/sitemap.xml', 'sort_order' => 3]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     */
    private function page(string $slug, string $title, ?int $author, string $template, array $blocks, ?string $excerpt = null): Page
    {
        return Page::query()->firstOrCreate(['slug' => $slug, 'parent_id' => null], [
            'title' => $title,
            'user_id' => $author,
            'template' => $template,
            'blocks' => $blocks,
            'excerpt' => $excerpt,
            'status' => PostStatus::Published,
            'published_at' => now()->subDays(7)->startOfDay(),
        ]);
    }
}
