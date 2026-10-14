<section class="press-container py-12">
    <div class="flex flex-col gap-6 rounded-sm bg-ink p-8 text-paper sm:flex-row sm:items-center sm:justify-between sm:p-12">
        <div>
            <h2 class="font-display text-3xl font-semibold tracking-tight">{{ $data['heading'] ?? '' }}</h2>
            @if (!empty($data['text']))
                <p class="mt-2 max-w-xl opacity-80">{{ $data['text'] }}</p>
            @endif
        </div>
        @if ($href = \App\Models\MenuItem::safeUrl($data['button_url'] ?? null))
            <a href="{{ $href }}" class="inline-flex shrink-0 items-center gap-2 rounded-full bg-highlight px-6 py-3 font-semibold text-[#1b1a17]">{{ $data['button_label'] ?? 'Learn more' }} <span aria-hidden="true">→</span></a>
        @endif
    </div>
</section>
