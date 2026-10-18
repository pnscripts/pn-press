<?php

namespace App\Models\Concerns;

use App\Press\I18n\Locales;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * A record written in one language (`locale`) and linked to its translations through a shared
 * `translation_group`. Each translation has its own slug/path, so URLs are per language.
 *
 * @property string $locale
 * @property string|null $translation_group
 */
trait HasTranslations
{
    public static function bootHasTranslations(): void
    {
        static::creating(function ($model): void {
            $locales = app(Locales::class);

            if (! $locales->isSupported($model->locale ?: null)) {
                $model->locale = $locales->default();
            }
        });
    }

    /** Records in one language (the current one when none is given). */
    public function scopeInLocale(Builder $query, ?string $locale = null): void
    {
        $query->where($this->qualifyColumn('locale'), $locale ?? app(Locales::class)->current());
    }

    /**
     * The other language versions of this record.
     *
     * @return Collection<int, static>
     */
    public function translations(): Collection
    {
        if (blank($this->translation_group)) {
            return new Collection;
        }

        return static::query()
            ->where('translation_group', $this->translation_group)
            ->whereKeyNot($this->getKey())
            ->get();
    }

    public function translation(string $locale): ?static
    {
        if ($locale === $this->locale) {
            return $this;
        }

        return $this->translations()->firstWhere('locale', $locale);
    }

    /** Joins this record to another one's translation group (giving that one a group first if needed). */
    public function linkTranslationTo(self $other): void
    {
        if (blank($other->translation_group)) {
            $other->translation_group = (string) Str::uuid();
            $other->saveQuietly();
        }

        $this->translation_group = $other->translation_group;
        $this->saveQuietly();
    }

    /** @return list<string> languages this record is not yet translated into */
    public function missingLocales(): array
    {
        $have = $this->translations()->pluck('locale')->push($this->locale)->all();

        return array_values(array_diff(app(Locales::class)->codes(), $have));
    }

    /**
     * URLs of the public language versions, this one included: locale => URL.
     *
     * @return array<string, string>
     */
    public function alternateUrls(): array
    {
        $urls = [];

        foreach ($this->translations()->push($this) as $version) {
            if ($version->isPubliclyVisible()) {
                $urls[$version->locale] = $version->url();
            }
        }

        return $urls;
    }

    abstract public function url(): string;

    abstract public function isPubliclyVisible(): bool;
}
