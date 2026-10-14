<?php

namespace App\Filament\Resources\CmsPages\RelationManagers;

use App\Filament\Resources\CmsPages\PageResource;
use App\Models\Page;
use App\Models\PageRevision;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RevisionsRelationManager extends RelationManager
{
    protected static string $relationship = 'revisions';

    protected static ?string $title = 'Revisions';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->description('Every save keeps the previous version. Restoring also keeps the current one, so it can be undone.')
            ->columns([
                TextColumn::make('created_at')->label('Saved')->dateTime()->sortable(),
                TextColumn::make('title'),
                TextColumn::make('data.status')->label('Status')->badge(),
                TextColumn::make('author.name')->label('By')->placeholder('—'),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                Action::make('restore')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->requiresConfirmation()
                    ->modalDescription('The page goes back to this version. The current version is kept as a new revision.')
                    ->action(function (PageRevision $record): void {
                        /** @var Page $page */
                        $page = $this->getOwnerRecord();
                        $page->restoreRevision($record);

                        Notification::make()->title('Revision restored')->success()->send();
                        $this->redirect(PageResource::getUrl('edit', ['record' => $page]));
                    }),
            ]);
    }
}
