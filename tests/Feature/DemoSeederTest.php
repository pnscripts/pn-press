<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoContent;
use Database\Seeders\DemoCoverGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_the_demo_posts_and_categories(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(count(DemoContent::posts()), Post::query()->count());
        $this->assertSame(count(DemoContent::categories()), Category::query()->count());
        $this->assertGreaterThanOrEqual(6, Post::query()->published()->count());
        $this->assertGreaterThanOrEqual(1, Post::query()->where('status', PostStatus::Draft)->count());
        $this->assertGreaterThanOrEqual(1, Post::query()->where('status', PostStatus::Published)->where('published_at', '>', now())->count());
        $this->assertSame(0, Post::query()->whereNull('category_id')->count());
    }

    public function test_seeder_is_idempotent_and_keeps_editor_changes(): void
    {
        $this->seed(DatabaseSeeder::class);

        $post = Post::query()->firstOrFail();
        $post->update(['title' => 'Edited in the admin']);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(count(DemoContent::posts()), Post::query()->count());
        $this->assertSame(count(DemoContent::categories()), Category::query()->count());
        $this->assertSame(1, User::query()->where('email', 'admin@example.com')->count());
        $this->assertSame('Edited in the admin', $post->fresh()->title);
    }

    public function test_every_post_has_a_unique_excerpt_title_and_body(): void
    {
        $this->seed(DatabaseSeeder::class);

        $posts = Post::query()->get();

        foreach (['excerpt', 'title', 'body'] as $field) {
            $this->assertSame($posts->count(), $posts->pluck($field)->unique()->count(), "Seeded posts share the same {$field}.");
        }
    }

    public function test_demo_content_has_no_placeholder_text(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (Post::query()->get() as $post) {
            $text = strtolower($post->title.' '.$post->excerpt.' '.strip_tags($post->body));

            foreach (['lorem', 'ipsum', 'dolor sit', 'consequatur', 'voluptat'] as $placeholder) {
                $this->assertStringNotContainsString($placeholder, $text, "Post [{$post->slug}] contains placeholder text.");
            }

            $this->assertGreaterThan(40, str_word_count(strip_tags($post->body)), "Post [{$post->slug}] body is too short.");
        }
    }

    public function test_cover_images_are_local_files_not_external_urls(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (Post::query()->get() as $post) {
            $image = (string) $post->featured_image;

            $this->assertNotSame('', $image, "Post [{$post->slug}] has no cover.");
            $this->assertDoesNotMatchRegularExpression('#^(https?:)?//#i', $image, "Post [{$post->slug}] hotlinks an external image.");
            $this->assertFileExists(public_path(ltrim($image, '/')));
            $this->assertStringStartsWith(url('/'), $post->featuredImageUrl());
        }

        foreach (DemoContent::posts() as $post) {
            $this->assertStringNotContainsString('http', $post['body'], "Post [{$post['slug']}] body links or embeds external content.");
        }
    }

    public function test_committed_cover_files_match_the_deterministic_generator(): void
    {
        $generator = new DemoCoverGenerator;

        foreach (DemoContent::posts() as $post) {
            $svg = $generator->svg($post['slug'], $post['cover']);

            $this->assertSame($svg, $generator->svg($post['slug'], $post['cover']));
            $this->assertStringNotContainsString('<image', $svg, 'Covers must be drawn shapes, not embedded pictures.');
            $this->assertStringEqualsFile(
                public_path(ltrim(DemoCoverGenerator::publicPath($post['slug']), '/')),
                $svg,
                "public/images/demo/{$post['slug']}.svg is stale; run php artisan pn-press:demo-covers."
            );
        }
    }

    public function test_seeded_blog_hides_drafts_and_scheduled_posts(): void
    {
        $this->seed(DatabaseSeeder::class);

        $response = $this->get('/')->assertOk();

        foreach (DemoContent::posts() as $post) {
            $visible = $post['status'] === PostStatus::Published && $post['days_ago'] >= 0;

            $visible
                ? $response->assertSee($post['title'], false)
                : $response->assertDontSee($post['title'], false);

            $this->get('/blog/'.$post['slug'])->assertStatus($visible ? 200 : 404);
        }
    }
}
