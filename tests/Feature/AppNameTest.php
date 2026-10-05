<?php

namespace Tests\Feature;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Env;
use Tests\TestCase;

class AppNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_and_mail_names_default_to_pn_press_when_unset(): void
    {
        $repository = Env::getRepository();
        $keys = ['APP_NAME', 'MAIL_FROM_NAME'];
        $saved = [];

        foreach ($keys as $key) {
            $saved[$key] = $repository->get($key);
            $repository->clear($key);
        }

        try {
            $this->assertNull(env('APP_NAME'));
            $config = require base_path('config/app.php');
            $mail = require base_path('config/mail.php');
        } finally {
            foreach ($saved as $key => $value) {
                if ($value !== null) {
                    $repository->set($key, $value);
                }
            }
        }

        $this->assertSame('PN Press', $config['name']);
        $this->assertSame('PN Press', $mail['from']['name']);
    }

    public function test_public_pages_show_the_configured_name_and_no_old_product_name(): void
    {
        config(['app.name' => 'PN Press']);
        $post = Post::factory()->published()->create();

        foreach (['/', '/blog/'.$post->slug] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('<title>', false)
                ->assertSee('PN Press')
                ->assertDontSee('Laravel Blog CMS')
                ->assertDontSee('a Laravel blog starter kit');
        }
    }
}
