<?php

namespace App\Press;

use Illuminate\Support\HtmlString;

/**
 * WordPress-style actions and filters.
 *
 * Actions run callbacks at a named point ("something happened"); filters pass a
 * value through every callback and return the result. Lower priority runs first,
 * equal priorities run in the order they were added.
 *
 * Render hooks are actions whose callbacks return HTML; Blade's @hook('name')
 * prints the joined output. Callbacks must escape what they print.
 */
class Hooks
{
    /** @var array<string, array<int, list<callable>>> */
    private array $actions = [];

    /** @var array<string, array<int, list<callable>>> */
    private array $filters = [];

    /** @var array<string, int> */
    private array $fired = [];

    public function addAction(string $hook, callable $callback, int $priority = 10): void
    {
        $this->actions[$hook][$priority][] = $callback;
    }

    public function addFilter(string $hook, callable $callback, int $priority = 10): void
    {
        $this->filters[$hook][$priority][] = $callback;
    }

    public function removeAction(string $hook, ?callable $callback = null): void
    {
        $this->remove($this->actions, $hook, $callback);
    }

    public function removeFilter(string $hook, ?callable $callback = null): void
    {
        $this->remove($this->filters, $hook, $callback);
    }

    public function hasAction(string $hook): bool
    {
        return ! empty($this->actions[$hook]);
    }

    public function hasFilter(string $hook): bool
    {
        return ! empty($this->filters[$hook]);
    }

    /**
     * Run every callback for the action and return what they returned, in order.
     *
     * @return list<mixed>
     */
    public function doAction(string $hook, mixed ...$args): array
    {
        $this->fired[$hook] = ($this->fired[$hook] ?? 0) + 1;

        $results = [];

        foreach ($this->sorted($this->actions, $hook) as $callback) {
            $results[] = $callback(...$args);
        }

        return $results;
    }

    public function applyFilters(string $hook, mixed $value, mixed ...$args): mixed
    {
        foreach ($this->sorted($this->filters, $hook) as $callback) {
            $value = $callback($value, ...$args);
        }

        return $value;
    }

    /**
     * Join the string output of a render hook. Non-string results are ignored.
     */
    public function render(string $hook, mixed ...$args): HtmlString
    {
        $html = collect($this->doAction($hook, ...$args))
            ->filter(fn ($result) => is_string($result) || $result instanceof \Stringable)
            ->map(fn ($result) => (string) $result)
            ->implode("\n");

        return new HtmlString($html);
    }

    public function didAction(string $hook): int
    {
        return $this->fired[$hook] ?? 0;
    }

    /**
     * @param  array<string, array<int, list<callable>>>  $store
     * @return list<callable>
     */
    private function sorted(array $store, string $hook): array
    {
        if (empty($store[$hook])) {
            return [];
        }

        $byPriority = $store[$hook];
        ksort($byPriority);

        return array_merge(...array_values($byPriority));
    }

    /**
     * @param  array<string, array<int, list<callable>>>  $store
     */
    private function remove(array &$store, string $hook, ?callable $callback): void
    {
        if ($callback === null) {
            unset($store[$hook]);

            return;
        }

        foreach ($store[$hook] ?? [] as $priority => $callbacks) {
            $store[$hook][$priority] = array_values(array_filter($callbacks, fn ($existing) => $existing !== $callback));
        }
    }
}
