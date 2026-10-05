<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Post;
use Illuminate\Support\Collection;

class PostController extends Controller
{
    public function index()
    {
        $posts = Post::query()
            ->published()
            ->with('category')
            ->latest('published_at')
            ->paginate(10);

        $categories = self::categoriesWithPosts();

        return view('posts.index', compact('posts', 'categories'));
    }

    public function show(string $slug)
    {
        $post = Post::query()
            ->published()
            ->with(['category', 'author'])
            ->where('slug', $slug)
            ->firstOrFail();

        $related = Post::query()
            ->published()
            ->with('category')
            ->whereKeyNot($post->getKey())
            ->when($post->category_id, fn ($query) => $query->where('category_id', $post->category_id))
            ->latest('published_at')
            ->limit(3)
            ->get();

        return view('posts.show', compact('post', 'related'));
    }

    /**
     * Categories that have at least one published post, for the category navigation.
     *
     * @return Collection<int, Category>
     */
    public static function categoriesWithPosts(): Collection
    {
        return Category::query()
            ->whereHas('posts', fn ($query) => $query->published())
            ->withCount(['posts' => fn ($query) => $query->published()])
            ->orderBy('name')
            ->get();
    }
}
