<?php

use Illuminate\Support\Facades\Route;
use PnPress\SeoSitemap\Http\SitemapController;

// Loaded with the "web" middleware; part of `php artisan route:cache`.
Route::get('/sitemap.xml', SitemapController::class)->name('seo-sitemap');
