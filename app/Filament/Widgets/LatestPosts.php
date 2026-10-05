<?php

namespace App\Filament\Widgets;

use App\Enums\PostStatus;
use App\Filament\Resources\Posts\PostResource;
use App\Models\Post;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LatestPosts extends TableWidget
{
    // Rendered with the page, not lazily, so the top of the dashboard never flashes empty.
    protected static bool $isLazy = false;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Latest posts')
            ->description('Scheduled and draft posts appear first, then everything by publish date.')
            ->query(Post::query()->with('category')->orderByRaw('COALESCE(published_at, updated_at) DESC'))
            ->defaultPaginationPageOption(5)
            ->paginated([5])
            ->columns([
                TextColumn::make('title')
                    ->weight('medium')
                    ->limit(60),
                TextColumn::make('category.name')
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->badge()
                    ->state(fn (Post $record): string => self::statusLabel($record))
                    ->color(fn (string $state): string => match ($state) {
                        'Published' => 'success',
                        'Scheduled' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('published_at')
                    ->label('Publish date')
                    ->date()
                    ->placeholder('—'),
                TextColumn::make('updated_at')
                    ->label('Last edited')
                    ->since(),
            ])
            ->recordUrl(fn (Post $record): string => PostResource::getUrl('edit', ['record' => $record]));
    }

    public static function statusLabel(Post $post): string
    {
        if ($post->status === PostStatus::Draft) {
            return 'Draft';
        }

        return $post->published_at?->isFuture() ? 'Scheduled' : 'Published';
    }
}
