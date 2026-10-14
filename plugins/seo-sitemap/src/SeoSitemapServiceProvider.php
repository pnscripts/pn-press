<?php

namespace PnPress\SeoSitemap;

use Illuminate\Support\ServiceProvider;

/**
 * Example plugin: a route file (/sitemap.xml), a render hook and a filter other plugins can extend.
 */
class SeoSitemapServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        add_action('head', fn () => '<link rel="sitemap" type="application/xml" title="Sitemap" href="'.e(url('/sitemap.xml')).'">');
    }
}
