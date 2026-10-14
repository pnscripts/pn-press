<?php

namespace App\Filament\Resources\CmsPages;

use App\Enums\PostStatus;
use App\Filament\Resources\CmsPages\Pages\CreatePage;
use App\Filament\Resources\CmsPages\Pages\EditPage;
use App\Filament\Resources\CmsPages\Pages\ListPages;
use App\Filament\Resources\CmsPages\RelationManagers\RevisionsRelationManager;
use App\Models\Page;
use App\Press\Blocks\BlockRegistry;
use App\Press\Permissions;
use App\Press\Settings;
use App\Press\Themes\ThemeManager;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use UnitEnum;

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-duplicate';

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $slug = 'pages';

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->can(Permissions::MANAGE_PAGES);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Page')
                ->columnSpanFull()
                ->persistTabInQueryString()
                ->tabs([
                    Tabs\Tab::make('Content')->icon('heroicon-o-pencil-square')->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, Set $set, ?string $state): void {
                                if ($operation === 'create') {
                                    $set('slug', Str::slug((string) $state));
                                }
                            }),
                        Textarea::make('excerpt')->rows(2)->maxLength(500)->helperText('Shown under the title and used as the default meta description.'),
                        Builder::make('blocks')
                            ->blocks(fn () => app(BlockRegistry::class)->builderBlocks())
                            ->collapsible()
                            ->cloneable()
                            ->blockNumbers(false)
                            ->addActionLabel('Add block'),
                    ]),
                    Tabs\Tab::make('Publishing')->icon('heroicon-o-calendar')->columns(2)->schema([
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(120)
                            ->alphaDash()
                            ->rule(fn (?Page $record) => function (string $attribute, $value, \Closure $fail) use ($record) {
                                if (in_array(Str::lower((string) $value), config('press.reserved_slugs'), true) && ! ($record?->parent_id)) {
                                    $fail('This address is used by PN Scripts Press itself.');
                                }
                            }),
                        Select::make('parent_id')
                            ->label('Parent page')
                            ->relationship('parent', 'title', fn ($query, ?Page $record) => $record ? $query->whereKeyNot($record->getKey()) : $query)
                            ->searchable()
                            ->preload(),
                        Select::make('status')
                            ->options([PostStatus::Draft->value => 'Draft', PostStatus::Published->value => 'Published'])
                            ->required()
                            ->default(PostStatus::Draft->value),
                        DateTimePicker::make('published_at')->helperText('A future date schedules the page.'),
                        Select::make('template')
                            ->options(fn () => app(ThemeManager::class)->templates())
                            ->default('default')
                            ->required(),
                        TextInput::make('sort_order')->numeric()->default(0),
                    ]),
                    Tabs\Tab::make('SEO')->icon('heroicon-o-magnifying-glass')->schema([
                        TextInput::make('meta_title')->maxLength(70)->helperText('Defaults to the page title.'),
                        Textarea::make('meta_description')->rows(2)->maxLength(160)->helperText('Defaults to the excerpt. About 150 characters.'),
                        TextInput::make('og_image')->label('Social image URL')->maxLength(255),
                        Toggle::make('noindex')->label('Hide from search engines'),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->sortable()->description(fn (Page $record) => '/'.$record->path),
                TextColumn::make('status')
                    ->badge()
                    ->state(fn (Page $record) => $record->isPublic() ? 'Published' : ($record->status === PostStatus::Published ? 'Scheduled' : 'Draft'))
                    ->color(fn (string $state) => match ($state) {
                        'Published' => 'success',
                        'Scheduled' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('home')
                    ->label('')
                    ->state(fn (Page $record) => $record->isHome() ? 'Home page' : null)
                    ->badge()
                    ->color('primary'),
                TextColumn::make('template')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->since()->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options([PostStatus::Draft->value => 'Draft', PostStatus::Published->value => 'Published']),
            ])
            ->recordActions([
                Action::make('view')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Page $record) => $record->url(), shouldOpenInNewTab: true),
                Action::make('makeHome')
                    ->label('Set as home')
                    ->icon('heroicon-o-home')
                    ->visible(fn (Page $record) => ! $record->isHome() && Auth::user()?->can(Permissions::MANAGE_SETTINGS))
                    ->action(fn (Page $record) => app(Settings::class)->set('reading.home_page_id', $record->getKey())),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }

    public static function getRelations(): array
    {
        return [RevisionsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPages::route('/'),
            'create' => CreatePage::route('/create'),
            'edit' => EditPage::route('/{record}/edit'),
        ];
    }
}
