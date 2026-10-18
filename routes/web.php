<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ThemeAssetController;
use App\Press\I18n\Locales;
use Illuminate\Support\Facades\Route;

// The public site once per language: "/blog" for the default language, "/bg/blog" (route "bg.blog") for the others.
$publicRoutes = function (): void {
    Route::get('/', HomeController::class)->name('home');
    Route::get('/blog', [PostController::class, 'index'])->name('blog');
    Route::get('/blog/{slug}', [PostController::class, 'show'])->name('posts.show');
    Route::get('/category/{slug}', [CategoryController::class, 'show'])->name('categories.show');
};

$publicRoutes();

foreach (app(Locales::class)->prefixed() as $locale) {
    Route::prefix($locale)->name($locale.'.')->group($publicRoutes);
}

Route::get('/theme-assets/{theme}/{path}', ThemeAssetController::class)
    ->where('path', '.*')
    ->name('press.theme-asset');

// CMS pages answer any path no other route (core, plugin or admin) claimed, in every language.
Route::fallback(PageController::class)->name('pages.show');
