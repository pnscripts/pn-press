<?php

namespace App\Filament\Widgets;

use App\Enums\PostStatus;
use App\Filament\Resources\Posts\PostResource;
use App\Models\Post;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class DraftsAwaitingReview extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 1];

    public function table(Table $table): Table
    {
        return $table
            ->heading('Drafts waiting to be published')
            ->description('Oldest first, so nothing sits forgotten.')
            ->query(Post::query()->with('author')->where('status', PostStatus::Draft)->oldest('updated_at'))
            ->defaultPaginationPageOption(5)
            ->paginated([5])
            ->emptyStateHeading('No drafts')
            ->emptyStateDescription('Every post is published or scheduled.')
            ->columns([
                TextColumn::make('title')
                    ->weight('medium')
                    ->limit(45),
                TextColumn::make('author.name')
                    ->label('Author')
                    ->placeholder('—'),
                TextColumn::make('updated_at')
                    ->label('Last edited')
                    ->since(),
            ])
            ->recordUrl(fn (Post $record): string => PostResource::getUrl('edit', ['record' => $record]));
    }
}
