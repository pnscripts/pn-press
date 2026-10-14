<?php

namespace PnPress\ContactForm\Filament\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;
use PnPress\ContactForm\ContactMessage;
use PnPress\ContactForm\Filament\ContactMessageResource;

class ViewContactMessage extends ViewRecord
{
    protected static string $resource = ContactMessageResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        /** @var ContactMessage $message */
        $message = $this->getRecord();
        $message->markRead();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reply')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->url(fn (ContactMessage $record) => 'mailto:'.rawurlencode($record->email).'?subject='.rawurlencode('Re: your message')),
            ContactMessageResource::handledAction(),
            DeleteAction::make(),
        ];
    }
}
