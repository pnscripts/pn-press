<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\File;
use InvalidArgumentException;

/**
 * Draws the abstract demo cover images as SVG files.
 *
 * The output depends only on the seed and the motif, so running the
 * generator twice produces byte-identical files. No photos, people or
 * third-party artwork are involved; every shape is drawn here.
 */
final class DemoCoverGenerator
{
    public const WIDTH = 1200;

    public const HEIGHT = 630;

    public const MOTIFS = ['orbits', 'columns', 'waves', 'grid'];

    /** Public directory, relative to public_path(), that holds the demo covers. */
    public const DIRECTORY = 'images/demo';

    private const NAVY = ['#0a1630', '#0f2147', '#142a57'];

    private const ACCENTS = ['#f67a3c', '#4f90ff', '#bcd3ff', '#ffb38a'];

    private int $state = 1;

    /**
     * Public URL path (leading slash) that the seeder stores in posts.featured_image.
     */
    public static function publicPath(string $slug): string
    {
        return '/'.self::DIRECTORY.'/'.$slug.'.svg';
    }

    /**
     * Write one SVG per demo post into public/images/demo and return the file paths.
     *
     * @return list<string>
     */
    public function writeAll(): array
    {
        $directory = public_path(self::DIRECTORY);
        File::ensureDirectoryExists($directory);

        $written = [];

        foreach (DemoContent::posts() as $post) {
            $path = $directory.'/'.$post['slug'].'.svg';
            File::put($path, $this->svg($post['slug'], $post['cover']));
            $written[] = $path;
        }

        return $written;
    }

    public function svg(string $seed, string $motif): string
    {
        if (! in_array($motif, self::MOTIFS, true)) {
            throw new InvalidArgumentException("Unknown cover motif [{$motif}].");
        }

        $this->state = (crc32($seed) & 0x7FFFFFFF) ?: 1;

        $w = self::WIDTH;
        $h = self::HEIGHT;
        $angle = $this->int(15, 75);
        $from = self::NAVY[$this->int(0, 1)];
        $to = self::NAVY[2];
        $glow = $this->int(0, 1) === 0 ? self::ACCENTS[0] : self::ACCENTS[1];
        $glowX = '.'.$this->int(30, 70);
        $glowY = '.'.$this->int(30, 70);

        $shapes = match ($motif) {
            'orbits' => $this->orbits(),
            'columns' => $this->columns(),
            'waves' => $this->waves(),
            'grid' => $this->grid(),
        };

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {$w} {$h}" width="{$w}" height="{$h}" role="img" aria-hidden="true">
  <defs>
    <linearGradient id="bg" gradientTransform="rotate({$angle} .5 .5)">
      <stop offset="0" stop-color="{$from}"/>
      <stop offset="1" stop-color="{$to}"/>
    </linearGradient>
    <radialGradient id="glow" cx="{$glowX}" cy="{$glowY}" r=".6">
      <stop offset="0" stop-color="{$glow}" stop-opacity=".35"/>
      <stop offset="1" stop-color="{$glow}" stop-opacity="0"/>
    </radialGradient>
    <pattern id="dots" width="24" height="24" patternUnits="userSpaceOnUse">
      <circle cx="2" cy="2" r="1.4" fill="#bcd3ff" fill-opacity=".12"/>
    </pattern>
  </defs>
  <rect width="{$w}" height="{$h}" fill="url(#bg)"/>
  <rect width="{$w}" height="{$h}" fill="url(#glow)"/>
  <rect width="{$w}" height="{$h}" fill="url(#dots)"/>
{$shapes}
</svg>

SVG;
    }

    private function orbits(): string
    {
        $cx = $this->int(560, 760);
        $cy = $this->int(260, 360);
        $out = '';

        for ($i = 0; $i < 6; $i++) {
            $r = 50 + $i * $this->int(44, 56);
            $color = $i === 2 ? self::ACCENTS[0] : self::ACCENTS[2];
            $opacity = $i === 2 ? '.9' : '.'.$this->int(18, 35);
            $width = $i === 2 ? 6 : 2;
            $out .= "  <circle cx=\"{$cx}\" cy=\"{$cy}\" r=\"{$r}\" fill=\"none\" stroke=\"{$color}\" stroke-width=\"{$width}\" stroke-opacity=\"{$opacity}\"/>\n";
        }

        for ($i = 0; $i < 5; $i++) {
            $r = 60 + $this->int(0, 5) * 50;
            $t = deg2rad($this->int(0, 359));
            $x = round($cx + cos($t) * $r, 1);
            $y = round($cy + sin($t) * $r, 1);
            $color = self::ACCENTS[$this->int(0, 3)];
            $size = $this->int(7, 16);
            $out .= "  <circle cx=\"{$x}\" cy=\"{$y}\" r=\"{$size}\" fill=\"{$color}\"/>\n";
        }

        return $out.$this->stripe();
    }

