<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    protected $table = 'media';

    protected $fillable = ['user_id', 'disk', 'path', 'name', 'mime_type', 'size', 'width', 'height', 'alt', 'caption'];

    protected static function booted(): void
    {
        static::deleted(function (Media $media): void {
            Storage::disk($media->disk)->delete($media->path);
        });
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    public function humanSize(): string
    {
        $size = (int) $this->size;

        return $size >= 1048576
            ? number_format($size / 1048576, 1).' MB'
            : number_format(max(1, $size / 1024)).' KB';
    }
}
