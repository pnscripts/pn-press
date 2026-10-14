<?php

namespace App\Press\Blocks;

use Closure;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Component;

/**
 * A content block type: its admin fields and the Blade view that renders it.
 *
 * The view receives $data (the saved field values) and $block (this object).
 * Themes override a block's look by shipping a view with the same name.
 */
final class Block
{
    /**
     * @param  Closure(): array<int, Component|Field>  $fields
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly Closure $fields,
        public readonly string $view,
        public readonly string $icon = 'heroicon-o-square-3-stack-3d',
        public readonly ?string $source = null,
    ) {}

    /** @return array<int, mixed> */
    public function fields(): array
    {
        return ($this->fields)();
    }
}
