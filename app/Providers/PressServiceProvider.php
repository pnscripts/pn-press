<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Media;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use App\Press\Blocks\BlockRegistry;
use App\Press\Blocks\CoreBlocks;
use App\Press\Cache\PageCache;
use App\Press\Hooks;
use App\Press\Menus;
use App\Press\Plugins\PluginManager;
use App\Press\Settings;
use App\Press\Themes\ThemeManager;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * The PN Scripts Press engine: settings, hooks, blocks, menus, plugins and themes.
 */
class PressServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Hooks::class);
        $this->app->singleton(Settings::class);
        $this->app->singleton(BlockRegistry::class);
        $this->app->singleton(Menus::class);
        $this->app->singleton(PluginManager::class);
        $this->app->singleton(ThemeManager::class);
        $this->app->singleton(PageCache::class);
    }

    public function boot(): void
    {
        CoreBlocks::register($this->app->make(BlockRegistry::class));

        // Administrators can do everything, like WordPress's administrator role.
        Gate::before(fn ($user) => method_exists($user, 'hasRole') && $user->hasRole('admin') ? true : null);

        // Active plugins load once every core provider has booted, so they can use everything.
        $this->app->booted(fn () => $this->app->make(PluginManager::class)->bootActive());

        $this->flushPageCacheOnChanges();

        // @hook('head') prints a render hook; @hook('name', $arg) passes arguments.

        Blade::directive('hook', fn (string $expression) => "<?php echo app(\\App\\Press\\Hooks::class)->render({$expression}); ?>");
    }

    /** Content, menus, settings, plugins and themes all show on public pages: any change flushes the page cache. */
    private function flushPageCacheOnChanges(): void
    {
        $flush = fn () => $this->app->make(PageCache::class)->flush();

        foreach ([Post::class, Page::class, Category::class, Menu::class, MenuItem::class, Media::class, User::class] as $model) {
            $model::saved($flush);
            $model::deleted($flush);
        }

        $hooks = $this->app->make(Hooks::class);
        foreach (['settings.changed', 'plugin.installed', 'plugin.activated', 'plugin.deactivated', 'plugin.upgraded', 'plugin.uninstalling',
            'theme.installed', 'theme.activated', 'theme.uninstalled', 'page_cache.flush'] as $action) {
            $hooks->addAction($action, $flush);
        }
    }
}
