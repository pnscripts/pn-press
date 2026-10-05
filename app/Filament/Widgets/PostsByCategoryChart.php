<?php

namespace App\Filament\Widgets;

use App\Models\Category;
use Filament\Widgets\ChartWidget;

class PostsByCategoryChart extends ChartWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 1];

    protected ?string $heading = 'Published posts by category';

    protected ?string $maxHeight = '260px';

    /** Brand-aligned palette (orange, navy, blues) that also reads on the dark theme. */
    private const COLORS = ['#f67a3c', '#4f90ff', '#1e3a8a', '#93c5fd', '#c2410c', '#64748b'];

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $categories = Category::query()
            ->withCount(['posts' => fn ($query) => $query->published()])
            ->orderByDesc('posts_count')
            ->orderBy('name')
            ->get()
            ->filter(fn (Category $category): bool => $category->posts_count > 0)
            ->values();

        return [
            'datasets' => [
                [
                    'label' => 'Published posts',
                    'data' => $categories->pluck('posts_count')->all(),
                    'backgroundColor' => $categories->keys()->map(fn (int $i): string => self::COLORS[$i % count(self::COLORS)])->all(),
                    'borderWidth' => 0,
                ],
            ],
            'labels' => $categories->pluck('name')->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['position' => 'right'],
            ],
            'scales' => [
                'x' => ['display' => false],
                'y' => ['display' => false],
            ],
            'cutout' => '62%',
        ];
    }
}
