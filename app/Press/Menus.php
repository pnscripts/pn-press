<?php

namespace App\Press;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Press\I18n\Locales;
use Throwable;

/**
 * Menus by theme location ("primary", "footer"), as plain arrays for Blade.
 *
 * Items whose target is not public (draft page, unpublished post) are left out.
 */
class Menus
{
    /** @var array<string, list<array<string, mixed>>> */
    private array $cache = [];

    public function __construct(private readonly Hooks $hooks) {}

    /**
     * Nested up to maxDepth() levels; an item is "active" when it or one of its children is the current page.
     *
     * @return list<array{label: string, url: string, new_tab: bool, active: bool, current: bool, children: list<array<string, mixed>>}>
     */
    public function tree(string $location): array
    {
        $locale = app(Locales::class)->current();
        $key = $location.'|'.$locale;

        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        try {
            // The menu made for this language, else the one for every language (locale null).
            $menu = Menu::query()
                ->where('location', $location)
                ->where(fn ($query) => $query->where('locale', $locale)->orWhereNull('locale'))
                ->orderByRaw('case when locale is null then 1 else 0 end')
                ->with(self::eagerLoads())
                ->first();
        } catch (Throwable) {
            return [];
        }

        $items = $menu ? $this->map($menu->items, 1) : [];

        return $this->cache[$key] = $this->hooks->applyFilters('menu.items', $items, $location);
    }

    /** Levels of nesting the site shows (config press.menus.max_depth, 1–6). */
    public static function maxDepth(): int
    {
        return max(1, min(6, (int) config('press.menus.max_depth', 3)));
    }

    /** @return list<string> items, items.children, … down to the deepest level, with their link targets */
    private static function eagerLoads(): array
    {
        $loads = [];
        $path = 'items';

        for ($level = 1; $level <= self::maxDepth(); $level++) {
            array_push($loads, $path.'.page', $path.'.post', $path.'.category');
            $path .= '.children';
        }

        return $loads;
    }

    /**
     * @param  iterable<MenuItem>  $items
     * @return list<array<string, mixed>>
     */
    private function map(iterable $items, int $level): array
    {
        $current = rtrim(request()->url(), '/');
        $out = [];

        foreach ($items as $item) {
            $url = $item->resolveUrl();

            if ($url === null) {
                continue;
            }

            $absolute = str_starts_with($url, '/') ? url($url) : $url;
            $children = $level < self::maxDepth() && $item->relationLoaded('children') ? $this->map($item->children, $level + 1) : [];
            $isCurrent = rtrim($absolute, '/') === $current;

            $out[] = [
                'label' => $item->label,
                'url' => $url,
                'new_tab' => $item->open_in_new_tab,
                'current' => $isCurrent,
                'active' => $isCurrent || in_array(true, array_column($children, 'active'), true),
                'children' => $children,
            ];
        }

        return $out;
    }
}
