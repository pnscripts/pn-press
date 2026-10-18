<?php

namespace Tests\Feature\Press;

use App\Models\Media;
use App\Models\Page;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_svg_with_active_content_is_refused_and_deleted(): void
    {
        Storage::fake('public');

        foreach ([
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
            '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"></svg>',
            '<svg xmlns="http://www.w3.org/2000/svg"><a href="javascript:alert(1)"><text>x</text></a></svg>',
            '<svg xmlns="http://www.w3.org/2000/svg"><foreignObject><iframe src="x"></iframe></foreignObject></svg>',
            '<!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]><svg>&x;</svg>',
            '<svg xmlns="http://www.w3.org/2000/svg"><image href="https://tracker.example/p.png"/></svg>',
        ] as $i => $svg) {
            Storage::disk('public')->put("media/bad{$i}.svg", $svg);

            try {
                Media::fromUpload('public', "media/bad{$i}.svg");
                $this->fail("SVG {$i} was accepted");
            } catch (ValidationException) {
                Storage::disk('public')->assertMissing("media/bad{$i}.svg");
            }
        }

        $this->assertSame(0, Media::query()->count());
    }

    public function test_a_plain_svg_drawing_is_accepted(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('media/ok.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><defs><linearGradient id="g"/></defs><rect width="10" height="10" fill="url(#g)"/><use href="#g"/></svg>');

        $this->assertTrue(Media::fromUpload('public', 'media/ok.svg')->exists);
    }

    public function test_post_bodies_and_text_blocks_are_sanitised(): void
    {
        Post::factory()->published()->create(['slug' => 'x', 'body' => '<p>Hello</p><script>alert(1)</script><img src=x onerror="alert(2)">']);
        Page::factory()->published()->create(['slug' => 'y', 'blocks' => [['type' => 'text', 'data' => ['content' => '<h2>Title</h2><a href="javascript:alert(3)">x</a><iframe src="https://evil.example"></iframe>']]]]);

        $this->get('/blog/x')->assertSee('<p>Hello</p>', false)->assertDontSee('<script>alert(1)', false)->assertDontSee('onerror', false);
        $this->get('/y')->assertSee('<h2>Title</h2>', false)->assertDontSee('javascript:alert(3)', false)->assertDontSee('<iframe', false);
    }
}
