<?php

namespace PnPress\SeoSitemap\Http;

use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
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
     * @return list<array{loc: string, lastmod: ?string}>
     */
    private function urls(): array
    {
        $urls = [['loc' => url('/'), 'lastmod' => null]];

        Page::query()->published()->where('noindex', false)->orderBy('path')->get()
            ->reject(fn (Page $page) => $page->isHome())
            ->each(function (Page $page) use (&$urls) {
                $urls[] = ['loc' => $page->url(), 'lastmod' => $page->updated_at?->toAtomString()];
            });

        $urls[] = ['loc' => route('blog'), 'lastmod' => null];

        Post::query()->published()->latest('published_at')->get()->each(function (Post $post) use (&$urls) {
            $urls[] = ['loc' => route('posts.show', $post->slug), 'lastmod' => $post->updated_at?->toAtomString()];
        });

        Category::query()->whereHas('posts', fn ($q) => $q->published())->get()->each(function (Category $category) use (&$urls) {
            $urls[] = ['loc' => route('categories.show', $category->slug), 'lastmod' => null];
        });

        return apply_filters('sitemap.urls', $urls);
    }
}
