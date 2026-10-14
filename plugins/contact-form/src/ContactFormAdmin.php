<?php

namespace PnPress\ContactForm;

use Filament\Contracts\Plugin;
use Filament\Panel;
use PnPress\ContactForm\Filament\ContactMessageResource;

/**
 * The plugin's admin screens: a Filament plugin named in plugin.json ("admin").
 * PN Scripts Press adds it to the panel while the plugin is active.
 */
class ContactFormAdmin implements Plugin
{
    public function getId(): string
    {
        return 'contact-form';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([ContactMessageResource::class]);
    }

    public function boot(Panel $panel): void {}
}
