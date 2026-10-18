<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An old address that sends visitors on (Content → Redirects). Only looked up when a request
 * would otherwise be a 404, so redirects never hide a live page and cost nothing on normal requests.
 *
 * @property int $id
 * @property string $from_path
 * @property string $to_url
 * @property int $status_code
 * @property bool $automatic
 * @property int $hits
 */
class Redirect extends Model
{
    public const CODES = [301 => '301 Moved permanently', 302 => '302 Found (temporary)', 307 => '307 Temporary', 308 => '308 Permanent'];

    protected $fillable = ['from_path', 'to_url', 'status_code', 'automatic'];

    protected function casts(): array
    {
        return [
            'automatic' => 'boolean',
            'last_hit_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Redirect $redirect): void {
            $redirect->from_path = self::normalize($redirect->from_path);
        });
    }

    /** "/Old-Page/?x=1#top" => "Old-Page" (no slashes at the ends, no query or fragment). */
    public static function normalize(?string $path): string
    {
        $path = (string) parse_url('/'.ltrim(trim((string) $path), '/'), PHP_URL_PATH);

        return trim(rawurldecode($path), '/');
    }

    /** Relative paths and http(s) URLs only; never protocol-relative ("//host") or script URLs. */
    public static function safeTarget(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '' || str_starts_with($url, '//') || str_starts_with($url, '/\\')) {
            return null;
        }

        if (str_starts_with($url, '/')) {
            return $url;
        }

        return preg_match('#^https?://[^\s/]#i', $url) ? $url : null;
    }

    /**
     * Records that an address moved, e.g. after a slug change. Any redirect *from* the new address is
     * removed (it would hide the page) and chains through the old address are pointed at the new one.
     */
    public static function moved(string $fromPath, string $toPath): void
    {
        $from = self::normalize($fromPath);
        $to = self::normalize($toPath);

        if ($from === $to || $from === '') {
            return;
        }

        self::query()->where('from_path', $to)->delete();
        self::query()->where('to_url', '/'.$from)->update(['to_url' => '/'.$to]);

        self::query()->updateOrCreate(['from_path' => $from], ['to_url' => '/'.$to, 'status_code' => 301, 'automatic' => true]);
    }

    public function recordHit(): void
    {
        $this->newQuery()->whereKey($this->getKey())->update(['hits' => $this->hits + 1, 'last_hit_at' => now()]);
    }
}
