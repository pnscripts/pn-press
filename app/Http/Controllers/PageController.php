<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Press\Hooks;
use App\Press\Themes\ThemeManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;

class PageController extends Controller
{
    public function __invoke(Request $request)
    {
        $path = trim($request->path(), '/');

        $page = Page::query()->where('path', $path)->first();

        abort_if($page === null || (! $page->isPublic() && ! self::canPreview()), 404);

        // The home page lives at "/" only.
        if ($page->isHome()) {
            return redirect('/', 301);
        }

        return $this->render($page);
    }

    public function render(Page $page)
    {
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
