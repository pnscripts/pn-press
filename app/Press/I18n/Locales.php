<?php

namespace App\Press\I18n;

use Carbon\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;

/**
 * The site's languages (config press.locales). The default language has no URL prefix;
 * every other one lives under "/{code}". With one language nothing changes: no prefixes,
 * no switcher, no hreflang.
 *
 * Controllers report the current item's translations with setAlternates(); the layout
 * prints them as hreflang links and the language switcher uses them.
 */
class Locales
{
    /** @var array<string, string>|null locale => URL of this page in that language */
    private ?array $alternates = null;

    /** @return list<string> */
    public function codes(): array
    {
        $codes = [];

        foreach ((array) config('press.locales', ['en']) as $code) {
            $code = strtolower(trim((string) $code));

            // Two or three letters, optional region (pt-br). Anything else is ignored.
            if (preg_match('/^[a-z]{2,3}(-[a-z0-9]{2,8})?$/', $code) && ! in_array($code, $codes, true)) {
                $codes[] = $code;
            }
        }

        return $codes !== [] ? $codes : [$this->fallback()];
    }

    public function default(): string
    {
        $configured = strtolower((string) config('press.default_locale'));
        $codes = $this->codes();

        return in_array($configured, $codes, true) ? $configured : $codes[0];
    }

    public function enabled(): bool
    {
        return count($this->codes()) > 1;
    }

    public function isSupported(?string $code): bool
    {
        return $code !== null && in_array($code, $this->codes(), true);
    }

    /** @return list<string> languages that have a URL prefix */
    public function prefixed(): array
    {
        return array_values(array_diff($this->codes(), [$this->default()]));
    }

    public function current(): string
    {
        $locale = strtolower(str_replace('_', '-', App::getLocale()));

        return $this->isSupported($locale) ? $locale : $this->default();
    }

    public function name(string $code): string
    {
        return (string) (config('press.locale_names')[$code] ?? strtoupper($code));
    }

    /** @return array<string, string> code => name, for selects */
    public function options(): array
    {
        $out = [];

        foreach ($this->codes() as $code) {
            $out[$code] = $this->name($code);
        }

        return $out;
    }

    /** Switches the app (translations, dates) to a language. */
    public function activate(string $code): void
    {
        $code = $this->isSupported($code) ? $code : $this->default();

        App::setLocale($code);
        Carbon::setLocale($code);
    }

    public function prefix(?string $code = null): string
    {
        $code ??= $this->current();

        return $code === $this->default() || ! $this->isSupported($code) ? '' : $code;
    }

    /** "/about" in Bulgarian => "/bg/about"; the default language keeps the path as it is. */
    public function path(string $path, ?string $code = null): string
    {
        $prefix = $this->prefix($code);
        $path = '/'.ltrim($path, '/');

        if ($prefix === '') {
            return $path;
        }

        return $path === '/' ? '/'.$prefix : '/'.$prefix.$path;
    }

    public function url(string $path, ?string $code = null): string
    {
        return url($this->path($path, $code));
    }

    /** The name of a core public route ("blog", "posts.show"…) in a language: "bg.blog". */
    public function routeName(string $name, ?string $code = null): string
    {
        $prefix = $this->prefix($code);
        $localized = $prefix === '' ? $name : $prefix.'.'.$name;

        return Route::has($localized) ? $localized : $name;
    }

    /**
     * Splits a request path into language and the rest: "bg/about" => ["bg", "about"].
     *
     * @return array{0: string, 1: string}
     */
    public function split(string $path): array
    {
        $path = trim($path, '/');
        $first = explode('/', $path, 2)[0];

        if ($first !== '' && in_array($first, $this->prefixed(), true)) {
            return [$first, trim(substr($path, strlen($first)), '/')];
        }

        return [$this->default(), $path];
    }

    /**
     * @param  array<string, string>  $urls  locale => URL
     */
    public function setAlternates(array $urls): void
    {
        $this->alternates = array_intersect_key($urls, array_flip($this->codes()));
    }

    /** @return array<string, string> locale => URL of the current page in each language it exists in */
    public function alternates(): array
    {
        return $this->alternates ?? [$this->current() => url()->current()];
    }

    /**
     * One entry per language for the switcher: the translation when there is one, else that language's home page.
     *
     * @return list<array{code: string, name: string, url: string, current: bool, translated: bool}>
     */
    public function switcher(): array
    {
        if (! $this->enabled()) {
            return [];
        }

        $alternates = $this->alternates();
        $out = [];

        foreach ($this->codes() as $code) {
            $out[] = [
                'code' => $code,
                'name' => $this->name($code),
                'url' => $alternates[$code] ?? $this->url('/', $code),
                'current' => $code === $this->current(),
                'translated' => isset($alternates[$code]),
            ];
        }

        return $out;
    }

    public function forget(): void
    {
        $this->alternates = null;
    }

    private function fallback(): string
    {
        return strtolower((string) config('app.locale', 'en')) ?: 'en';
    }
}
