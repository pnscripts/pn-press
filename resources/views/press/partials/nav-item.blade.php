{{--
    One menu item, nested as deep as the menu goes (config press.menus.max_depth).
    Items with children are <details> disclosures: they open with Enter/Space and work without JavaScript;
    press.partials.menu-script adds Escape, arrow keys, closing other menus and closing on outside clicks.
    $level: 0 for the menu bar, 1 for a dropdown, 2+ for sub-menus inside a dropdown.
--}}
@php($level = $level ?? 0)
@if (! empty($item['children']))
    <details class="group/menu relative" data-press-menu data-level="{{ $level }}">
        <summary @class([
            'flex cursor-pointer list-none items-center gap-1 [&::-webkit-details-marker]:hidden focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent',
            'whitespace-nowrap rounded-full px-3 py-2 transition hover:bg-rule/60' => $level === 0,
            'w-full justify-between rounded-md px-3 py-2 hover:bg-paper' => $level > 0,
            'bg-ink text-paper hover:bg-ink' => $level === 0 && $item['active'],
            'text-accent' => $level > 0 && $item['active'],
        ])>
            <span>{{ $item['label'] }}</span>
            <span aria-hidden="true" class="text-xs transition group-open/menu:rotate-180">▾</span>
        </summary>
        <ul role="list" @class([
            'absolute left-0 z-30 mt-2 min-w-56 sm:left-auto sm:right-0 rounded-lg border border-rule bg-surface p-1.5 text-ink shadow-lg' => $level === 0,
            'ml-3 mt-1 border-l border-rule pl-2' => $level > 0,
        ])>
            {{-- The parent's own link, which the disclosure button would otherwise hide. --}}
            <li>
                <a href="{{ $item['url'] }}"
                   @class(['block rounded-md px-3 py-2 font-semibold hover:bg-paper focus-visible:outline-2 focus-visible:outline-accent', 'text-accent' => $item['current']])
                   @if ($item['current']) aria-current="page" @endif
                   @if ($item['new_tab']) target="_blank" rel="noopener" @endif>{{ $item['label'] }}</a>
            </li>
            @foreach ($item['children'] as $child)
                <li>@include('press.partials.nav-item', ['item' => $child, 'level' => $level + 1])</li>
            @endforeach
        </ul>
    </details>
@elseif ($level === 0)
    <a href="{{ $item['url'] }}"
       @class(['whitespace-nowrap rounded-full px-3 py-2 transition hover:bg-rule/60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent', 'bg-ink text-paper hover:bg-ink' => $item['active']])
       @if ($item['current']) aria-current="page" @endif
       @if ($item['new_tab']) target="_blank" rel="noopener" @endif>{{ $item['label'] }}</a>
@else
    <a href="{{ $item['url'] }}"
       @class(['block rounded-md px-3 py-2 hover:bg-paper focus-visible:outline-2 focus-visible:outline-accent', 'text-accent' => $item['active']])
       @if ($item['current']) aria-current="page" @endif
       @if ($item['new_tab']) target="_blank" rel="noopener" @endif>{{ $item['label'] }}</a>
@endif
