<?php

namespace App\Filament\Resources\Posts\Schemas;

use App\Enums\PostStatus;
use App\Filament\Support\Translatable;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true, modifyRuleUsing: Translatable::uniquePerLocale()),
                Select::make('category_id')
                    ->relationship('category', 'name', fn ($query, Get $get) => $query->where('locale', Translatable::formLocale($get)))
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Select::make('status')
                    ->options([
                        PostStatus::Draft->value => 'Draft',
                        PostStatus::Published->value => 'Published',
                    ])
                    ->required()
                    ->default(PostStatus::Draft->value),
                DateTimePicker::make('published_at')
                    ->nullable(),
                TextInput::make('featured_image')
                    ->label('Featured image')
                    ->maxLength(255)
                    ->helperText('A full URL, a path on the public disk, or a path inside public/ starting with / (for example /images/demo/cover.svg).'),
                Textarea::make('excerpt')
                    ->required()
                    ->rows(3)
                    ->columnSpanFull(),
                RichEditor::make('body')
                    ->required()
                    ->columnSpanFull(),
                Section::make('SEO')
                    ->collapsed()
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('meta_title')->maxLength(70)->helperText('Defaults to the title.'),
                        Textarea::make('meta_description')->rows(2)->maxLength(160)->helperText('Defaults to the excerpt.'),
                    ]),
            ]);
    }
}
