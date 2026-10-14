# Writing a PN Scripts Press theme

A theme is a folder with a `theme.json` and Blade views that replace the defaults. See `themes/folio` and `themes/nocturne`.

```
my-theme/
├── theme.json
├── screenshot.svg              (shown in Appearance → Themes)
├── assets/                     (served at /theme-assets/my-theme/assets/...)
│   └── theme.css
└── views/                      (overrides; same names as resources/views)
    ├── layouts/app.blade.php
    ├── press/partials/header.blade.php
    ├── press/blocks/hero.blade.php
    └── pages/templates/wide.blade.php
```

## theme.json

```json
{
    "slug": "my-theme",
    "name": "My Theme",
    "version": "1.0.0",
    "description": "One sentence.",
    "author": "You",
    "requires": { "press": ">=1.0" },
    "screenshot": "screenshot.svg",
    "stylesheet": "assets/theme.css",
    "templates": { "wide": "Wide page" },
    "settings": [
        { "key": "accent", "label": "Accent colour", "type": "color", "default": "#0f6e66" },
        { "key": "display_font", "label": "Headings", "type": "select", "default": "serif",
          "options": { "serif": "Serif", "sans": "Sans" } },
        { "key": "show_tagline", "label": "Show the tagline", "type": "toggle", "default": true },
        { "key": "footer_note", "label": "Footer note", "type": "text", "default": "" }
    ]
}
```

## Overriding views

The active theme's `views/` folder is searched before `resources/views`, so copy any default view into the same path in your theme and change it. Views you do not copy keep working. Useful ones:

| View | What it is |
|------|------------|
| `layouts/app.blade.php` | the HTML shell (keep `@hook('head')`, `@hook('body.end')` and `{{ $themes->styleTag() }}`) |
| `press/partials/header.blade.php`, `footer.blade.php`, `nav-item.blade.php` | masthead, footer, menu items |
| `press/blocks/<block>.blade.php` | one view per block (`hero`, `text`, `image`, `features`, `quote`, `call-to-action`, `latest-posts`) |
| `pages/show.blade.php` | a page; `pages/templates/<template>.blade.php` for each template you declare |
| `posts/index.blade.php`, `posts/show.blade.php`, `categories/show.blade.php` | the blog |
| `components/blog/post-card.blade.php` | a post in a list |

Menus: `app(\App\Press\Menus::class)->tree('primary')` returns `label`, `url`, `new_tab`, `active` and `children` for each item.

## Settings

Settings appear under **Customize** on the Themes screen. Values are checked against their type (colours must be `#rgb` or `#rrggbb`, selects must be one of the options) before they are saved or printed.

- `color` settings become CSS variables: `accent` → `--press-accent`.
- `select` and `toggle` settings become attributes on `<html>`: `display_font` → `data-press-display-font="sans"`, toggles are `on` / `off`.
- Read any value in Blade with `app(\App\Press\Themes\ThemeManager::class)->setting('footer_note')`.

The core styles read these variables: `--press-paper`, `--press-surface`, `--press-ink`, `--press-muted`, `--press-rule`, `--press-accent`, `--press-accent-ink`, `--press-highlight`, `--press-font-display`. A dark theme can set them all in its stylesheet (see Nocturne).

## Styles

Tailwind classes in bundled themes (`themes/*/views`) are compiled with the core CSS by `npm run build`. A theme installed from a zip on a live site is not rebuilt, so put its own styles in its stylesheet (`assets/theme.css`) or use the core utility classes and `press-*` components (`press-container`, `press-button`, `press-eyebrow`, `press-prose`).

## Preview and activate

In **Appearance → Themes**: *Preview* opens the site with `?preview_theme=<slug>` (only for users who can manage themes; visitors keep the active theme), *Activate* switches it for everyone. From the command line:

```bash
php artisan press:theme list
php artisan press:theme install path/to/my-theme.zip   # or a folder; --replace to update
php artisan press:theme activate my-theme
php artisan press:theme delete my-theme
```

## Packaging

Zip the theme folder. The same checks as for plugins apply, with one extra rule: **PHP is only allowed as Blade views (`*.blade.php`)**, so a theme cannot ship service providers or other PHP classes. Blade views still execute on the server, so install only themes you trust.

Assets are served only from `assets/` (plus the screenshot), only for static file types, with `X-Content-Type-Options: nosniff` and a restrictive Content-Security-Policy.
