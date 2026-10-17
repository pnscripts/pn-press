<?php

namespace App\Models;

use App\Enums\PostStatus;
use App\Models\Concerns\HasTranslations;
use App\Press\I18n\Locales;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int|null $category_id
 * @property string $title
 * @property string $slug
 * @property PostStatus $status
 * @property Carbon|null $published_at
 * @property string|null $featured_image
 * @property string $locale
 * @property string|null $translation_group
 */
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    use HasTranslations;

    protected $fillable = [
        'user_id',
        'category_id',
        'title',
        'slug',
        'excerpt',
        'body',
        'status',
        'published_at',
        'featured_image',
        'meta_title',
        'meta_description',
        'locale',
        'translation_group',
    ];

    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Post $post): void {
            if (blank($post->slug) && filled($post->title)) {
                $post->slug = Str::slug($post->title);
            }

            if ($post->status === PostStatus::Published && $post->published_at === null) {
                $post->published_at = now();
            }
        });

        // A published post whose slug changed keeps answering at its old address.
        static::updated(function (Post $post): void {
            if ($post->wasChanged('slug') && $post->getOriginal('status') === PostStatus::Published) {
                $old = route(app(Locales::class)->routeName('posts.show', $post->locale), (string) $post->getOriginal('slug'), false);
                Redirect::moved($old, parse_url($post->url(), PHP_URL_PATH) ?: '/');
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('status', PostStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function url(): string
    {
        return route(app(Locales::class)->routeName('posts.show', $this->locale), $this->slug);
    }

    public function isPubliclyVisible(): bool
    {
        return $this->status === PostStatus::Published && $this->published_at !== null && $this->published_at->lte(now());
    }

    public function featuredImageUrl(): ?string
    {
        if (blank($this->featured_image)) {
            return null;
        }

        if (str_starts_with($this->featured_image, 'http://') || str_starts_with($this->featured_image, 'https://')) {
            return $this->featured_image;
        }

        // A leading slash means a file inside public/ (the demo covers live in public/images/demo).
        if (str_starts_with($this->featured_image, '/')) {
            return asset(ltrim($this->featured_image, '/'));
        }

        return asset('storage/'.$this->featured_image);
    }

    public function readingTimeMinutes(): int
    {
        $words = str_word_count(strip_tags((string) $this->body));

        return max(1, (int) ceil($words / 200));
    }
}
