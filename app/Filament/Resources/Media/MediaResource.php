<?php

namespace App\Filament\Resources\Media;

use App\Filament\Resources\Media\Pages\CreateMedia;
use App\Filament\Resources\Media\Pages\EditMedia;
use App\Filament\Resources\Media\Pages\ListMedia;
use App\Models\Media;
use App\Press\Permissions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class MediaResource extends Resource
{
    protected static ?string $model = Media::class;

    protected static ?string $modelLabel = 'media file';

    protected static ?string $pluralModelLabel = 'media';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->can(Permissions::MANAGE_MEDIA);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            FileUpload::make('path')
                ->label('File')
                ->disk(config('press.media.disk'))
                ->directory(config('press.media.directory'))
                ->acceptedFileTypes(config('press.media.accepted'))
                ->maxSize(config('press.media.max_kb'))
                ->required()
                ->visibleOn('create')
                ->columnSpanFull(),
            TextInput::make('alt')->label('Alt text')->maxLength(255)->helperText('Describe the image for people who cannot see it.'),
            TextInput::make('caption')->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('path')->label('')->disk(config('press.media.disk'))->square()
                    ->visible(true),
                TextColumn::make('name')->searchable()->description(fn (Media $record) => $record->alt),
                TextColumn::make('mime_type')->label('Type')->badge()->color('gray'),
                TextColumn::make('size')->formatStateUsing(fn (Media $record) => $record->humanSize()),
                TextColumn::make('dimensions')->state(fn (Media $record) => $record->width ? $record->width.'×'.$record->height : null)->placeholder('—'),
                TextColumn::make('created_at')->since()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('open')->icon('heroicon-o-arrow-top-right-on-square')->url(fn (Media $record) => $record->url(), shouldOpenInNewTab: true),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMedia::route('/'),
            'create' => CreateMedia::route('/create'),
            'edit' => EditMedia::route('/{record}/edit'),
        ];
    }
}
