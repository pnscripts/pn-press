<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Press\Hooks;
use App\Press\I18n\Locales;
use App\Press\Themes\ThemeManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;

class PageController extends Controller
{
    public function __invoke(Request $request, Locales $locales)
    {
        // "/bg/about" is the Bulgarian page with the path "about".
        [$locale, $path] = $locales->split($request->path());

        $page = $path === '' ? null : Page::query()->inLocale($locale)->where('path', $path)->first();

        abort_if($page === null || (! $page->isPublic() && ! self::canPreview()), 404);

        // A language's home page lives at its root only ("/" or "/bg").
        if ($page->isHome()) {
            return redirect($page->url(), 301);
        }

        return $this->render($page);
    }

    public function render(Page $page, bool $alternates = true)
    {
        if ($alternates) {
            app(Locales::class)->setAlternates($page->alternateUrls());
        }

        app(Hooks::class)->doAction('page.viewing', $page);

        $template = 'pages.templates.'.$page->template;

        return response()->view(View::exists($template) ? $template : 'pages.show', [
            'page' => $page,
            'templates' => app(ThemeManager::class)->templates(),
        ]);
    }

    /** Signed-in admin users see drafts and scheduled pages. */
    public static function canPreview(): bool
    {
        $user = Auth::user();

        return $user !== null && method_exists($user, 'canAccessPanel') && $user->hasAnyRole(['admin', 'editor']);
    }
}
