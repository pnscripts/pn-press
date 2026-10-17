<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Press\I18n\Locales;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $locale
 * @property string|null $translation_group
 */
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    use HasTranslations;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'locale',
        'translation_group',
    ];

    protected static function booted(): void
    {
        static::saving(function (Category $category): void {
            if (blank($category->slug) && filled($category->name)) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    /** @return HasMany<Post, $this> */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function url(): string
    {
        return route(app(Locales::class)->routeName('categories.show', $this->locale), $this->slug);
    }

    /** A category page is shown when it has published posts. */
    public function isPubliclyVisible(): bool
    {
        return $this->posts()->published()->exists();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
