<?php

namespace PnPress\ContactForm;

use App\Press\Blocks\Block;
use App\Press\Blocks\BlockRegistry;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Example plugin: a block, a route, a table (migration), a settings section and a hook.
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

        Route::middleware(['web', 'throttle:5,1'])
            ->post('/contact-form/send', fn (Request $request) => $this->send($request))
            ->name('contact-form.send');

        add_filter('settings.sections', function (array $sections) {
            $sections[] = Section::make('Contact form')
                ->description('From the Contact Form plugin.')
                ->schema([
                    TextInput::make('plugin.contact-form.recipient')
                        ->label('Send messages to')
                        ->email()
                        ->helperText('Leave empty to only keep messages in the database.'),
                ]);

            return $sections;
        });
    }

    private function send(Request $request)
    {
        // Honeypot: real people leave the hidden "website" field empty.
        if (filled($request->input('website'))) {
            return back()->with('contact-form.sent', true);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'message' => ['required', 'string', 'min:5', 'max:5000'],
        ]);

        $message = ContactMessage::query()->create($data + ['page_url' => substr((string) url()->previous(), 0, 255)]);

        do_action('contact-form.received', $message);

        if ($recipient = press_setting('plugin.contact-form.recipient')) {
            Mail::raw("{$message->message}\n\n— {$message->name} <{$message->email}>", function ($mail) use ($recipient, $message) {
                $mail->to($recipient)->replyTo($message->email, $message->name)->subject('New message from your website');
            });
        }

        return back()->with('contact-form.sent', true)->withFragment('contact-form');
    }
}
