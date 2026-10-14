<?php

namespace PnPress\ContactForm\Filament\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use PnPress\ContactForm\ContactMessage;
use PnPress\ContactForm\Filament\ContactMessageResource;
use PnPress\ContactForm\MessagesCsv;

class ListContactMessages extends ListRecords
{
    protected static string $resource = ContactMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->tooltip('Every message that matches the current filters and search')
                ->action(fn () => MessagesCsv::download(
                    ($this->getFilteredSortedTableQuery() ?? ContactMessage::query()->latest())->lazy(200)
                )),
        ];
    }
}
