<?php

namespace App\Press;

use App\Models\Menu;
use App\Models\MenuItem;
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
     * @return list<array{label: string, url: string, new_tab: bool, active: bool, children: list<array<string, mixed>>}>
     */
    public function tree(string $location): array
    {
        if (array_key_exists($location, $this->cache)) {
            return $this->cache[$location];
        }

        try {
            $menu = Menu::query()
                ->where('location', $location)
                ->with(['items.page', 'items.post', 'items.category', 'items.children.page', 'items.children.post', 'items.children.category'])
                ->first();
        } catch (Throwable) {
            return [];
        }

        $items = $menu ? $this->map($menu->items) : [];

        return $this->cache[$location] = $this->hooks->applyFilters('menu.items', $items, $location);
    }

    /**
     * @param  iterable<MenuItem>  $items
     * @return list<array<string, mixed>>
     */
    private function map(iterable $items): array
    {
        $current = rtrim(request()->url(), '/');
        $out = [];

        foreach ($items as $item) {
            $url = $item->resolveUrl();

            if ($url === null) {
                continue;
            }

            $absolute = str_starts_with($url, '/') ? url($url) : $url;

            $out[] = [
                'label' => $item->label,
                'url' => $url,
                'new_tab' => $item->open_in_new_tab,
                'active' => rtrim($absolute, '/') === $current,
                'children' => $item->relationLoaded('children') ? $this->map($item->children) : [],
            ];
        }

        return $out;
    }
}
