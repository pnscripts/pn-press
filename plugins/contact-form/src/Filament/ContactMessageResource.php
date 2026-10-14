<?php

namespace PnPress\ContactForm\Filament;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use PnPress\ContactForm\ContactMessage;
use PnPress\ContactForm\Filament\Pages\ListContactMessages;
use PnPress\ContactForm\Filament\Pages\ViewContactMessage;
use PnPress\ContactForm\MessagesCsv;
use Throwable;
use UnitEnum;

/**
 * Contact Form inbox: list, read, mark handled, delete and export messages.
 */
class ContactMessageResource extends Resource
{
    protected static ?string $model = ContactMessage::class;

    protected static ?string $slug = 'contact-messages';

    protected static ?string $modelLabel = 'message';

    protected static ?string $navigationLabel = 'Messages';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-inbox';

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->can(ContactMessage::PERMISSION);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        try {
            $count = ContactMessage::query()->unhandled()->count();
        } catch (Throwable) {
            return null;
        }

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Not handled yet';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->columnSpanFull()->schema([
                TextEntry::make('name'),
                TextEntry::make('email')->copyable()->url(fn (ContactMessage $record) => 'mailto:'.$record->email),
                TextEntry::make('created_at')->label('Received')->dateTime(),
                TextEntry::make('page_url')->label('Sent from')->placeholder('—')
                    ->url(fn (ContactMessage $record) => self::safeUrl($record->page_url), shouldOpenInNewTab: true),
                TextEntry::make('status')
                    ->state(fn (ContactMessage $record) => $record->isHandled() ? 'Handled '.$record->handled_at->diffForHumans() : 'Not handled')
                    ->badge()
                    ->color(fn (ContactMessage $record) => $record->isHandled() ? 'success' : 'warning'),
                TextEntry::make('message')->columnSpanFull()->prose()
                    ->formatStateUsing(fn (string $state) => nl2br(e($state)))->html(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                IconColumn::make('handled_at')
                    ->label('')
                    ->state(fn (ContactMessage $record) => $record->isHandled())
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-envelope')
                    ->falseColor('warning')
                    ->tooltip(fn (ContactMessage $record) => $record->isHandled() ? 'Handled' : 'Not handled'),
                TextColumn::make('name')
                    ->searchable()
                    ->description(fn (ContactMessage $record) => $record->email)
                    ->weight(fn (ContactMessage $record) => $record->read_at ? null : 'bold'),
                TextColumn::make('message')
                    ->searchable()
                    ->formatStateUsing(fn (string $state) => Str::limit(preg_replace('/\s+/', ' ', $state), 90))
                    ->wrap(),
                TextColumn::make('email')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label('Received')->since()->dateTimeTooltip()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('handled')
                    ->label('Handled')
                    ->placeholder('All messages')
                    ->trueLabel('Handled')
                    ->falseLabel('Not handled')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('handled_at'),
                        false: fn (Builder $query) => $query->whereNull('handled_at'),
                    ),
            ])
            ->recordActions([
                ViewAction::make()->label('Read'),
                self::handledAction(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('markHandled')
                        ->label('Mark handled')
                        ->icon('heroicon-o-check')
                        ->action(fn (Collection $records) => $records->each->markHandled())
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('exportSelected')
                        ->label('Export selected (CSV)')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(fn (Collection $records) => MessagesCsv::download($records->sortByDesc('created_at'))),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-inbox')
            ->emptyStateHeading('No messages yet')
            ->emptyStateDescription('Messages sent through a Contact form block on your pages land here.');
    }

    /** Mark handled / not handled, for the table and the message screen. */
    public static function handledAction(): Action
    {
        return Action::make('toggleHandled')
            ->label(fn (ContactMessage $record) => $record->isHandled() ? 'Mark not handled' : 'Mark handled')
            ->icon(fn (ContactMessage $record) => $record->isHandled() ? 'heroicon-o-arrow-uturn-left' : 'heroicon-o-check')
            ->color(fn (ContactMessage $record) => $record->isHandled() ? 'gray' : 'success')
            ->action(fn (ContactMessage $record) => $record->markHandled(! $record->isHandled()));
    }

    /** Only http(s) links are clickable; anything else is shown as text. */
    public static function safeUrl(?string $url): ?string
    {
        return $url !== null && preg_match('#^https?://#i', $url) ? $url : null;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContactMessages::route('/'),
            'view' => ViewContactMessage::route('/{record}'),
        ];
    }
}
