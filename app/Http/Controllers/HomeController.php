<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Press\I18n\Locales;
use App\Press\Settings;

class HomeController extends Controller
{
    /**
     * The page chosen in Settings → Reading for this language, or the blog when none is set
     * (or it is not public, or it belongs to another language).
     */
    public function __invoke(Settings $settings, Locales $locales, PostController $posts, PageController $pages)
    {
        $locale = $locales->current();
        $homeId = $settings->localized('reading.home_page_id', $locale);
        $page = $homeId ? Page::query()->inLocale($locale)->find($homeId) : null;

        // Every language's front page is the same place on the site.
        $locales->setAlternates(collect($locales->codes())->mapWithKeys(fn ($code) => [$code => $locales->url('/', $code)])->all());

        if ($page && ($page->isPublic() || PageController::canPreview())) {
            return $pages->render($page, alternates: false);
        }

        return $posts->index($locales);
    }
}
