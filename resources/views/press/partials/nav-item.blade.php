@if (! empty($item['children']))
    <details class="group relative">
        <summary @class(['flex cursor-pointer list-none items-center gap-1 rounded-full px-3 py-2 hover:bg-rule/60', 'bg-ink text-paper' => $item['active']])>
            {{ $item['label'] }} <span aria-hidden="true" class="text-xs transition group-open:rotate-180">▾</span>
        </summary>
        <div class="absolute right-0 z-20 mt-2 min-w-48 rounded-lg border border-rule bg-surface p-1.5 shadow-lg">
            <a href="{{ $item['url'] }}" class="block rounded-md px-3 py-2 hover:bg-paper" @if ($item['new_tab']) target="_blank" rel="noopener" @endif>{{ $item['label'] }}</a>
            @foreach ($item['children'] as $child)
                <a href="{{ $child['url'] }}" @class(['block rounded-md px-3 py-2 hover:bg-paper', 'text-accent' => $child['active']]) @if ($child['new_tab']) target="_blank" rel="noopener" @endif>{{ $child['label'] }}</a>
            @endforeach
        </div>
    </details>
@else
    <a href="{{ $item['url'] }}"
       @class(['whitespace-nowrap rounded-full px-3 py-2 transition hover:bg-rule/60', 'bg-ink text-paper hover:bg-ink' => $item['active']])
       @if ($item['active']) aria-current="page" @endif
       @if ($item['new_tab']) target="_blank" rel="noopener" @endif>{{ $item['label'] }}</a>
@endif
