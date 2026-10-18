# Languages (multilingual content)

PN Scripts Press 1.1 serves a site in several languages. Every page, post, category and menu belongs to one language; translations of the same item are linked, so visitors can switch language and search engines see `hreflang` links.

## Switch it on

In `.env`:

```dotenv
PRESS_LOCALES=en,bg          # the first one is the default language
# PRESS_DEFAULT_LOCALE=en    # optional: pick another default from the list
```

Then `php artisan config:clear` (and `php artisan route:cache` again if you cache routes — the language prefixes are routes).

- The default language lives at `/` (`/about`, `/blog`), every other one under its code (`/bg/za-nas`, `/bg/blog`).
- One code (`PRESS_LOCALES=en`, the default when the variable is missing) is a single-language site: no prefixes, no switcher, no hreflang. Sites upgraded from 1.0 stay that way until you add a language.
- Language names for the switcher and admin come from `config/press.php` → `locale_names`; add a line for a language that is not listed.
- The URL decides the language. Nothing is guessed from the browser or a cookie, so one address always shows one language (good for the page cache and for search engines).

## Writing translations

1. Write the page, post or category in the default language.
2. In its list (Content → Pages, Blog → Posts, Blog → Categories) press **Translate** and pick the language. A **draft** copy is created and linked to the original; the parent page and the post category point at their own translations when those exist.
3. Translate the text and the slug (each language has its own address, e.g. `/about` and `/bg/za-nas`), then publish.

The **Language** column shows each item's language and "Also in …" for its translations; filter the list by language. Slugs and page paths only have to be unique within one language. Top-level pages cannot use a language code (`bg`) as their slug.

## What each language has of its own

| | How |
|---|---|
| Pages, posts, categories | `locale` per item; lists, the blog, categories and the "Latest posts" block show only the current language |
| Menus | Appearance → Menus → **Language**. A menu for one language replaces the "Every language" menu of the same location there |
| Site name, tagline, footer text, home page | Settings → **Language: Български** (one section per extra language). Empty fields use the default language's value |
| Interface texts of the theme | `lang/bg.json` (front-end strings), `lang/bg/validation.php`, `lang/bg/pagination.php`. Add `lang/<code>.json` for another language |
| Dates | Written in the page language (`5 март 2026`) |

## For search engines

- `<html lang>` and `og:locale` follow the page language.
- Every page that exists in more than one language prints `<link rel="alternate" hreflang="…">` for each public translation plus `x-default` (the default language). Drafts and scheduled translations are not linked.
- The SEO Sitemap plugin (1.2.0) lists every language and adds `xhtml:link` alternates to translated items.

## In themes and plugins

```blade
@include('press.partials.language-switcher')   {{-- the switcher; override this view in a theme to restyle it --}}
<a href="{{ press_route('blog') }}">{{ __('Blog') }}</a>   {{-- /blog or /bg/blog --}}
<a href="{{ press_locale_url('/contact') }}">…</a>        {{-- /contact or /bg/contact --}}
<a href="{{ $post->url() }}">…</a>                        {{-- models know their language --}}
```

- `App\Press\I18n\Locales` (`app(Locales::class)`): `codes()`, `default()`, `current()`, `enabled()`, `url($path, $code)`, `alternates()`, `switcher()`, `setAlternates([...])` for a plugin's own pages.
- `Post::query()->inLocale()` (also `Page`, `Category`): the current language; `$model->translations()`, `->translation('bg')`, `->alternateUrls()`.
- `app(Settings::class)->localized('site.tagline')` reads a per-language setting (stored as `site.i18n.bg.tagline`).
- Plugin routes are not prefixed. A form posting to a plugin route can send `locale` (as the Contact Form block does) so validation messages come back in the page language.

## Limits

- The admin itself is in English.
- Translations are separate items: images, blocks and SEO fields are copied once by **Translate**, then edited per language.
- No automatic machine translation.
