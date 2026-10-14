<?php

namespace App\Http\Middleware;

use App\Press\Permissions;
use App\Press\Themes\ThemeManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Puts the active theme's views in front of the defaults for public pages.
 * Users who can manage themes may preview another one with ?preview_theme=slug.
 */
class ApplyTheme
{
    public function __construct(private readonly ThemeManager $themes) {}

    public function handle(Request $request, Closure $next): Response
    {
        $preview = $request->query('preview_theme');

        if (is_string($preview) && $this->themes->find($preview) && $request->user()?->can(Permissions::MANAGE_THEMES)) {
            $this->themes->apply($preview);
        } else {
            $this->themes->apply();
        }

        return $next($request);
    }
}
