<?php

namespace App\Http\Controllers;

use App\Press\Themes\ThemeManager;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves files from a theme's assets/ folder (falling back to its parent theme's) and its screenshot.
 * Only static file types; never PHP.
 */
class ThemeAssetController extends Controller
{
    private const TYPES = [
        'css' => 'text/css', 'js' => 'text/javascript', 'svg' => 'image/svg+xml', 'png' => 'image/png',
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp',
        'ico' => 'image/x-icon', 'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf', 'otf' => 'font/otf',
        'map' => 'application/json',
    ];

    public function __invoke(ThemeManager $themes, string $theme, string $path): BinaryFileResponse
    {
        $manifest = $themes->find($theme);
        abort_if($manifest === null, 404);

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        abort_unless(isset(self::TYPES[$extension]), 404);
        abort_if(str_contains($path, '..') || str_contains($path, '\\') || str_contains($path, "\0"), 404);

        $isScreenshot = $manifest->screenshot !== null && $path === $manifest->screenshot;
        abort_unless($isScreenshot || str_starts_with($path, 'assets/'), 404);

        // The theme's own file, else its parent's (child themes ship only what they change).
        $file = $themes->assetFile($manifest, $path);
        abort_if($file === null, 404);

        return response()->file($file, [
            'Content-Type' => self::TYPES[$extension],
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
            // An SVG opened directly must not run scripts on this origin.
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; img-src data:; font-src data:",
        ]);
    }
}
