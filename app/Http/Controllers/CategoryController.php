<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Press\I18n\Locales;

class CategoryController extends Controller
{
    public function show(Locales $locales, string $slug)
    {
        $category = Category::query()
            ->inLocale()
            ->where('slug', $slug)
            ->firstOrFail();

        $locales->setAlternates($category->alternateUrls() + [$category->locale => $category->url()]);

        $posts = $category->posts()
            ->published()
            ->with('category')
            ->latest('published_at')
            ->paginate(10);

        $categories = PostController::categoriesWithPosts();

        return view('categories.show', compact('category', 'posts', 'categories'));
    }
}
