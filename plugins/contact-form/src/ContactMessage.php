<?php

namespace PnPress\ContactForm;

use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    protected $table = 'contact_messages';

    protected $fillable = ['name', 'email', 'message', 'page_url'];
}
