<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Press\Settings;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Env;
use Tests\TestCase;

class AppNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_and_mail_names_default_to_pn_scripts_press_when_unset(): void
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

        $this->assertSame('PN Scripts Press', $config['name']);
        $this->assertSame('PN Scripts Press', $mail['from']['name']);
    }

    public function test_public_pages_show_the_configured_name_and_no_old_product_name(): void
    {
        config(['app.name' => 'PN Scripts Press']);
        $post = Post::factory()->published()->create();

        foreach (['/', '/blog/'.$post->slug] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('<title>', false)
                ->assertSee('PN Scripts Press')
                ->assertDontSee('Laravel Blog CMS')
                ->assertDontSee('a Laravel blog starter kit');
        }
    }

    public function test_a_fresh_install_is_called_pn_scripts_press_even_with_laravels_stock_name(): void
    {
        foreach (['Laravel', 'Laravel Blog CMS', 'PN Press', '', null] as $stock) {
            config(['app.name' => $stock]);
            app(Settings::class)->flush();

            $this->assertSame('PN Scripts Press', app(Settings::class)->siteName());
            $this->get('/blog')->assertOk()->assertSee('<title>Journal · PN Scripts Press', false)->assertDontSee('Laravel</');
        }

        // An APP_NAME of your own still wins, and Settings › Site name wins over both.
        config(['app.name' => 'Ink & Paper']);
        $this->assertSame('Ink & Paper', app(Settings::class)->siteName());
        app(Settings::class)->set('site.name', 'The Quarterly');
        $this->assertSame('The Quarterly', app(Settings::class)->siteName());
    }

    public function test_the_demo_seed_stores_the_default_site_name_and_keeps_an_existing_one(): void
    {
        config(['app.name' => 'Laravel']);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame('PN Scripts Press', app(Settings::class)->get('site.name'));
        $this->get('/')->assertOk()->assertSee('PN Scripts Press');

        app(Settings::class)->set('site.name', 'Mine');
        $this->seed(DatabaseSeeder::class);
        $this->assertSame('Mine', app(Settings::class)->get('site.name'));
    }

    public function test_the_example_environment_file_names_the_product(): void
    {
        $this->assertMatchesRegularExpression('/^APP_NAME="PN Scripts Press"$/m', file_get_contents(base_path('.env.example')));
    }
}
