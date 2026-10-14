{{-- Media library grid for App\Filament\Forms\MediaPicker. Native radio buttons, so arrow keys move between images.
     Inline styles: the panel's CSS is prebuilt and does not include app Tailwind classes. --}}
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <style>
        .press-media-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(7.5rem, 1fr)); gap: .75rem; max-height: 22rem; overflow-y: auto; padding: .25rem; }
        .press-media-tile { position: relative; display: flex; flex-direction: column; gap: .35rem; padding: .35rem; border-radius: .6rem; border: 2px solid transparent; cursor: pointer; }
        .press-media-tile:hover { background: rgba(120, 113, 108, .08); }
        .press-media-tile:has(input:checked) { border-color: var(--primary-600, #0f6e66); background: rgba(15, 110, 102, .08); }
        .press-media-tile:has(input:focus-visible) { outline: 2px solid var(--primary-500, #1a8f82); outline-offset: 2px; }
        .press-media-tile input { position: absolute; opacity: 0; pointer-events: none; }
        .press-media-tile img { width: 100%; aspect-ratio: 1; object-fit: cover; border-radius: .4rem; background: #e7e5e4; }
        .press-media-name { font-size: .75rem; line-height: 1.2; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .press-media-meta { font-size: .7rem; opacity: .65; }
        .press-media-check { position: absolute; top: .6rem; right: .6rem; display: none; width: 1.4rem; height: 1.4rem; border-radius: 999px; background: var(--primary-600, #0f6e66); color: #fff; font-size: .8rem; line-height: 1.4rem; text-align: center; }
        .press-media-tile:has(input:checked) .press-media-check { display: block; }
    </style>

    @if ($items === [])
        <p style="font-size: .875rem; opacity: .75;">No images found. Upload one below, or add some in Content → Media.</p>
    @else
        <div class="press-media-grid" role="radiogroup" aria-label="Images in the media library">
            @foreach ($items as $item)
                <label class="press-media-tile" wire:key="press-media-{{ $item['id'] }}">
                    <input type="radio" name="{{ $getStatePath() }}" value="{{ $item['id'] }}" wire:model="{{ $getStatePath() }}">
                    <img src="{{ $item['url'] }}" alt="{{ $item['alt'] }}" loading="lazy">
                    <span class="press-media-check" aria-hidden="true">✓</span>
                    <span class="press-media-name" title="{{ $item['name'] }}">{{ $item['name'] }}</span>
                    <span class="press-media-meta">{{ $item['size'] }}</span>
                </label>
            @endforeach
        </div>
    @endif
</x-dynamic-component>
