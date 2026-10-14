# Writing a PN Scripts Press plugin

A plugin is a folder with a `plugin.json`. Look at `plugins/contact-form` and `plugins/seo-sitemap` for two complete examples.

```
my-plugin/
├── plugin.json
├── src/
│   └── MyPluginServiceProvider.php
├── database/migrations/        (optional)
└── resources/views/            (optional, namespace = slug: view('my-plugin::thing'))
```

## plugin.json

```json
{
    "slug": "my-plugin",
    "name": "My Plugin",
    "version": "1.0.0",
    "description": "One sentence about what it does.",
    "author": "You",
    "requires": { "press": ">=1.0" },
    "provider": "Acme\\MyPlugin\\MyPluginServiceProvider",
    "autoload": { "psr-4": { "Acme\\MyPlugin\\": "src/" } },
    "migrations": "database/migrations",
    "views": "resources/views"
}
```

| Field | Rules |
|-------|-------|
| `slug` | lowercase letters, digits and dashes; must equal the folder name |
| `name` | up to 80 characters |
| `version` | `1.0`, `1.0.0`, `1.0.0-beta.1` |
| `requires.press` | `>=1.0`, `>1.0`, `^1.0` or `~1.0`; activation is refused when it does not match |
| `provider` | a Laravel `ServiceProvider` class; optional |
| `autoload.psr-4` | namespace prefix (ending in `\\`) to a folder inside the plugin. `App\`, `Illuminate\`, `Filament\`, `Livewire\`, `Database\`, `Tests\` and `Spatie\` are refused |
| `migrations`, `views` | folders inside the plugin; paths with `..` are refused |

No Composer step is needed: PN Scripts Press autoloads the namespace itself.

## Lifecycle

| Step | What happens | Hook |
|------|--------------|------|
| Install | The zip is validated and unpacked into `plugins/<slug>` (or a folder is copied). Nothing runs yet. | |
| Activate | Autoload, views and the provider are registered, migrations run, the plugin is marked active. | `plugin.activated` |
| Every request | Active plugins load after the core has booted. A plugin that throws is reported and skipped. | `plugin.loaded` |
| Deactivate | The plugin stops loading. Its tables and settings stay. | `plugin.deactivated` |
| Uninstall | Deactivate, roll back its migrations (data is lost), delete settings `plugin.<slug>.*` and the folder. | `plugin.uninstalling` |

From the command line:

```bash
php artisan press:plugin list
php artisan press:plugin install path/to/my-plugin.zip      # or a folder; --replace to update
php artisan press:plugin activate my-plugin
php artisan press:plugin deactivate my-plugin
php artisan press:plugin uninstall my-plugin
```

## The service provider

Do your work in `boot()`; the core is fully booted by then.

```php
namespace Acme\MyPlugin;

use App\Press\Blocks\Block;
use App\Press\Blocks\BlockRegistry;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class MyPluginServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // A page block. The view gets $data (the saved fields), $block and $context (the page).
        $this->app->make(BlockRegistry::class)->register(new Block(
            key: 'notice',
            label: 'Notice',
            fields: fn () => [TextInput::make('text')->required()],
            view: 'my-plugin::notice',
            icon: 'heroicon-o-information-circle',
        ));

        // Routes. Add the "web" middleware for sessions, CSRF and the theme.
        Route::middleware('web')->get('/hello', fn () => 'Hello');

        // Print something in every page <head>. Escape what you print.
        add_action('head', fn () => '<meta name="my-plugin" content="1">');

        // A section on the Settings screen. Name fields plugin.<slug>.<key>.
        add_filter('settings.sections', function (array $sections) {
            $sections[] = Section::make('My plugin')->schema([
                TextInput::make('plugin.my-plugin.greeting'),
            ]);

            return $sections;
        });
    }
}
```

Read a setting anywhere with `press_setting('plugin.my-plugin.greeting', 'Hi')`.

## Actions and filters

```php
add_action('name', callable $callback, int $priority = 10);
do_action('name', ...$args);              // returns the callbacks' results
add_filter('name', callable $callback, int $priority = 10);
apply_filters('name', $value, ...$args);  // returns the filtered value
```

Lower priority runs first. In Blade, `@hook('name')` prints the joined string output of an action.

Hooks the core provides:

| Hook | Type | Arguments |
|------|------|-----------|
| `head`, `body.start`, `body.end`, `footer` | render action | — |
| `page.viewing` | action | `Page` |
| `page.after_content`, `post.after_content` | render action | `Page` / `Post` |
| `page.blocks` | filter | block list, `Page` |
| `blocks.before_render`, `blocks.html` | filter | blocks / HTML, context |
| `post.content` | filter | body HTML, `Post` |
| `menu.items` | filter | menu tree, location |
| `menu.locations` | filter | `['primary' => 'Main navigation', ...]` |
| `page.templates` | filter | `['default' => 'Default', ...]` |
| `settings.sections` | filter | Filament sections |
| `settings.saved` | action | saved values |
| `sitemap.urls` | filter | list of `['loc' => ..., 'lastmod' => ...]` (SEO Sitemap plugin) |
| `contact-form.received` | action | `ContactMessage` (Contact Form plugin) |
| `plugin.loaded`, `plugin.activated`, `plugin.deactivated`, `plugin.uninstalling` | action | `PluginManifest` |
| `theme.applied`, `theme.activated` | action | `ThemeManifest` |

## Packaging

Zip the plugin folder (`my-plugin/plugin.json` at the top, or `plugin.json` at the zip root). The upload is refused when it:

- is bigger than 10 MB, has more than 2,000 entries, unpacks to more than 50 MB, or compresses suspiciously well;
- contains absolute paths, `..`, backslashes, symlinks, hidden files (`.htaccess`, `.env`, `.git`) or unusual characters in names;
- contains file types other than `php json md txt css js map svg png jpg jpeg gif webp ico woff woff2 ttf otf xml csv`.

The limits live in `config/press.php`.

## Security notes

A plugin is PHP that runs with full access to the site and its database, like a WordPress plugin. Only install plugins you trust, keep `manage plugins` for administrators, and set `PRESS_PACKAGE_UPLOADS=false` on sites where nobody should upload code from the browser.
