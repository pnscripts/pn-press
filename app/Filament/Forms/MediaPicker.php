<?php

namespace App\Filament\Forms;

use App\Models\Media;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Image picker for blocks: a searchable select whose options show thumbnails, plus a "Browse"
 * button that opens the media library as a grid of thumbnails, with an upload field for a new image.
 * Stores the media id. Filament-native (Select, Action, ViewField, FileUpload).
 */
class MediaPicker
{
    public const LIMIT = 48;

    public static function make(string $name = 'media_id'): Select
    {
        return Select::make($name)
            ->label('Image')
            ->allowHtml()
            ->searchable()
            ->options(fn () => self::options(self::images()->latest()->limit(self::LIMIT)->get()))
            ->getSearchResultsUsing(fn (?string $search) => self::options(self::search($search)))
            ->getOptionLabelUsing(fn ($value) => ($media = self::images()->find($value)) ? self::label($media) : null)
            ->helperText('Type to search, or browse the media library.')
            ->suffixAction(self::browseAction());
    }

    public static function browseAction(): Action
    {
        return Action::make('browseMedia')
            ->label('Browse')
            ->tooltip('Browse the media library')
            ->icon('heroicon-o-photo')
            ->modalHeading('Choose an image')
            ->modalWidth(Width::FourExtraLarge)
            ->modalSubmitActionLabel('Use this image')
            ->fillForm(fn (Select $component) => ['media' => $component->getState()])
            ->schema([
                TextInput::make('search')
                    ->hiddenLabel()
                    ->placeholder('Search by name, alt text or caption')
                    ->prefixIcon('heroicon-o-magnifying-glass')
                    ->live(debounce: 400),
                ViewField::make('media')
                    ->hiddenLabel()
                    ->view('filament.forms.media-grid')
                    ->viewData(fn (Get $get) => ['items' => self::tiles(self::search($get('search')))]),
                FileUpload::make('upload')
                    ->label('Or upload a new image')
                    ->image()
                    ->disk(config('press.media.disk'))
                    ->directory(config('press.media.directory'))
                    ->acceptedFileTypes(self::imageTypes())
                    ->maxSize(config('press.media.max_kb')),
            ])
            ->action(function (array $data, Select $component): void {
                $id = filled($data['upload'] ?? null)
                    ? Media::fromUpload(config('press.media.disk'), $data['upload'], auth()->id())->id
                    : ($data['media'] ?? null);

                if (filled($id)) {
                    $component->state((int) $id);
                }
            });
    }

    /** @return Builder<Media> */
    public static function images(): Builder
    {
        return Media::query()->where('mime_type', 'like', 'image/%');
    }

    /** @return Collection<int, Media> */
    public static function search(?string $search): Collection
    {
        $search = trim((string) $search);
        // % and _ are searched for literally.
        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';

        return self::images()
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereRaw("name like ? escape '!'", [$like])
                ->orWhereRaw("alt like ? escape '!'", [$like])
                ->orWhereRaw("caption like ? escape '!'", [$like])))
            ->latest()
            ->limit(self::LIMIT)
            ->get();
    }

    /**
     * @param  iterable<Media>  $media
     * @return array<int, string> id => HTML label
     */
    public static function options(iterable $media): array
    {
        $options = [];

        foreach ($media as $item) {
            $options[$item->id] = self::label($item);
        }

        return $options;
    }

    /** Option label: thumbnail and name. Everything user-supplied is escaped. */
    public static function label(Media $media): string
    {
        return '<span style="display:flex;align-items:center;gap:.625rem">'
            .'<img src="'.e($media->url()).'" alt="" loading="lazy" style="width:2.25rem;height:2.25rem;flex:none;object-fit:cover;border-radius:.375rem;background:#e7e5e4">'
            .'<span>'.e($media->name).'</span></span>';
    }

    /**
     * @param  iterable<Media>  $media
     * @return list<array{id: int, url: string, name: string, alt: string, size: string}>
     */
    public static function tiles(iterable $media): array
    {
        $tiles = [];

        foreach ($media as $item) {
            $tiles[] = [
                'id' => $item->id,
                'url' => $item->url(),
                'name' => (string) $item->name,
                'alt' => (string) ($item->alt ?: $item->name),
                'size' => $item->width ? $item->width.'×'.$item->height : $item->humanSize(),
            ];
        }

        return $tiles;
    }

    /** @return list<string> */
    private static function imageTypes(): array
    {
        return array_values(array_filter((array) config('press.media.accepted'), fn (string $type) => str_starts_with($type, 'image/')));
    }
}
