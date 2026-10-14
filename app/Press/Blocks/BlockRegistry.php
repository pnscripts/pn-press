<?php

namespace App\Press\Blocks;

use App\Press\Hooks;
use Filament\Forms\Components\Builder;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;

/**
 * Every block type a page can use. Core blocks come from CoreBlocks; plugins add their own:
 *
 *     app(BlockRegistry::class)->register(new Block('contact_form', 'Contact form', fn () => [...], 'contact-form::block'));
 */
class BlockRegistry
{
    /** @var array<string, Block> */
    private array $blocks = [];

    public function __construct(private readonly Hooks $hooks) {}

    public function register(Block $block): void
    {
        if (! preg_match('/^[a-z][a-z0-9_]*$/', $block->key)) {
            throw new InvalidArgumentException("Block key [{$block->key}] must be snake_case.");
        }

        $this->blocks[$block->key] = $block;
    }

    public function unregister(string $key): void
    {
        unset($this->blocks[$key]);
    }

    public function has(string $key): bool
    {
        return isset($this->blocks[$key]);
    }

    public function get(string $key): ?Block
    {
        return $this->blocks[$key] ?? null;
    }

    /** @return array<string, Block> */
    public function all(): array
    {
        return $this->blocks;
    }

    /**
     * Filament Builder blocks for the page editor.
     *
     * @return list<Builder\Block>
     */
    public function builderBlocks(): array
    {
        return collect($this->blocks)
            ->map(fn (Block $block) => Builder\Block::make($block->key)
                ->label($block->label)
                ->icon($block->icon)
                ->schema($block->fields()))
            ->values()
            ->all();
    }

    /**
     * Render saved blocks. Unknown types (for example from a deactivated plugin) are skipped.
     *
     * @param  array<int, array{type?: string, data?: array<string, mixed>}>|null  $blocks
     */
    public function render(?array $blocks, mixed $context = null): HtmlString
    {
        $blocks = $this->hooks->applyFilters('blocks.before_render', $blocks ?? [], $context);

        $html = '';

        foreach ($blocks as $index => $item) {
            $block = $this->get((string) ($item['type'] ?? ''));

            if ($block === null || ! View::exists($block->view)) {
                continue;
            }

            $html .= view($block->view, [
                'data' => $item['data'] ?? [],
                'block' => $block,
                'index' => $index,
                'context' => $context,
            ])->render();
        }

        return new HtmlString($this->hooks->applyFilters('blocks.html', $html, $context));
    }
}
