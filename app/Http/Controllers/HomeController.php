<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Press\Settings;

class HomeController extends Controller
{
    /** The page chosen in Settings → Reading, or the blog when none is set (or it is not public). */
    public function __invoke(Settings $settings, PostController $posts, PageController $pages)
    {
        $homeId = $settings->get('reading.home_page_id');
        $page = $homeId ? Page::query()->find($homeId) : null;

        if ($page && ($page->isPublic() || PageController::canPreview())) {
            return $pages->render($page);
        }

        return $posts->index();
    }
}
