# Changelog

All notable changes to PN Scripts Press. Dates are in YYYY-MM-DD.

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
