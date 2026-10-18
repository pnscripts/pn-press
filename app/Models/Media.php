<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

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

        // SVG files are served from this site's own address, so one with scripts could act as a signed-in admin.
        if (in_array($mime, ['image/svg+xml', 'text/xml', 'application/xml'], true) || str_ends_with(strtolower($path), '.svg')) {
            if (! self::isSafeSvg((string) $storage->get($path))) {
                $storage->delete($path);

                throw ValidationException::withMessages(['data.path' => 'This SVG contains scripts, event handlers, external references or embedded documents and was not saved.']);
            }
        }
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

    /** True for a plain drawing: no scripts, handlers, javascript: links, foreign HTML, entities or external references. */
    public static function isSafeSvg(string $svg): bool
    {
        $patterns = [
            '/<\s*script/i', '/<\s*foreignObject/i', '/<\s*(iframe|embed|object|link|meta|style[^>]*>[^<]*@import)/i',
            '/\son[a-z]+\s*=/i', '/(javascript|vbscript)\s*:/i', '/<!\s*(ENTITY|DOCTYPE)/i',
            '/(xlink:)?href\s*=\s*["\']\s*(?!#|data:image\/(png|jpe?g|gif|webp);)/i', '/data:\s*text\/html/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $svg)) {
                return false;
            }
        }

        return true;
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
