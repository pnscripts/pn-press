<?php

namespace App\Models;

use App\Enums\PostStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    public const TYPES = [
        'page' => 'Page',
        'post' => 'Post',
        'category' => 'Category',
        'blog' => 'Blog index',
        'url' => 'Custom link',
    ];

    protected $fillable = ['menu_id', 'parent_id', 'label', 'type', 'page_id', 'post_id', 'category_id', 'url', 'open_in_new_tab', 'sort_order'];

    protected function casts(): array
    {
        return [
            'open_in_new_tab' => 'boolean',
        ];
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'parent_id')->orderBy('sort_order');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * The link target, or null when it points at something that is not public (draft page, deleted post).
     */
    public function resolveUrl(): ?string
    {
        return match ($this->type) {
            'page' => $this->page?->isPublic() ? $this->page->url() : null,
            'post' => $this->post && $this->post->status === PostStatus::Published && $this->post->published_at?->isPast()
                ? route('posts.show', $this->post->slug) : null,
            'category' => $this->category ? route('categories.show', $this->category->slug) : null,
            'blog' => route('blog'),
            default => self::safeUrl($this->url),
        };
    }

    /** Only http(s), mailto, tel, relative paths and fragments. Never javascript: or data:. */
    public static function safeUrl(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return $url;
        }

        return preg_match('/^(https?:|mailto:|tel:)/i', $url) ? $url : null;
    }
}
