<?php

namespace PnPress\SeoSitemap\Http;

use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Press\I18n\Locales;
use Illuminate\Http\Response;

class SitemapController
{
    public function __invoke(): Response
    {
        return response()
            ->view('seo-sitemap::sitemap', ['urls' => $this->urls()])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /**
     * Every language's URLs; translated items list their language versions as xhtml:link alternates.
     *
     * @return list<array{loc: string, lastmod: ?string, alternates?: array<string, string>}>
     */
    private function urls(): array
    {
        $locales = app(Locales::class);
        $multilingual = $locales->enabled();
        $alternatesOf = fn ($model) => $multilingual ? $model->alternateUrls() : [];

        $homes = [];
        $blogs = [];
        foreach ($locales->codes() as $code) {
            $homes[$code] = $locales->url('/', $code);
            $blogs[$code] = press_route('blog', [], $code);
        }

        $urls = [];
        foreach ($locales->codes() as $code) {
            $urls[] = ['loc' => $homes[$code], 'lastmod' => null, 'alternates' => $multilingual ? $homes : []];
        }

        Page::query()->published()->where('noindex', false)->orderBy('locale')->orderBy('path')->get()
            ->reject(fn (Page $page) => $page->isHome())
            ->each(function (Page $page) use (&$urls, $alternatesOf) {
                $urls[] = ['loc' => $page->url(), 'lastmod' => $page->updated_at?->toAtomString(), 'alternates' => $alternatesOf($page)];
            });

        foreach ($locales->codes() as $code) {
            $urls[] = ['loc' => $blogs[$code], 'lastmod' => null, 'alternates' => $multilingual ? $blogs : []];
        }

        Post::query()->published()->latest('published_at')->get()->each(function (Post $post) use (&$urls, $alternatesOf) {
            $urls[] = ['loc' => $post->url(), 'lastmod' => $post->updated_at?->toAtomString(), 'alternates' => $alternatesOf($post)];
        });

        Category::query()->whereHas('posts', fn ($q) => $q->published())->get()->each(function (Category $category) use (&$urls, $alternatesOf) {
            $urls[] = ['loc' => $category->url(), 'lastmod' => null, 'alternates' => $alternatesOf($category)];
        });

        return apply_filters('sitemap.urls', $urls);
    }
}
