# Changelog

All notable changes to PN Scripts Press. Dates are in YYYY-MM-DD.

## 1.1.0 (2026-10-18)

### Added

- **Languages (multilingual content):** `PRESS_LOCALES=en,bg`. Pages, posts, categories and menus have a language; the default language lives at `/`, the others under `/{code}`. Per-language slugs and page paths, a **Translate** action (linked draft copy, parent page and category mapped), a language switcher (`press.partials.language-switcher`), `hreflang` + `x-default` links, `og:locale`, per-language site name, tagline, footer text and home page, per-language menus with an "every language" fallback, dates in the page language, Bulgarian front-end strings (`lang/bg.json`, validation, pagination), Bulgarian demo content. Helpers `press_route()`, `press_locale_url()`, `App\Press\I18n\Locales`. Guide: `docs/multilingual.md`.
- **Redirects manager** (Content → Redirects): 301/302/307/308 from old addresses with hit counts; automatic 301 when a published page or post changes its address (loops and chains cleaned up). Only would-be 404s are redirected; targets must be a path or an http(s) URL.

- **Page cache** for anonymous visitors that respects scheduled publishing: entries expire when the next scheduled post or page goes live and are cleared by any content, menu, settings, plugin or theme change. Pages with forms, Livewire components, flashed messages or extra cookies, signed-in users, theme previews and unknown query keys are never cached. `X-Press-Cache` header, `php artisan press:page-cache status|clear`, `PRESS_PAGE_CACHE`, `PRESS_PAGE_CACHE_TTL`, `PRESS_PAGE_CACHE_STORE`.
- **Child themes:** `"parent"` in `theme.json` inherits views, assets, stylesheet, settings and templates. `theme_asset()` helper.
- **Plugin dependencies:** `requires.plugins`, `requires.php`, `requires.extensions` (themes: PHP and extensions). Activation, deactivation and uninstall respect them; dependencies boot first; a plugin whose dependency vanished is skipped and reported.
- **Update notices** for the core (GitHub latest release), plugins and themes (`update_url`): `php artisan press:check-updates`, scheduled daily; badges in the admin. Nothing is downloaded or installed.
- **Two-factor sign-in** for the admin: profile page with authenticator app and recovery codes; `PRESS_REQUIRE_2FA`.
- Larastan (PHPStan level 5) in CI, `composer analyse`.

### Security

Review of the 1.1 code paths (sign-in and 2FA, page cache keys, uploads, plugin loading, languages, redirects):

- **Stored XSS from editors closed:** post bodies and Text blocks are sanitised on output (Symfony HtmlSanitizer via Filament), so an editor's HTML can no longer run scripts for visitors or signed-in admins.
- **SVG uploads** with scripts, event handlers, `javascript:` links, foreign HTML, entities or external references are refused and deleted (they are served from the site's own address).
- **Trusted hosts:** outside `local`/testing only the `APP_URL` host and its subdomains are answered, so a forged `Host` header cannot fill the page cache or poison absolute links. Make sure `APP_URL` is your real address in production.
- Redirect targets are limited to site paths and http(s) URLs (no `//host`, no script URLs); the `locale` sent by forms is accepted only when it is a configured language; language codes in `PRESS_LOCALES` are validated.

### Changed

- symfony/yaml 8.1.9 (CVE-2026-109413).
- `composer.json` now requires PHP `^8.4` (the 1.0 lock file already needed it through Symfony 8.1; `composer create-project` on PHP 8.3 now fails with a clear message instead of a dependency error).
- Contact Form plugin 1.2.0 (sends the page language so errors are translated; strings translatable), SEO Sitemap plugin 1.2.0 (every language, `xhtml:link` alternates).
- Core views use `press_route()` / `$model->url()` instead of `route('blog')` etc. Theme overrides that still call `route('blog')` keep working but always link to the default language.
- Larastan baseline down from 41 to 19 entries (content models are typed).

### Upgrade notes (from 1.0.0)

- Back up the database, then `composer install` and run `php artisan migrate`: 2FA columns on `users`, `locale` + `translation_group` on pages/posts/categories (existing rows get the default language), slug/path uniqueness becomes per language, `locale` on menus, and the `redirects` table. All migrations are reversible.
- Nothing changes for visitors until you list a second language: without `PRESS_LOCALES` the site stays single-language. To add Bulgarian set `PRESS_LOCALES=en,bg`, run `php artisan config:clear` (and `route:cache` if you cache routes), then translate items with **Translate**.
- Production: `APP_URL` must match the address visitors use (trusted hosts).
- Custom theme views: replace `route('blog')`, `route('posts.show', $post->slug)`, `route('categories.show', …)` and `url('/')` with `press_route('blog')`, `$post->url()`, `$category->url()` and `press_locale_url('/')`, and add `@include('press.partials.language-switcher')` to your header. Wrap fixed texts in `__()`.
- The page cache is on when `APP_DEBUG=false`. Run `php artisan press:page-cache clear` after deploying view or theme files, or set `PRESS_PAGE_CACHE=false`.
- Copy the bundled `plugins/contact-form` and `plugins/seo-sitemap` folders over yours (versions 1.2.0); they upgrade on the next request.
- Add the Laravel scheduler to cron for daily update checks: `* * * * * php artisan schedule:run`.

## 1.0.0 (2026-10-14)

PN Scripts Press grows from a blog starter into a small CMS with WordPress-style plugins and themes, and its own design.

### Added

- **CMS:** pages built from blocks (hero, text, image, feature list, quote, call to action, latest posts), child pages, drafts and scheduled publishing, page templates, a chosen home page, SEO fields, and revisions you can restore.
- **Menus:** main and footer locations with nested dropdowns (three levels by default, `press.menus.max_depth`). Dropdowns work without JavaScript and by keyboard: Enter/Space, Escape, arrow keys, Home/End.
- **Media library** with alt text and captions; image blocks get a picker with thumbnails, search, a browse grid and upload in place.
- **Settings** (site name, tagline, footer, home page, posts per page), **Users** and roles with capabilities.
- **Plugins:** `plugin.json`, own PSR-4 autoloading, service provider, views, migrations, a route file that works with `php artisan route:cache`, and admin screens through a Filament plugin class (resources, pages, navigation). Install from a `.zip` or folder, activate, deactivate, uninstall, or update (new migrations run once). Actions and filters (`add_action`, `apply_filters`, `@hook`).
- **Contact Form** plugin with a *Messages* inbox: read, mark handled, delete, export to CSV. **SEO Sitemap** plugin.
- **Themes:** `theme.json`, view overrides, settings as CSS variables, templates, preview, `.zip` install. Folio (default) and Nocturne.
- Zip package checks: size, entry count, ratio, traversal, symlinks, hidden files, file types.
- `press:plugin` and `press:theme` Artisan commands.
- Guides: `docs/plugins.md`, `docs/themes.md`.

### Changed

- New PN Scripts Press design: paper, ink and teal, Fraunces headings, new mark and admin brand.
- A fresh install is called "PN Scripts Press" until the site name is set in Settings (Laravel's stock name and this kit's earlier names count as unset).
- Filament 5.10.2 (fixes GHSA-7m6h-rg42-m449), Laravel 13.36, Livewire 4.4.7. `ext-zip` is required.

### Fixed

- Dropdowns in the main menu are no longer clipped by a scrolling menu bar.
