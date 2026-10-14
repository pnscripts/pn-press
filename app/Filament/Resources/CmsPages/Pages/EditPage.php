<?php

namespace App\Filament\Resources\CmsPages\Pages;

use App\Filament\Resources\CmsPages\PageResource;
use App\Models\Page;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view')
                ->label('View page')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn (Page $record) => $record->url(), shouldOpenInNewTab: true),
            DeleteAction::make(),
        ];
    }
}
