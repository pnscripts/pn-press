<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Filament\Widgets\DraftsAwaitingReview;
use App\Filament\Widgets\LatestPosts;
use App\Filament\Widgets\PostsByCategoryChart;
use App\Filament\Widgets\PostStatsOverview;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::query()->where('email', 'admin@example.com')->firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_dashboard_renders_the_pn_press_widgets(): void
    {
        $this->get('/admin')
            ->assertOk()
            ->assertSee('PN Press')
            ->assertSeeLivewire(PostStatsOverview::class)
            ->assertSeeLivewire(LatestPosts::class)
            ->assertSeeLivewire(DraftsAwaitingReview::class)
            ->assertSeeLivewire(PostsByCategoryChart::class)
            ->assertDontSeeLivewire(FilamentInfoWidget::class);
    }

    public function test_panel_uses_the_pn_press_brand(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertSame('PN Press', $panel->getBrandName());
        $this->assertArrayHasKey('primary', $panel->getColors());
        $this->assertNotContains(FilamentInfoWidget::class, $panel->getWidgets());
    }

    public function test_stats_widget_counts_posts_by_status(): void
    {
        Livewire::test(PostStatsOverview::class)
            ->assertOk()
            ->assertSee('Published posts')
            ->assertSee('Drafts')
            ->assertSee('Scheduled')
            ->assertSee('Categories');
    }

    public function test_latest_posts_widget_lists_recent_posts(): void
    {
        Livewire::test(LatestPosts::class)
            ->assertOk()
            ->loadTable()
            ->assertCanSeeTableRecords(Post::query()->where('slug', 'what-to-check-before-you-launch')->get())
            ->assertSee('Latest posts');
    }

    public function test_drafts_widget_lists_only_drafts(): void
    {
        Livewire::test(DraftsAwaitingReview::class)
            ->assertOk()
            ->loadTable()
            ->assertCanSeeTableRecords(Post::query()->where('status', PostStatus::Draft)->get())
            ->assertCanNotSeeTableRecords(Post::query()->where('status', PostStatus::Published)->get())
            ->assertSee('Drafts waiting to be published');
    }

    public function test_category_chart_renders(): void
    {
        Livewire::test(PostsByCategoryChart::class)
            ->assertOk()
            ->assertSee('Published posts by category');
    }

    public function test_post_editor_renders(): void
    {
        $post = Post::query()->firstOrFail();

        $this->get('/admin/posts/'.$post->getKey().'/edit')->assertOk()->assertSee($post->title);
    }
}
