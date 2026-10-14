<?php

namespace App\Press\Blocks;

use App\Models\Media;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

/**
 * The blocks every PN Scripts Press site has. Views live in resources/views/press/blocks.
 */
class CoreBlocks
{
    public static function register(BlockRegistry $registry): void
    {
        $registry->register(new Block('hero', 'Hero', fn () => [
            TextInput::make('eyebrow')->maxLength(80),
            TextInput::make('heading')->required()->maxLength(160),
            Textarea::make('text')->rows(3)->maxLength(500),
            TextInput::make('button_label')->maxLength(60),
            TextInput::make('button_url')->maxLength(255)->helperText('/path, https://… or mailto:'),
            self::mediaSelect(),
        ], 'press.blocks.hero', 'heroicon-o-sparkles'));

        $registry->register(new Block('text', 'Text', fn () => [
            RichEditor::make('content')->required(),
        ], 'press.blocks.text', 'heroicon-o-bars-3-bottom-left'));

        $registry->register(new Block('image', 'Image', fn () => [
            self::mediaSelect()->required(),
            TextInput::make('caption')->maxLength(255),
            Toggle::make('wide')->label('Full width'),
        ], 'press.blocks.image', 'heroicon-o-photo'));

        $registry->register(new Block('features', 'Feature list', fn () => [
            TextInput::make('heading')->maxLength(160),
            Repeater::make('items')
                ->schema([
                    TextInput::make('title')->required()->maxLength(120),
                    Textarea::make('text')->rows(2)->maxLength(400),
                ])
                ->minItems(1)
                ->maxItems(12)
                ->columns(2),
        ], 'press.blocks.features', 'heroicon-o-squares-2x2'));

        $registry->register(new Block('quote', 'Quote', fn () => [
            Textarea::make('text')->required()->rows(3)->maxLength(800),
            TextInput::make('cite')->maxLength(120),
        ], 'press.blocks.quote', 'heroicon-o-chat-bubble-bottom-center-text'));

        $registry->register(new Block('call_to_action', 'Call to action', fn () => [
            TextInput::make('heading')->required()->maxLength(160),
            Textarea::make('text')->rows(2)->maxLength(400),
            TextInput::make('button_label')->required()->maxLength(60),
            TextInput::make('button_url')->required()->maxLength(255),
        ], 'press.blocks.call-to-action', 'heroicon-o-megaphone'));

        $registry->register(new Block('latest_posts', 'Latest posts', fn () => [
            TextInput::make('heading')->default('From the blog')->maxLength(160),
            TextInput::make('count')->numeric()->minValue(1)->maxValue(12)->default(3),
        ], 'press.blocks.latest-posts', 'heroicon-o-newspaper'));
    }

    public static function mediaSelect(string $name = 'media_id'): Select
    {
        return Select::make($name)
            ->label('Image')
            ->options(fn () => Media::query()->where('mime_type', 'like', 'image/%')->latest()->limit(500)->pluck('name', 'id'))
            ->searchable()
            ->helperText('Upload images in Content → Media.');
    }
}
