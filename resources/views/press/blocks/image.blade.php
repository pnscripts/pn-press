@php($media = !empty($data['media_id']) ? \App\Models\Media::find($data['media_id']) : null)
@if ($media)
    <figure @class(['py-8', 'press-container max-w-3xl' => empty($data['wide']), 'press-container' => !empty($data['wide'])])>
        <img src="{{ $media->url() }}" alt="{{ $media->alt }}" loading="lazy" class="w-full rounded-sm">
        @if (!empty($data['caption']) || $media->caption)
            <figcaption class="mt-3 border-l-2 border-highlight pl-3 text-sm text-muted">{{ $data['caption'] ?: $media->caption }}</figcaption>
        @endif
    </figure>
@endif
