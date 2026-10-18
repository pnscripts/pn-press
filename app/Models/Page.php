<?php

namespace App\Models;

use App\Enums\PostStatus;
use App\Models\Concerns\HasTranslations;
use App\Press\I18n\Locales;
use App\Press\Settings;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * A CMS page built from blocks (see App\Press\Blocks\BlockRegistry).
 *
 * Every save that changes content keeps the previous version as a PageRevision.
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $title
 * @property string $slug
 * @property string $path
 * @property string $template
 * @property array<int, array<string, mixed>>|null $blocks
 * @property PostStatus $status
 * @property Carbon|null $published_at
 * @property string $locale
 * @property string|null $translation_group
 */
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory;

    use HasTranslations;

    /** Fields copied into a revision and restored from it. */
    public const REVISIONED = ['title', 'slug', 'parent_id', 'template', 'blocks', 'excerpt', 'status', 'published_at', 'meta_title', 'meta_description', 'og_image', 'noindex'];

    protected $fillable = [
        'parent_id', 'user_id', 'title', 'slug', 'template', 'blocks', 'excerpt', 'status', 'published_at',
        'meta_title', 'meta_description', 'og_image', 'noindex', 'sort_order', 'locale', 'translation_group',
    ];

    public bool $skipRevision = false;

    protected function casts(): array
    {
        return [
            'blocks' => 'array',
            'status' => PostStatus::class,
            'published_at' => 'datetime',
            'noindex' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Page $page): void {
            $page->slug = Str::slug(filled($page->slug) ? $page->slug : (string) $page->title) ?: 'page';
            $page->path = $page->buildPath();

            if ($page->status === PostStatus::Published && $page->published_at === null) {
                $page->published_at = now();
            }
        });

        static::updating(function (Page $page): void {
            if ($page->skipRevision || ! $page->isDirty(self::REVISIONED)) {
                return;
            }

            $data = [];
            foreach (self::REVISIONED as $field) {
                $data[$field] = $page->getRawOriginal($field);
            }
            $data['blocks'] = json_decode((string) $data['blocks'], true);
            $data['noindex'] = (bool) $data['noindex'];

            $page->revisions()->create([
                'user_id' => Auth::id(),
                'title' => (string) $page->getOriginal('title'),
                'data' => $data,
            ]);

            $keep = (int) config('press.revisions.keep', 25);
            $page->revisions()->latest('id')->skip($keep)->take(PHP_INT_MAX)->get()->each->delete();
        });

        static::saved(function (Page $page): void {
            // A public page that moved keeps answering at its old address.
            if ($page->wasChanged('path') && $page->getOriginal('status') === PostStatus::Published) {
                $locales = app(Locales::class);
                Redirect::moved($locales->path((string) $page->getOriginal('path'), $page->locale), $locales->path($page->path, $page->locale));
            }

            if ($page->wasChanged('path')) {
                // Children carry the parent's path; re-save them so their paths follow.
                $page->children()->get()->each(function (Page $child): void {
                    $child->skipRevision = true;
                    $child->save();
                });
            }
        });
    }

    /** @return BelongsTo<Page, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'parent_id');
    }

    /** @return HasMany<Page, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(Page::class, 'parent_id')->orderBy('sort_order');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(PageRevision::class);
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('status', PostStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function isPublic(): bool
    {
        return $this->status === PostStatus::Published
            && $this->published_at !== null
            && $this->published_at->lte(now());
    }

    /** The home page of its language (Settings → Reading, per language). */
    public function isHome(): bool
    {
        return (int) app(Settings::class)->localized('reading.home_page_id', $this->locale) === (int) $this->getKey();
    }

    public function url(): string
    {
        return app(Locales::class)->url($this->isHome() ? '/' : $this->path, $this->locale);
    }

    public function isPubliclyVisible(): bool
    {
        return $this->isPublic();
    }

    public function metaTitle(): string
    {
        return filled($this->meta_title) ? $this->meta_title : $this->title;
    }

    public function metaDescription(): ?string
    {
        return filled($this->meta_description) ? $this->meta_description : $this->excerpt;
    }

    public function restoreRevision(PageRevision $revision): void
    {
        $this->fill(collect($revision->data)->only(self::REVISIONED)->all());
        $this->save();
    }

    private function buildPath(): string
    {
        $segments = [$this->slug];
        $parent = $this->parent_id ? Page::query()->find($this->parent_id) : null;
        $guard = 0;

        while ($parent && $guard++ < 10) {
            if ($parent->getKey() === $this->getKey()) {
                break; // A page cannot be its own ancestor.
            }

            array_unshift($segments, $parent->slug);
            $parent = $parent->parent_id ? Page::query()->find($parent->parent_id) : null;
        }

        return implode('/', $segments);
    }
}
