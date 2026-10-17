<?php

namespace App\Http\Middleware;

use App\Press\I18n\Locales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the language from the first path segment ("/bg/…"); everything else is the default
 * language. No cookie or Accept-Language guessing: one URL always means one language, which
 * keeps the page cache and search engines right.
 */
class DetectLocale
{
    public function __construct(private readonly Locales $locales) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->locales->forget();
        $this->locales->activate($this->locales->split($request->path())[0]);

        return $next($request);
    }
}
