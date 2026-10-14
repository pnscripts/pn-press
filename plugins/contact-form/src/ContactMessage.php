<?php

namespace PnPress\ContactForm;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $name
 * @property string $email
 * @property string $message
 * @property ?string $page_url
 * @property ?Carbon $read_at
 * @property ?Carbon $handled_at
 */
class ContactMessage extends Model
{
    /** Who may open the inbox. Administrators always can; grant it to other roles as needed. */
    public const PERMISSION = 'manage contact messages';

    protected $table = 'contact_messages';

    protected $fillable = ['name', 'email', 'message', 'page_url'];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'handled_at' => 'datetime',
        ];
    }

    public function scopeUnhandled(Builder $query): void
    {
        $query->whereNull('handled_at');
    }

    public function isHandled(): bool
    {
        return $this->handled_at !== null;
    }

    public function markRead(): void
    {
        if ($this->read_at === null) {
            $this->forceFill(['read_at' => now()])->save();
        }
    }

    public function markHandled(bool $handled = true): void
    {
        $this->forceFill([
            'handled_at' => $handled ? ($this->handled_at ?? now()) : null,
            'read_at' => $this->read_at ?? now(),
        ])->save();
    }
}
