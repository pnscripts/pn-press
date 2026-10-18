<?php

namespace App\Http\Middleware;

use App\Press\Cache\PageCache;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Serves anonymous visitors from the page cache and stores what may be shared.
 * Adds X-Press-Cache: HIT or MISS so a deploy can be checked with curl -I.
 */
class CachePublicPages
{
    public function __construct(private readonly PageCache $cache) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->cache->accepts($request)) {
            return $next($request);
        }

        try {
            $entry = $this->cache->get($request);
        } catch (Throwable $e) {
            // A broken cache store must never take the site down.
            report($e);

            return $next($request);
        }

        if ($entry !== null) {
            return response($entry['content'], $entry['status'], $entry['headers'] + ['X-Press-Cache' => 'HIT']);
        }

        $response = $next($request);

        try {
            if ($this->cache->put($request, $response)) {
                $response->headers->set('X-Press-Cache', 'MISS');
            }
        } catch (Throwable $e) {
            report($e);
        }

        return $response;
    }
}
