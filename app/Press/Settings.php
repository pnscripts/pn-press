<?php

namespace App\Press;

use App\Models\Setting;
use App\Press\I18n\Locales;
use Illuminate\Support\Arr;
use Throwable;

/**
 * Site settings stored as key/value JSON rows, loaded once per request.
 *
 * Keys are dotted strings: "site.name", "reading.home_page_id", "theme.active",
 * "theme.folio" (an array of that theme's settings), "plugin.contact-form" ...
 */
class Settings
{
    /** @var array<string, mixed>|null */
    private ?array $values = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $values = $this->all();

        return array_key_exists($key, $values) && $values[$key] !== null ? $values[$key] : $default;
    }

    public function set(string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);

        $this->values = null;
        $this->changed();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        $this->values = null;
        $this->changed();
    }

    public function forget(string $key): void
    {
        Setting::query()->where('key', $key)->delete();

        $this->values = null;
        $this->changed();
    }

    /** Forget a key and every key below it ("plugin.contact-form" and "plugin.contact-form.*"). */
    public function forgetTree(string $key): void
    {
        Setting::query()->where('key', $key)->orWhere('key', 'like', $key.'.%')->delete();

        $this->values = null;
        $this->changed();
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->values !== null) {
            return $this->values;
        }

        try {
            return $this->values = Setting::query()->pluck('value', 'key')->all();
        } catch (Throwable) {
            // Not migrated yet (fresh install, package:discover): behave as empty, but retry next call.
            return [];
        }
    }

    /**
     * Settings under a prefix, without the prefix: group('site') => ['name' => ...].
     *
     * @return array<string, mixed>
     */
    public function group(string $prefix): array
    {
        $prefix = rtrim($prefix, '.').'.';

        return collect($this->all())
            ->filter(fn ($value, $key) => str_starts_with($key, $prefix))
            ->mapWithKeys(fn ($value, $key) => [substr($key, strlen($prefix)) => $value])
            ->all();
    }

    /**
     * A setting in a language: "site.name" in Bulgarian is stored as "site.i18n.bg.name" and falls back
     * to "site.name" when empty. The default language always reads the plain key.
     */
    public function localized(string $key, ?string $locale = null, mixed $default = null): mixed
    {
        $locales = app(Locales::class);
        $locale ??= $locales->current();

        if ($locale !== $locales->default() && str_contains($key, '.')) {
            [$group, $rest] = explode('.', $key, 2);
            $value = $this->get($group.'.i18n.'.$locale.'.'.$rest);

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return $this->get($key, $default);
    }

    /** The product name a fresh install shows until Settings › Site name is filled in. */
    public const DEFAULT_SITE_NAME = 'PN Scripts Press';

    /** APP_NAME values that are only defaults (Laravel's and this kit's earlier names), not a real site name. */
    public const STOCK_APP_NAMES = ['Laravel', 'Laravel Blog CMS', 'PN Press'];

    /**
     * Settings › Site name, else APP_NAME, else "PN Scripts Press". Stock APP_NAMEs count as unset.
     */
    public function siteName(): string
    {
        $name = trim((string) $this->localized('site.name', null, ''));

        if ($name !== '') {
            return $name;
        }

        $appName = trim((string) config('app.name'));

        return $appName !== '' && ! in_array($appName, self::STOCK_APP_NAMES, true) ? $appName : self::DEFAULT_SITE_NAME;
    }

    /** Tells listeners (the page cache) that a setting changed. */
    private function changed(): void
    {
        app(Hooks::class)->doAction('settings.changed');
    }

    public function flush(): void
    {
        $this->values = null;
    }

    public function dot(string $key, string $path, mixed $default = null): mixed
    {
        return Arr::get((array) $this->get($key, []), $path, $default);
    }
}
