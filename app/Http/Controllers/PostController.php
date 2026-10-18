<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Post;
use App\Press\I18n\Locales;
use Illuminate\Support\Collection;

class PostController extends Controller
{
    public function index(Locales $locales)
    {
        $posts = Post::query()
            ->inLocale()
            ->published()
            ->with('category')
            ->latest('published_at')
            ->paginate((int) press_setting('reading.posts_per_page', 10))
            ->withPath(press_route('blog'));

        if (! request()->routeIs('home', '*.home')) {
            $locales->setAlternates(collect($locales->codes())->mapWithKeys(fn ($code) => [$code => press_route('blog', [], $code)])->all());
        }

        $categories = self::categoriesWithPosts();

        return view('posts.index', compact('posts', 'categories'));
    }

    public function show(Locales $locales, string $slug)
    {
        $post = Post::query()
            ->inLocale()
            ->published()
            ->with(['category', 'author'])
            ->where('slug', $slug)
            ->firstOrFail();

        $locales->setAlternates($post->alternateUrls());

        $related = Post::query()
            ->inLocale()
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
     * Categories of the current language that have at least one published post, for the category navigation.
     *
     * @return Collection<int, Category>
     */
    public static function categoriesWithPosts(): Collection
    {
        return Category::query()
            ->inLocale()
            ->whereHas('posts', fn ($query) => $query->published())
            ->withCount(['posts' => fn ($query) => $query->published()])
            ->orderBy('name')
            ->get();
    }
}
