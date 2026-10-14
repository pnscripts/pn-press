@php($media = !empty($data['media_id']) ? \App\Models\Media::find($data['media_id']) : null)
<section class="press-container py-12 sm:py-20">
    <div @class(['grid items-center gap-10', 'md:grid-cols-2' => $media])>
        <div>
            @if (!empty($data['eyebrow']))
                <p class="press-eyebrow">{{ $data['eyebrow'] }}</p>
            @endif
            <h2 class="mt-3 font-display text-4xl font-semibold leading-[1.05] tracking-tight sm:text-6xl">{{ $data['heading'] ?? '' }}</h2>
            @if (!empty($data['text']))
                <p class="mt-5 max-w-xl text-lg text-muted">{{ $data['text'] }}</p>
            @endif
            @if (!empty($data['button_label']) && ($href = \App\Models\MenuItem::safeUrl($data['button_url'] ?? null)))
                <a href="{{ $href }}" class="press-button mt-8">{{ $data['button_label'] }} <span aria-hidden="true">→</span></a>
            @endif
        </div>
        @if ($media)
            <img src="{{ $media->url() }}" alt="{{ $media->alt }}" class="aspect-[4/3] w-full rounded-sm object-cover shadow-[12px_12px_0_var(--press-highlight)]">
        @endif
    </div>
</section>
