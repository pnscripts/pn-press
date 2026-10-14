<?php

namespace App\Http\Middleware;

use App\Press\Plugins\PluginManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards plugin routes: a cached route of a plugin that has since been deactivated answers 404.
 */
class EnsurePluginIsActive
{
    public function __construct(private readonly PluginManager $plugins) {}

    public function handle(Request $request, Closure $next, string $slug): Response
    {
        abort_unless($this->plugins->isActive($slug), 404);

        return $next($request);
    }
}
