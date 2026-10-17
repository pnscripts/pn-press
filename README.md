# PN Scripts Press

PN Scripts Press is a [PN Scripts](https://pnscripts.com) product ([product page](https://pnscripts.com/products/pn-press)). It was previously published as Laravel Blog CMS.

A Laravel CMS with WordPress-style plugins and themes: pages built from blocks, a blog, menus, a media library, SEO fields, page revisions, users and roles, and an admin built on [Filament](https://filamentphp.com) at `/admin`. Plugins and themes install from a `.zip` (or a folder) and are switched on and off in the admin.

Built with Laravel 13, Filament 5, Livewire 4, Spatie Permission and Tailwind CSS 4.

Repository: [github.com/pnscripts/pn-press](https://github.com/pnscripts/pn-press)

## Requirements

- PHP 8.4+ (the committed `composer.lock` pins Symfony 8.1 packages that need PHP 8.4)
- Composer
- Node.js 18+ and npm (for Vite / Tailwind in development)
- SQLite (default) or MySQL / PostgreSQL

## Install

```bash
git clone https://github.com/pnscripts/pn-press.git
cd pn-press
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

Visit [http://127.0.0.1:8000](http://127.0.0.1:8000) for the demo site. The seed creates a home page, About and Contact pages, two menus, activates the Folio theme and the two example plugins.

## Admin

- URL: `/admin`
- Email: `admin@example.com`
- Password: `password`

Change these credentials before any real deployment.

Filament login stays at `/admin`. Only users with the `admin` or `editor` role can open the panel.

## What is included

**Content**

- Pages built from blocks: hero, text, image, feature list, quote, call to action, latest posts (plugins add more). Child pages (`/services/design`), drafts, scheduled publishing, a page template per page, and a home page chosen in Settings.
- Revisions: every page save keeps the previous version (last 25); restore any of them from the page editor.
- Blog: posts and categories at `/blog`, `/blog/{slug}`, `/category/{slug}`, with drafts and scheduled posts hidden from visitors.
- SEO: meta title, description, social image and "hide from search engines" on pages; meta title and description on posts; canonical and Open Graph tags; `/sitemap.xml` from the SEO Sitemap plugin.
- Menus: a main and a footer location, with nested dropdowns (three levels by default, `press.menus.max_depth`), pointing at pages, posts, categories, the blog or any safe link. Dropdowns work without JavaScript and by keyboard (Enter, Escape, arrow keys). Links to unpublished pages are left out automatically.
- Media library: upload images and PDFs, with alt text and captions. Image blocks pick from the library with thumbnails, search and a browse grid, or upload a new image on the spot.
- Settings: site name (a fresh install is called "PN Scripts Press"), tagline, footer text, home page, posts per page. Plugins add their own sections.
- Page cache: anonymous visitors get pages from a full-page cache (`X-Press-Cache: HIT`). Entries expire when the next scheduled post or page goes live, and every change to content, menus, settings, plugins or themes clears them, so scheduled publishing and edits show on time. Pages with a form, signed-in users, theme previews and unknown query strings are never cached. On when `APP_DEBUG=false` (or `PRESS_PAGE_CACHE=true`); after deploying new theme or view files run `php artisan press:page-cache clear`.
- Two-factor sign-in: every admin user can add an authenticator app (with recovery codes) on their profile page; `PRESS_REQUIRE_2FA=true` makes it mandatory.
- Users and roles: `admin` can do everything; `editor` manages pages, posts, media and menus. Capabilities: `manage plugins`, `manage themes`, `manage settings`, `manage users`, `manage menus`, `manage pages`, `manage media`.

**Plugins** (Extend → Plugins, or `php artisan press:plugin`)

- A plugin is a folder in `plugins/` with a `plugin.json`: its own PSR-4 namespace, a Laravel service provider, views, migrations, a route file (works with `php artisan route:cache`) and admin screens (a Filament plugin class adding resources, pages and navigation).
- Dependencies: `requires.plugins`, `requires.php` and `requires.extensions` in `plugin.json`; a plugin activates only when what it needs is there, and a plugin that others need cannot be switched off under them.
- Update notices: the admin shows when a newer PN Scripts Press, plugin or theme exists (daily `press:check-updates`; plugins and themes opt in with `update_url`). Nothing is downloaded or installed automatically.
- Install from an uploaded `.zip` or a folder on the server, then activate (runs its migrations), deactivate, or uninstall (rolls its tables back, removes its settings and folder). Replacing a plugin with a newer version runs its new migrations on the next request.
- Actions and filters, WordPress style: `add_action`, `do_action`, `add_filter`, `apply_filters`, and `@hook('head')` in Blade.
- Examples: **Contact Form** (a block, a route, its own table, a settings section, a honeypot, a rate limit and a *Messages* inbox to read, mark handled, delete and export messages as CSV) and **SEO Sitemap** (`/sitemap.xml` and a head link).

**Themes** (Appearance → Themes, or `php artisan press:theme`)

- A theme is a folder in `themes/` with a `theme.json`. Any view in its `views/` replaces the default with the same name.
- Settings declared in `theme.json` (colours, choices, toggles, text) are edited in the admin and reach the page as CSS variables and `data-*` attributes.
- Child themes: `"parent": "folio"` inherits the parent's views, assets, settings and templates; the child ships only what it changes.
- Preview a theme before activating it, add page templates, ship a stylesheet and screenshot.
- Two themes: **Folio** (default; warm paper, ink type, teal accent, serif headings) and **Nocturne** (dark, violet accent, centred masthead).

**Package security**

Uploads are checked before anything is written: archive size, entry count, unpacked size and compression ratio; no absolute paths, `..`, backslashes, symlinks or hidden files; only allowed file types (themes may contain PHP only as `*.blade.php`). Only users with `manage plugins` / `manage themes` can install. Nothing is ever downloaded from the internet. Plugins and themes run with full access to your site, so install only code you trust; set `PRESS_PACKAGE_UPLOADS=false` to turn uploads off and install over SSH instead.

Guides: [writing a plugin](docs/plugins.md) and [writing a theme](docs/themes.md).

## What is not included

- Comments, multi-tenancy, multilingual content (planned next: EN/BG pages and posts with hreflang and a language switcher)
- A redirects manager and scheduled backups (planned)
- A plugin or theme directory, automatic updates or remote installs (on purpose)
- Production-ready Telescope access (the `viewTelescope` allowlist is empty on purpose)

## Tests

```bash
php artisan test
composer analyse   # Larastan (PHPStan level 5)
```

## More from PN Scripts

- [PN Scripts Invoice](https://github.com/pnscripts/pn-invoice): free PHP library to write and validate EN 16931 e-invoices (UBL and CII).
- [Laravel and Filament upgrades and care](https://pnscripts.com/services/laravel-filament-care): fixed-price upgrades to Laravel 13 and Filament 5, and monthly care plans.
- All products: [pnscripts.com/products](https://pnscripts.com/products)

## License

MIT. Copyright 2026 ПН СКРИПТС ЕООД (PN Scripts). See [LICENSE](LICENSE).
