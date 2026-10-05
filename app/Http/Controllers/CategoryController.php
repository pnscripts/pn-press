<?php

namespace App\Http\Controllers;

use App\Models\Category;

class CategoryController extends Controller
{
    public function show(string $slug)
    {
        $category = Category::query()
            ->where('slug', $slug)
            ->firstOrFail();

        $posts = $category->posts()
            ->published()
            ->with('category')
            ->latest('published_at')
            ->paginate(10);

        $categories = PostController::categoriesWithPosts();

        return view('categories.show', compact('category', 'posts', 'categories'));
    }
}
