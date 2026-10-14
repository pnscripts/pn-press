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

    /** Create the record for a file already stored on the disk (an upload). */
    public static function fromUpload(string $disk, string $path, ?int $userId = null, array $attributes = []): self
    {
        $storage = Storage::disk($disk);
        $mime = $storage->mimeType($path) ?: null;
        $data = $attributes + [
            'disk' => $disk,
            'path' => $path,
            'user_id' => $userId,
            'name' => basename($path),
            'mime_type' => $mime,
            'size' => $storage->size($path),
        ];

        if (str_starts_with((string) $mime, 'image/') && $mime !== 'image/svg+xml') {
            $info = @getimagesize($storage->path($path));
            if ($info) {
                [$data['width'], $data['height']] = $info;
            }
        }

        return static::query()->create($data);
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
