{{-- Language switcher: links to this page in each language (its home page when there is no translation). Themes may override this file. --}}
@php($languages = app(\App\Press\I18n\Locales::class)->switcher())
@if ($languages !== [])
    <ul class="press-languages ml-1 flex items-center gap-1 border-l border-rule pl-2 text-xs font-semibold uppercase tracking-wide" aria-label="{{ __('Language') }}">
        @foreach ($languages as $language)
            <li>
                @if ($language['current'])
                    <span aria-current="true" lang="{{ $language['code'] }}" title="{{ $language['name'] }}" class="rounded-full bg-ink px-2 py-1 text-paper">{{ $language['code'] }}</span>
                @else
                    <a href="{{ $language['url'] }}" hreflang="{{ $language['code'] }}" lang="{{ $language['code'] }}" title="{{ $language['name'] }}" rel="alternate" class="rounded-full px-2 py-1 text-muted hover:bg-rule/60 hover:text-ink">{{ $language['code'] }}<span class="sr-only"> — {{ $language['name'] }}</span></a>
                @endif
            </li>
        @endforeach
    </ul>
@endif
