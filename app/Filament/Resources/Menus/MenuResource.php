<?php

namespace App\Filament\Resources\Menus;

use App\Filament\Resources\Menus\Pages\CreateMenu;
use App\Filament\Resources\Menus\Pages\EditMenu;
use App\Filament\Resources\Menus\Pages\ListMenus;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Press\Permissions;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class MenuResource extends Resource
{
    protected static ?string $model = Menu::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bars-3';

    protected static string|UnitEnum|null $navigationGroup = 'Appearance';

    protected static ?int $navigationSort = 2;

    /** Menu locations every theme can print. Themes may add more through the menu.locations filter. */
    public static function locations(): array
    {
        return apply_filters('menu.locations', ['primary' => 'Main navigation', 'footer' => 'Footer']);
    }

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->can(Permissions::MANAGE_MENUS);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->columnSpanFull()->schema([
                TextInput::make('name')->required()->maxLength(80),
                Select::make('location')
                    ->options(fn () => self::locations())
                    ->unique(ignoreRecord: true)
                    ->helperText('Where the theme shows this menu.'),
            ]),
            Repeater::make('items')
                ->relationship()
                ->orderColumn('sort_order')
                ->reorderableWithDragAndDrop()
                ->collapsible()
                ->itemLabel(fn (array $state) => $state['label'] ?? null)
                ->columnSpanFull()
                ->schema([
                    ...self::itemFields(),
                    Repeater::make('children')
                        ->label('Dropdown items')
                        ->relationship()
                        ->orderColumn('sort_order')
                        ->collapsible()
                        ->collapsed()
                        ->itemLabel(fn (array $state) => $state['label'] ?? null)
                        ->schema(self::itemFields())
                        ->defaultItems(0)
                        ->columnSpanFull(),
                ])
                ->defaultItems(0)
                ->addActionLabel('Add menu item'),
        ]);
    }

    /** @return array<int, mixed> */
    private static function itemFields(): array
    {
        return [
            Grid::make(3)->schema([
                TextInput::make('label')->required()->maxLength(80),
                Select::make('type')->options(MenuItem::TYPES)->default('page')->required()->live(),
                Toggle::make('open_in_new_tab')->label('New tab')->inline(false),
            ]),
            Select::make('page_id')->relationship('page', 'title')->searchable()->preload()
                ->visible(fn (Get $get) => $get('type') === 'page')->required(fn (Get $get) => $get('type') === 'page'),
            Select::make('post_id')->relationship('post', 'title')->searchable()
                ->visible(fn (Get $get) => $get('type') === 'post')->required(fn (Get $get) => $get('type') === 'post'),
            Select::make('category_id')->relationship('category', 'name')->preload()
                ->visible(fn (Get $get) => $get('type') === 'category')->required(fn (Get $get) => $get('type') === 'category'),
            TextInput::make('url')->label('URL')->maxLength(255)
                ->visible(fn (Get $get) => $get('type') === 'url')->required(fn (Get $get) => $get('type') === 'url')
                ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                    if (MenuItem::safeUrl($value) === null) {
                        $fail('Use /path, https://…, mailto: or tel:.');
                    }
                }),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('location')->badge()->formatStateUsing(fn (?string $state) => self::locations()[$state] ?? $state)->placeholder('Not shown'),
                TextColumn::make('items_count')->counts('items')->label('Items'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMenus::route('/'),
            'create' => CreateMenu::route('/create'),
            'edit' => EditMenu::route('/{record}/edit'),
        ];
    }
}
