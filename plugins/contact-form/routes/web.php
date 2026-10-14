<?php

use Illuminate\Support\Facades\Route;
use PnPress\ContactForm\Http\SendMessageController;

// Loaded with the "web" middleware; part of `php artisan route:cache`.
Route::post('/contact-form/send', SendMessageController::class)
    ->middleware('throttle:5,1')
    ->name('contact-form.send');
