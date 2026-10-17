<?php

namespace Database\Seeders;

use App\Enums\PostStatus;
use App\Models\Category;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use App\Press\I18n\Locales;
use App\Press\Settings;
use Illuminate\Database\Seeder;

/**
 * Bulgarian translations of the demo (only when "bg" is in PRESS_LOCALES): pages, categories,
 * three posts, menus and the site tagline, each linked to its English original so the language
 * switcher and hreflang links work. Re-running it leaves existing rows alone.
 */
class DemoSiteBulgarian extends Seeder
{
    private const LOCALE = 'bg';

    public function run(): void
    {
        $locales = app(Locales::class);

        if (! in_array(self::LOCALE, $locales->prefixed(), true)) {
            return;
        }

        $settings = app(Settings::class);
        $author = User::query()->where('email', 'admin@example.com')->value('id');
        $default = $locales->default();

        if ($settings->get('site.i18n.bg.tagline') === null) {
            $settings->set('site.i18n.bg.tagline', 'Бележки, ръководства и страници, публикувани с PN Scripts Press');
        }

        $categories = [];
        foreach ([
            'getting-started' => ['Първи стъпки', 'parvi-stapki', 'Какво да промените след инсталацията, какво да запазите и къде се намират нещата.'],
            'writing' => ['Писане', 'pisane', 'Заглавия, резюмета, структура и малките навици, които правят текста по-лесен за четене.'],
            'workflow' => ['Работен процес', 'raboten-proces', 'Чернови, насрочване, роли и навиците, които държат блога жив.'],
            'design' => ['Дизайн', 'dizayn', 'Корици, категории и частите от сайта, които читателите виждат първи.'],
        ] as $source => [$name, $slug, $description]) {
            $original = Category::query()->where('locale', $default)->where('slug', $source)->first();
            $category = Category::query()->firstOrCreate(
                ['locale' => self::LOCALE, 'slug' => $slug],
                ['name' => $name, 'description' => $description],
            );
            if ($original && blank($category->translation_group)) {
                $category->linkTranslationTo($original);
            }
            $categories[$source] = $category;
        }

        foreach ($this->posts() as $source => $post) {
            $original = Post::query()->where('locale', $default)->where('slug', $source)->first();

            if ($original === null) {
                continue;
            }

            $record = Post::query()->firstOrCreate(
                ['locale' => self::LOCALE, 'slug' => $post['slug']],
                [
                    'user_id' => $original->user_id,
                    'category_id' => $categories[$post['category']]->id ?? null,
                    'title' => $post['title'],
                    'excerpt' => $post['excerpt'],
                    'body' => $post['body'],
                    'status' => PostStatus::Published,
                    'published_at' => $original->published_at,
                    'featured_image' => $original->featured_image,
                ],
            );

            if (blank($record->translation_group)) {
                $record->linkTranslationTo($original);
            }
        }

        $home = $this->page('nachalo', 'Начало', $author, 'landing', 'home', [
            ['type' => 'hero', 'data' => [
                'eyebrow' => 'Добре дошли',
                'heading' => 'Малко издателство за вашето кътче в мрежата',
                'text' => 'Този сайт работи с PN Scripts Press: страници от блокове, блог, менюта, медийна библиотека, плъгини и теми, които добавяте от администрацията — на български и английски.',
                'button_label' => 'Към блога',
                'button_url' => '/bg/blog',
            ]],
            ['type' => 'features', 'data' => [
                'heading' => 'Какво правите от администрацията',
                'items' => [
                    ['title' => 'Страници от блокове', 'text' => 'Банер, текст, изображение, цитат, списък с предимства, призив за действие и последните публикации.'],
                    ['title' => 'Два езика', 'text' => 'Всяка страница и публикация има свой адрес на всеки език, превключвател на езика и hreflang връзки.'],
                    ['title' => 'Менюта', 'text' => 'Главно меню и меню в долната част, с падащи подменюта — отделно за всеки език.'],
                    ['title' => 'Теми и плъгини', 'text' => 'Качете тема или плъгин като .zip, прегледайте и активирайте. Дъщерните теми наследяват родителската.'],
                    ['title' => 'Сигурен вход', 'text' => 'Двуфакторно удостоверяване с приложение и кодове за възстановяване.'],
                    ['title' => 'Бързина', 'text' => 'Кеш на цели страници, който се обновява при всяка промяна и при насрочените публикации.'],
                ],
            ]],
            ['type' => 'latest_posts', 'data' => ['heading' => 'От блога', 'count' => 3]],
            ['type' => 'call_to_action', 'data' => [
                'heading' => 'Имате въпрос за сайта?',
                'text' => 'Страницата за контакт използва плъгина Contact Form.',
                'button_label' => 'Пишете ни',
                'button_url' => '/bg/kontakt',
            ]],
        ]);

        $about = $this->page('za-nas', 'За нас', $author, 'default', 'about', [
            ['type' => 'text', 'data' => ['content' => '<p>Това е примерна страница. Редактирайте я в администрацията от <strong>Content → Pages</strong> или я изтрийте и започнете своя.</p><h2>Как работят страниците</h2><p>Страницата е списък от блокове. Добавяйте, подреждайте, дублирайте и премахвайте блокове в редактора; активната тема решава как изглежда всеки блок.</p>']],
            ['type' => 'quote', 'data' => ['text' => 'Напишете страницата, която бихте искали да прочетете.', 'cite' => 'PN Scripts Press']],
        ], 'Няколко думи за този сайт.');

        $contact = $this->page('kontakt', 'Контакт', $author, 'default', 'contact', [
            ['type' => 'text', 'data' => ['content' => '<p>Изпратете съобщение чрез формата по-долу. То се записва в базата данни и се изпраща по имейл, когато в <strong>Settings</strong> е зададен получател.</p>']],
            ['type' => 'contact_form', 'data' => ['heading' => 'Изпратете съобщение', 'button_label' => 'Изпрати']],
        ], 'Въпроси, идеи и поправки са добре дошли.');

        if ($settings->get('reading.i18n.bg.home_page_id') === null) {
            $settings->set('reading.i18n.bg.home_page_id', $home->id);
        }

        if (! Menu::query()->where('location', 'primary')->where('locale', self::LOCALE)->exists()) {
            $menu = Menu::query()->create(['name' => 'Главно меню', 'location' => 'primary', 'locale' => self::LOCALE]);
            $menu->items()->create(['label' => 'Начало', 'type' => 'url', 'url' => '/bg', 'sort_order' => 1]);
            $blog = $menu->items()->create(['label' => 'Блог', 'type' => 'blog', 'sort_order' => 2]);
            foreach (array_values($categories) as $index => $category) {
                $blog->children()->create(['label' => $category->name, 'type' => 'category', 'category_id' => $category->id, 'sort_order' => $index + 1]);
            }
            $menu->items()->create(['label' => 'За нас', 'type' => 'page', 'page_id' => $about->id, 'sort_order' => 3]);
            $menu->items()->create(['label' => 'Контакт', 'type' => 'page', 'page_id' => $contact->id, 'sort_order' => 4]);
        }

        if (! Menu::query()->where('location', 'footer')->where('locale', self::LOCALE)->exists()) {
            $menu = Menu::query()->create(['name' => 'Долно меню', 'location' => 'footer', 'locale' => self::LOCALE]);
            $menu->items()->create(['label' => 'За нас', 'type' => 'page', 'page_id' => $about->id, 'sort_order' => 1]);
            $menu->items()->create(['label' => 'Контакт', 'type' => 'page', 'page_id' => $contact->id, 'sort_order' => 2]);
            $menu->items()->create(['label' => 'Карта на сайта', 'type' => 'url', 'url' => '/sitemap.xml', 'sort_order' => 3]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     */
    private function page(string $slug, string $title, ?int $author, string $template, string $source, array $blocks, ?string $excerpt = null): Page
    {
        $page = Page::query()->firstOrCreate(['locale' => self::LOCALE, 'slug' => $slug, 'parent_id' => null], [
            'title' => $title,
            'user_id' => $author,
            'template' => $template,
            'blocks' => $blocks,
            'excerpt' => $excerpt,
            'status' => PostStatus::Published,
            'published_at' => now()->subDays(7)->startOfDay(),
        ]);

        $original = Page::query()->where('locale', app(Locales::class)->default())->where('slug', $source)->whereNull('parent_id')->first();
        if ($original && blank($page->translation_group)) {
            $page->linkTranslationTo($original);
        }

        return $page;
    }

    /**
     * Bulgarian versions of three English demo posts (keyed by the English slug).
     *
     * @return array<string, array{title: string, slug: string, category: string, excerpt: string, body: string}>
     */
    private function posts(): array
    {
        return [
            'welcome-to-your-new-pn-press-blog' => [
                'title' => 'Добре дошли в новия си блог с PN Scripts Press',
                'slug' => 'dobre-doshli-v-noviya-si-blog',
                'category' => 'getting-started',
                'excerpt' => 'Кратка обиколка на демо сайта, администрацията и трите настройки, които си струва да смените, преди да напишете каквото и да е.',
                'body' => '<p>Този блог е попълнен с примерни публикации, за да разгледате сайта, преди да напишете и дума. Всяка публикация е за воденето на малък блог, така че демото е и кратко ръководство.</p><h2>Какво виждате</h2><p>Публичният сайт има три вида страници: списък с публикации, страница за всяка публикация и архив за всяка категория. Черновите и насрочените публикации никога не се показват тук.</p><h2>Сменете първо това</h2><ul><li><strong>Името на сайта</strong> — в <em>Settings</em>; за всеки език има отделно поле.</li><li><strong>Паролата на администратора</strong> — демо профилът е само за локална употреба.</li><li><strong>Демо публикациите</strong> — редактирайте ги или ги изтрийте.</li></ul>',
            ],
            'write-your-first-post-in-five-minutes' => [
                'title' => 'Първата ви публикация за пет минути',
                'slug' => 'parvata-vi-publikatsiya-za-pet-minuti',
                'category' => 'writing',
                'excerpt' => 'Заглавие, адрес, категория, резюме, текст: поле по поле през редактора на публикации.',
                'body' => '<p>Отворете <em>Posts</em> в администрацията и натиснете <em>New post</em>. Изберете езика, напишете заглавието — адресът (slug) се попълва сам.</p><h2>Полетата</h2><ul><li><strong>Резюме</strong> — две изречения, които обещават какво ще получи читателят.</li><li><strong>Категория</strong> — показват се само категориите на избрания език.</li><li><strong>Дата на публикуване</strong> — бъдеща дата насрочва публикацията.</li></ul><p>За да преведете готова публикация, използвайте действието <em>Translate</em> в списъка: създава се свързана чернова на другия език.</p>',
            ],
            'drafts-publish-dates-and-scheduled-posts' => [
                'title' => 'Чернови, дати на публикуване и насрочени публикации',
                'slug' => 'chernovi-i-nasrocheni-publikatsii',
                'category' => 'workflow',
                'excerpt' => 'Как PN Scripts Press решава какво виждат читателите и как да подредите публикации предварително с бъдеща дата.',
                'body' => '<p>Публикацията се вижда само когато е със статус <em>Published</em> и датата ѝ е минала. Всичко друго остава скрито — дори ако някой знае адреса.</p><h2>Насрочване</h2><p>Задайте бъдеща дата и публикацията ще се появи сама в този момент. Кешът на страниците изтича точно тогава, така че няма нужда да го чистите ръчно.</p>',
            ],
        ];
    }
}
