<?php

namespace App\Filament\Support;

use App\Enums\PostStatus;
use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Press\I18n\Locales;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;

/**
 * Admin pieces for multilingual content: the Language field, the language column/filter and
 * the "Translate" action, which copies an item into another language as a linked draft.
 * All of them hide themselves on a single-language site.
 */
class Translatable
{
    public static function enabled(): bool
    {
        return app(Locales::class)->enabled();
    }

    public static function localeField(): Select
    {
        return Select::make('locale')
            ->label('Language')
            ->options(fn () => app(Locales::class)->options())
            ->default(fn () => app(Locales::class)->default())
            ->required()
            ->live()
            ->in(fn () => app(Locales::class)->codes())
            ->visible(fn () => self::enabled())
            ->helperText('Each language version has its own address. Use “Translate” in the list to add another language.');
    }

    /** The language the form is editing (the default one on a single-language site). */
    public static function formLocale(Get $get): string
    {
        $locale = $get('locale');

        return is_string($locale) && app(Locales::class)->isSupported($locale) ? $locale : app(Locales::class)->default();
    }

    /** "unique" scoped to the record's language, so /about and /bg/about can share a slug. */
    public static function uniquePerLocale(): \Closure
    {
        return fn (Unique $rule, Get $get) => $rule->where('locale', self::formLocale($get));
    }

    public static function column(): TextColumn
    {
        return TextColumn::make('locale')
            ->label('Language')
            ->badge()
            ->formatStateUsing(fn (?string $state) => strtoupper((string) $state))
            ->description(fn (Model $record) => self::otherLanguages($record))
            ->visible(fn () => self::enabled());
    }

    public static function filter(): SelectFilter
    {
        return SelectFilter::make('locale')
            ->label('Language')
            ->options(fn () => app(Locales::class)->options())
            ->visible(fn () => self::enabled());
    }

    public static function action(string $resource): Action
    {
        return Action::make('translate')
            ->label('Translate')
            ->icon('heroicon-o-language')
            ->visible(fn (Page|Post|Category $record) => self::enabled() && $record->missingLocales() !== [])
            ->schema([
                Select::make('target')
                    ->label('Into')
                    ->options(fn (Page|Post|Category $record) => collect($record->missingLocales())->mapWithKeys(fn ($code) => [$code => app(Locales::class)->name($code)])->all())
                    ->required(),
            ])
            ->modalDescription('A draft copy in that language is created and linked to this one. Translate its text, then publish it.')
            ->action(function (Page|Post|Category $record, array $data, Action $action) use ($resource) {
                $copy = self::translate($record, (string) $data['target']);

                Notification::make()->title('Draft translation created')->success()->send();

                $action->redirect($resource::getUrl('edit', ['record' => $copy]));
            });
    }

    /**
     * Copies a page, post or category into another language as a linked draft. Parent page and
     * category point at their own translations when those exist.
     */
    public static function translate(Page|Post|Category $record, string $locale): Page|Post|Category
    {
        abort_unless(app(Locales::class)->isSupported($locale) && in_array($locale, $record->missingLocales(), true), 422);

        $copy = $record->replicate(['path', 'translation_group']);
        $copy->locale = $locale;

        if ($record instanceof Page || $record instanceof Post) {
            $copy->status = PostStatus::Draft;
            $copy->published_at = null;
        }

        if ($record instanceof Page) {
            $copy->parent_id = $record->parent?->translation($locale)?->getKey();
            $copy->skipRevision = true;
        }

        if ($record instanceof Post) {
            $copy->category_id = $record->category?->translation($locale)?->getKey();
        }

        if ($record instanceof Category) {
            $copy->slug = $record->slug;
        }

        $copy->save();
        $copy->linkTranslationTo($record);

        return $copy;
    }

    private static function otherLanguages(Model $record): ?string
    {
        if (! method_exists($record, 'translations')) {
            return null;
        }

        $codes = $record->translations()->pluck('locale')->map(fn ($code) => strtoupper((string) $code))->sort()->implode(', ');

        return $codes !== '' ? 'Also in '.$codes : null;
    }
}
