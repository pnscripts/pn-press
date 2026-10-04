# PN Press

PN Press is a [PN Scripts](https://pnscripts.com) product ([product page](https://pnscripts.com/products/pn-press)). It was previously published as Laravel Blog CMS.

A small, working blog CMS starter kit: a public blog on the front, [Filament](https://filamentphp.com) at `/admin` for posts and categories.

It is meant to be cloned and customized, not used as a hosted product. Built with Laravel 13, Filament 5, Spatie Permission, Livewire 4, and Tailwind CSS 4.

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

Visit [http://127.0.0.1:8000](http://127.0.0.1:8000) for the public blog.

## Admin

- URL: `/admin`
- Email: `admin@example.com`
- Password: `password`

Change these credentials before any real deployment.

Filament login stays at `/admin`. Only users with the `admin` or `editor` role can open the panel.

## What is included

- Public blog index (`/`), post pages (`/blog/{slug}`), and category archives (`/category/{slug}`)
- Draft vs published visibility (drafts never appear on the public site)
- Filament 5 resources for posts and categories (title, auto slug, excerpt, rich body, status, publish date, featured image URL/path)
- Spatie roles: `admin` and `editor`
- Demo seed: 3 categories, 6 published posts, 1 draft
- PHPUnit coverage for public listing/show/archive and admin access
- Laravel Telescope (disabled by default; `require-dev` only) and Scramble for API docs if you add APIs later

## What is not included

- Comments
- Multi-tenancy
- Media library / image uploads beyond a featured-image string
- Newsletter, tags, SEO suite, or a public user registration flow
- Production-ready Telescope access (the `viewTelescope` allowlist is empty on purpose)

## Tests

```bash
php artisan test
```

## More from PN Scripts

- [PN Invoice](https://github.com/pnscripts/pn-invoice): free PHP library to write and validate EN 16931 e-invoices (UBL and CII).
- [Laravel and Filament upgrades and care](https://pnscripts.com/services/laravel-filament-care): fixed-price upgrades to Laravel 13 and Filament 5, and monthly care plans.
- All products: [pnscripts.com/products](https://pnscripts.com/products)

## License

MIT. Copyright 2026 Petar Nikolov / PN Scripts. See [LICENSE](LICENSE).
