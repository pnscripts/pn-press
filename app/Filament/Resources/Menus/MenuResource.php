<?php

namespace App\Filament\Resources\Menus;

use App\Filament\Resources\Menus\Pages\CreateMenu;
use App\Filament\Resources\Menus\Pages\EditMenu;
use App\Filament\Resources\Menus\Pages\ListMenus;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Press\I18n\Locales;
use App\Press\Menus;
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
use Illuminate\Validation\Rules\Unique;
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
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => filled($get('locale'))
                        ? $rule->where('locale', $get('locale'))
                        : $rule->whereNull('locale'))
                    ->helperText('Where the theme shows this menu.'),
                Select::make('locale')
                    ->label('Language')
                    ->options(fn () => app(Locales::class)->options())
                    ->placeholder('Every language')
                    ->in(fn () => app(Locales::class)->codes())
                    ->live()
                    ->visible(fn () => app(Locales::class)->enabled())
                    ->helperText('A menu for one language replaces the “every language” menu of the same location there.'),
            ]),
            self::itemsRepeater('items', 1)
                ->label('Items')
                ->columnSpanFull()
                ->addActionLabel('Add menu item'),
        ]);
    }

    /**
     * Menu items, with "Dropdown items" nested inside them down to press.menus.max_depth levels.
     * Drag the handle to reorder items within a level.
     */
    private static function itemsRepeater(string $name, int $level): Repeater
    {
        $schema = self::itemFields();

        if ($level < Menus::maxDepth()) {
            $schema[] = self::itemsRepeater('children', $level + 1)
                ->label($level === 1 ? 'Dropdown items' : 'Sub-items (level '.($level + 1).')')
                ->collapsed()
                ->columnSpanFull()
                ->addActionLabel($level === 1 ? 'Add dropdown item' : 'Add sub-item');
        }

        return Repeater::make($name)
            ->relationship()
            ->orderColumn('sort_order')
            ->reorderableWithDragAndDrop()
            ->collapsible()
            ->itemLabel(fn (array $state) => $state['label'] ?? null)
            ->schema($schema)
            ->defaultItems(0);
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
                TextColumn::make('locale')->label('Language')->badge()->formatStateUsing(fn (?string $state) => strtoupper((string) $state))->placeholder('All')->visible(fn () => app(Locales::class)->enabled()),
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
