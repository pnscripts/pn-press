<?php

namespace App\Filament\Widgets;

use App\Enums\PostStatus;
use App\Models\Category;
use App\Models\Post;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PostStatsOverview extends StatsOverviewWidget
{
    // Rendered with the page, not lazily, so the top of the dashboard never flashes empty.
    protected static bool $isLazy = false;

    protected static ?int $sort = 1;

    protected ?string $heading = 'Content at a glance';

    protected function getStats(): array
    {
        $published = Post::query()->published()->count();
        $drafts = Post::query()->where('status', PostStatus::Draft)->count();
        $scheduled = Post::query()
            ->where('status', PostStatus::Published)
            ->where('published_at', '>', now())
            ->count();
        $categories = Category::query()->count();
        $lastThirtyDays = Post::query()->published()->where('published_at', '>=', now()->subDays(30))->count();

        return [
            Stat::make('Published posts', $published)
                ->description($lastThirtyDays.' in the last 30 days')
                ->descriptionIcon('heroicon-m-globe-alt')
                ->chart($this->publishedPerWeek())
                ->color('success'),
            Stat::make('Drafts', $drafts)
                ->description('Not visible to readers')
                ->descriptionIcon('heroicon-m-pencil-square')
                ->color($drafts > 0 ? 'warning' : 'gray'),
            Stat::make('Scheduled', $scheduled)
                ->description('Go live on their publish date')
                ->descriptionIcon('heroicon-m-clock')
                ->color('info'),
            Stat::make('Categories', $categories)
                ->description('Used to group posts')
                ->descriptionIcon('heroicon-m-tag')
                ->color('gray'),
        ];
    }

    /**
     * Published posts per week for the last eight weeks, oldest first.
     *
     * @return list<int>
     */
    private function publishedPerWeek(): array
    {
        $start = now()->startOfWeek()->subWeeks(7);

        $dates = Post::query()
            ->published()
            ->where('published_at', '>=', $start)
            ->pluck('published_at');

        $weeks = array_fill(0, 8, 0);

        foreach ($dates as $date) {
            $index = (int) floor($start->diffInDays($date) / 7);
            $weeks[min(7, max(0, $index))]++;
        }

        return $weeks;
    }
}
