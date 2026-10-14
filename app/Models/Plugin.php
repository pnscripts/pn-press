<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Activation state of a plugin folder. The folder and its plugin.json are the source of truth for code;
 * this row only records that the plugin was activated and with which version.
 */
class Plugin extends Model
{
    protected $fillable = ['slug', 'name', 'version', 'enabled', 'activated_at'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'activated_at' => 'datetime',
        ];
    }
}