    private function columns(): string
    {
        $out = '';
        $count = $this->int(8, 10);
        $x = (int) ((self::WIDTH - ($count * 72 - 24)) / 2);
        $highlight = $this->int(2, 5);

        for ($i = 0; $i < $count; $i++) {
            $height = $this->int(140, 400);
            $y = 500 - $height;
            $color = $i === $highlight ? self::ACCENTS[0] : self::ACCENTS[$this->int(1, 2)];
            $opacity = $color === self::ACCENTS[0] ? '.95' : '.'.$this->int(35, 70);
            $out .= "  <rect x=\"{$x}\" y=\"{$y}\" width=\"48\" height=\"{$height}\" rx=\"24\" fill=\"{$color}\" fill-opacity=\"{$opacity}\"/>\n";
            $x += 72;
        }

        return $out.'  <rect x="200" y="520" width="800" height="4" rx="2" fill="#bcd3ff" fill-opacity=".35"/>'."\n";
    }

    private function waves(): string
    {
        $out = '';
        $base = $this->int(190, 240);

        for ($i = 0; $i < 5; $i++) {
            $amp = $this->int(90, 150);
            $y = $base + $i * 52;
            $phase = $this->int(0, 300);
            $c1 = 200 + $phase;
            $c2 = 600 + $phase;
            $up = $y - $amp;
            $down = $y + $amp;
            $color = $i === 1 ? self::ACCENTS[0] : self::ACCENTS[$this->int(1, 2)];
            $opacity = $i === 1 ? '.95' : '.'.$this->int(30, 60);
            $width = $i === 1 ? 8 : 4;
            $out .= "  <path d=\"M-20 {$y} C {$c1} {$up}, {$c2} {$down}, 1220 {$y}\" fill=\"none\" stroke=\"{$color}\" stroke-width=\"{$width}\" stroke-opacity=\"{$opacity}\" stroke-linecap=\"round\"/>\n";
        }

        return $out;
    }

    private function grid(): string
    {
        $out = '';
        $size = 64;
        $gap = 18;
        $startX = $this->int(290, 330);
        $startY = $this->int(100, 120);
        $highlight = $this->int(8, 26);

        for ($row = 0; $row < 5; $row++) {
            for ($col = 0; $col < 7; $col++) {
                $index = $row * 7 + $col;

                if ($this->int(0, 9) < 3 && $index !== $highlight) {
                    continue;
                }

                $x = $startX + $col * ($size + $gap);
                $y = $startY + $row * ($size + $gap);

                if ($index === $highlight) {
                    $out .= "  <rect x=\"{$x}\" y=\"{$y}\" width=\"{$size}\" height=\"{$size}\" rx=\"14\" fill=\"".self::ACCENTS[0]."\"/>\n";

                    continue;
                }

                $opacity = '.'.$this->int(12, 40);
                $out .= "  <rect x=\"{$x}\" y=\"{$y}\" width=\"{$size}\" height=\"{$size}\" rx=\"14\" fill=\"none\" stroke=\"".self::ACCENTS[2]."\" stroke-width=\"2\" stroke-opacity=\"{$opacity}\"/>\n";
            }
        }

        return $out;
    }

    private function stripe(): string
    {
        $y = $this->int(500, 540);

        return "  <rect x=\"80\" y=\"{$y}\" width=\"".$this->int(160, 260).'" height="8" rx="4" fill="'.self::ACCENTS[0]."\"/>\n";
    }

    /**
     * Small deterministic PRNG (Park–Miller) so output never depends on mt_rand's global state.
     */
    private function int(int $min, int $max): int
    {
        $this->state = ($this->state * 48271) % 2147483647;

        return $min + ($this->state % ($max - $min + 1));
    }
}
