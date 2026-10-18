<?php

namespace App\Filament\Resources\Redirects;

use App\Filament\Resources\Redirects\Pages\ManageRedirects;
use App\Models\Redirect;
use App\Press\Permissions;
use BackedEnum;
use Closure;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Content → Redirects: old addresses that send visitors to a new one. Pages and posts add
 * automatic 301s when a published item's address changes.
 */
class RedirectResource extends Resource
{
    protected static ?string $model = Redirect::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-uturn-right';

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'from_path';

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->can(Permissions::MANAGE_PAGES);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('from_path')
                ->label('Old address')
                ->prefix(url('/').'/')
                ->required()
                ->maxLength(255)
                ->dehydrateStateUsing(fn (?string $state) => Redirect::normalize($state))
                ->unique(ignoreRecord: true)
                ->rule(fn () => function (string $attribute, $value, Closure $fail) {
                    if (Redirect::normalize($value) === '') {
                        $fail('The home page cannot be redirected.');
                    }
                })
                ->helperText('A path on this site, e.g. old-page or bg/staro. Redirects apply only to addresses that would otherwise be "not found".'),
            TextInput::make('to_url')
                ->label('New address')
                ->required()
                ->maxLength(500)
                ->rule(fn () => function (string $attribute, $value, Closure $fail) {
                    if (Redirect::safeTarget($value) === null) {
                        $fail('Use a path starting with / or a full http(s) address.');
                    }
                })
                ->helperText('/new-page, /bg/novo or https://example.com/page'),
            Select::make('status_code')
                ->label('Type')
                ->options(Redirect::CODES)
                ->default(301)
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('from_path')->label('Old address')->formatStateUsing(fn (string $state) => '/'.$state)->searchable()->sortable(),
                TextColumn::make('to_url')->label('New address')->searchable()->limit(60),
                TextColumn::make('status_code')->label('Type')->badge(),
                IconColumn::make('automatic')->label('Auto')->boolean()->tooltip('Created when a page or post address changed'),
                TextColumn::make('hits')->numeric()->sortable(),
                TextColumn::make('last_hit_at')->label('Last used')->since()->placeholder('Never')->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageRedirects::route('/')];
    }
}
