<?php

namespace App\Filament\Resources\CmsPages;

use App\Enums\PostStatus;
use App\Filament\Resources\CmsPages\Pages\CreatePage;
use App\Filament\Resources\CmsPages\Pages\EditPage;
use App\Filament\Resources\CmsPages\Pages\ListPages;
use App\Filament\Resources\CmsPages\RelationManagers\RevisionsRelationManager;
use App\Filament\Support\Translatable;
use App\Models\Page;
use App\Press\Blocks\BlockRegistry;
use App\Press\I18n\Locales;
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
use Filament\Schemas\Components\Utilities\Get;
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
                        Translatable::localeField(),
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
                            ->rule(fn (?Page $record, Get $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                                // Top-level slugs may not take a core route or a language prefix (/bg).
                                $reserved = array_merge(config('press.reserved_slugs'), app(Locales::class)->prefixed());
                                if (in_array(Str::lower((string) $value), $reserved, true) && blank($get('parent_id'))) {
                                    $fail('This address is used by PN Scripts Press itself.');
                                }
                            })
                            ->rule(fn (?Page $record, Get $get) => function (string $attribute, $value, \Closure $fail) use ($record, $get) {
                                // The full path (parent path + slug) must be free in this language.
                                $parent = filled($get('parent_id')) ? Page::query()->find($get('parent_id')) : null;
                                $path = ltrim(($parent ? $parent->path.'/' : '').Str::slug((string) $value), '/');
                                $taken = Page::query()->where('locale', Translatable::formLocale($get))->where('path', $path)
                                    ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))->exists();
                                if ($taken) {
                                    $fail('Another page in this language already has this address.');
                                }
                            }),
                        Select::make('parent_id')
                            ->label('Parent page')
                            ->relationship('parent', 'title', fn ($query, ?Page $record, Get $get) => $query->where('locale', Translatable::formLocale($get))->when($record, fn ($q) => $q->whereKeyNot($record->getKey())))
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
                TextColumn::make('title')->searchable()->sortable()->description(fn (Page $record) => app(Locales::class)->path($record->path, $record->locale)),
                Translatable::column(),
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
                Translatable::filter(),
            ])
            ->recordActions([
                Action::make('view')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Page $record) => $record->url(), shouldOpenInNewTab: true),
                Action::make('makeHome')
                    ->label('Set as home')
                    ->icon('heroicon-o-home')
                    ->visible(fn (Page $record) => ! $record->isHome() && Auth::user()?->can(Permissions::MANAGE_SETTINGS))
                    ->action(fn (Page $record) => app(Settings::class)->set(self::homeSettingKey($record->locale), $record->getKey())),
                Translatable::action(self::class),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }

    /** Settings key of the home page for a language: reading.home_page_id, reading.i18n.bg.home_page_id… */
    public static function homeSettingKey(?string $locale): string
    {
        $locales = app(Locales::class);

        return $locale === null || $locale === $locales->default() ? 'reading.home_page_id' : 'reading.i18n.'.$locale.'.home_page_id';
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
