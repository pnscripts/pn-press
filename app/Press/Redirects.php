<?php

namespace App\Press;

use App\Models\Redirect;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/** Turns a 404 into a redirect when Content → Redirects has the address. */
class Redirects
{
    public function respond(Request $request): ?Response
    {
        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return null;
        }

        try {
            $redirect = Redirect::query()->where('from_path', Redirect::normalize($request->path()))->first();
        } catch (Throwable) {
            return null; // Not migrated yet.
        }

        $target = $redirect ? Redirect::safeTarget($redirect->to_url) : null;

        if ($redirect === null || $target === null) {
            return null;
        }

        $redirect->recordHit();

        if (str_starts_with($target, '/') && ($query = $request->getQueryString())) {
            $target .= (str_contains($target, '?') ? '&' : '?').$query;
        }

        return redirect(str_starts_with($target, '/') ? url($target) : $target, $redirect->status_code)
            ->header('X-Press-Redirect', (string) $redirect->getKey());
    }
}
