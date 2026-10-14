<section class="press-container max-w-4xl py-12">
    <blockquote class="border-l-4 border-highlight pl-6">
        <p class="font-display text-3xl italic leading-snug sm:text-4xl">“{{ $data['text'] ?? '' }}”</p>
        @if (!empty($data['cite']))
            <footer class="mt-4 text-sm font-semibold uppercase tracking-widest text-muted">— {{ $data['cite'] }}</footer>
        @endif
    </blockquote>
</section>
