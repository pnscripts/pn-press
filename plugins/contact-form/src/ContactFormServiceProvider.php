<?php

namespace PnPress\ContactForm;

use App\Press\Blocks\Block;
use App\Press\Blocks\BlockRegistry;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Illuminate\Support\ServiceProvider;

/**
 * Example plugin: a block, a route file, a table (migrations), a settings section, a hook
 * and an admin inbox (ContactFormAdmin, a Filament plugin).
 */
class ContactFormServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(BlockRegistry::class)->register(new Block(
            key: 'contact_form',
            label: 'Contact form',
            fields: fn () => [
                TextInput::make('heading')->default('Get in touch')->maxLength(120),
                Textarea::make('intro')->rows(2)->maxLength(400),
                TextInput::make('button_label')->default('Send message')->maxLength(40),
            ],
            view: 'contact-form::block',
            icon: 'heroicon-o-envelope',
            source: 'contact-form',
        ));

        add_filter('settings.sections', function (array $sections) {
            $sections[] = Section::make('Contact form')
                ->description('From the Contact Form plugin.')
                ->schema([
                    TextInput::make('plugin.contact-form.recipient')
                        ->label('Send messages to')
                        ->email()
                        ->helperText('Leave empty to only keep messages in the database (Content → Messages).'),
                ]);

            return $sections;
        });
    }
}
