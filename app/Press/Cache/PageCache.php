<?php

namespace App\Press\Cache;

use App\Enums\PostStatus;
use App\Models\Page;
use App\Models\Post;
use App\Press\Hooks;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Full-page cache for what visitors see.
 *
 * Only anonymous GET/HEAD requests are served from it, and only 200 HTML/XML responses
 * without a form token are stored. Every entry expires at the latest when the next
 * scheduled post or page goes live, so scheduled publishing works with the cache on.
 * Any change to content, menus, settings, plugins or themes flushes the whole cache
 * (a new version token makes every older entry unreachable).
 */
class PageCache
{
    public const VERSION_KEY = 'press.page_cache.version';

    public function __construct(private readonly Hooks $hooks) {}

    public function enabled(): bool
    {
        return (bool) config('press.page_cache.enabled', false);
    }

    public function store(): Repository
    {
        $store = config('press.page_cache.store');

        return Cache::store(is_string($store) && $store !== '' ? $store : null);
    }

    /** Whether this request may be answered from, or stored in, the cache. */
    public function accepts(Request $request): bool
    {
        if (! $this->enabled() || ! $request->isMethodCacheable() || $request->user() !== null) {
            return false;
        }

        // Only known query keys (pagination) are cached, so random query strings cannot fill the cache store.
        if (array_diff(array_keys($request->query()), (array) config('press.page_cache.query', ['page'])) !== []) {
            return false;
        }

        // Flashed data (validation errors, "thanks" messages, old input) belongs to one visitor.
        if ($request->hasSession() && $request->session()->get('_flash.old', []) !== []) {
            return false;
        }

        foreach ((array) config('press.page_cache.except', []) as $pattern) {
            if (is_string($pattern) && $request->is($pattern)) {
                return false;
            }
        }

        return (bool) $this->hooks->applyFilters('page_cache.accepts', true, $request);
    }

    public function key(Request $request): string
    {
        $query = $request->query();
        ksort($query);

        return 'press.page:'.$this->version().':'.sha1($request->getSchemeAndHttpHost().'/'.trim($request->path(), '/').'?'.http_build_query($query));
    }

    /** @return array{status: int, content: string, headers: array<string, string>}|null */
    public function get(Request $request): ?array
    {
        $entry = $this->store()->get($this->key($request));

        return is_array($entry) && isset($entry['content'], $entry['headers']) ? $entry : null;
    }

    /** Stores the response when it is safe to share with every visitor. Returns whether it did. */
    public function put(Request $request, Response $response): bool
    {
        if (! $this->cacheable($request, $response)) {
            return false;
        }

        $ttl = $this->ttl();
        if ($ttl < 1) {
            return false;
        }

        $this->store()->put($this->key($request), [
            'status' => $response->getStatusCode(),
            'content' => (string) $response->getContent(),
            'headers' => ['Content-Type' => (string) $response->headers->get('Content-Type', 'text/html; charset=UTF-8')],
        ], $ttl);

        return true;
    }

    public function cacheable(Request $request, Response $response): bool
    {
        if ($response->getStatusCode() !== 200 || $response->headers->has('X-Press-No-Cache')) {
            return false;
        }

        // Cookies other than the session and XSRF ones (a plugin's own) mean the page is personal.
        $shared = [config('session.cookie'), 'XSRF-TOKEN'];
        foreach ($response->headers->getCookies() as $cookie) {
            if (! in_array($cookie->getName(), $shared, true)) {
                return false;
            }
        }

        $content = $response->getContent();
        if (! is_string($content) || $content === '' || strlen($content) > (int) config('press.page_cache.max_kb', 2048) * 1024) {
            return false;
        }

        $type = (string) $response->headers->get('Content-Type', 'text/html');
        if (! preg_match('#^(text/html|application/xml|text/xml|application/rss\+xml|application/atom\+xml|text/plain)#i', $type)) {
            return false;
        }

        // A page with a form carries this visitor's CSRF token; sharing it would break the form for everyone else.
        if ($request->hasSession() && ($token = $request->session()->token()) && str_contains($content, $token)) {
            return false;
        }

        // Livewire components carry per-visitor snapshots and need their scripts injected on every request.
        if (str_contains($content, 'wire:snapshot')) {
            return false;
        }

        return (bool) $this->hooks->applyFilters('page_cache.cacheable', true, $request, $response);
    }

    /** Seconds an entry stored now may live: the configured TTL, cut short by the next scheduled item. */
    public function ttl(): int
    {
        $ttl = max(0, (int) config('press.page_cache.ttl', 3600));
        $next = $this->nextScheduledAt();

        if ($next !== null) {
            $ttl = min($ttl, max(1, (int) ceil(now()->diffInSeconds($next, false))));
        }

        return $ttl;
    }

    /** When the next published-but-future post or page goes live, if any. */
    public function nextScheduledAt(): ?CarbonInterface
    {
        $times = array_filter([
            Post::query()->where('status', PostStatus::Published)->where('published_at', '>', now())->min('published_at'),
            Page::query()->where('status', PostStatus::Published)->where('published_at', '>', now())->min('published_at'),
        ]);

        if ($times === []) {
            return null;
        }

        return collect($times)->map(fn ($time) => now()->parse($time))->sort()->first();
    }

    /** Make every cached page stale. */
    public function flush(): void
    {
        // A fresh token is safe on every cache store (no tags or prefix scans needed); old entries expire on their own.
        try {
            $this->store()->forever(self::VERSION_KEY, Str::random(12));
        } catch (Throwable $e) {
            // E.g. the cache table is not migrated yet. Saving content must not fail because of it.
            report($e);

            return;
        }

        $this->hooks->doAction('page_cache.flushed');
    }

    private function version(): string
    {
        $version = $this->store()->get(self::VERSION_KEY);

        if (! is_string($version) || $version === '') {
            $version = Str::random(12);
            $this->store()->forever(self::VERSION_KEY, $version);
        }

        return $version;
    }
}
