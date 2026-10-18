<?php

namespace PnPress\ContactForm\Http;

use App\Press\I18n\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use PnPress\ContactForm\ContactMessage;

class SendMessageController
{
    public function __invoke(Request $request): RedirectResponse
    {
        // Errors in the language of the page the form was sent from (a hidden field; only configured languages count).
        $locales = app(Locales::class);
        if ($locales->isSupported(is_string($request->input('locale')) ? $request->input('locale') : null)) {
            $locales->activate($request->input('locale'));
        }

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
