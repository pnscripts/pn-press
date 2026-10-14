<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('PN Scripts Press')
            ->brandLogo(fn () => view('filament.brand'))
            ->brandLogoHeight('2rem')
            ->favicon(fn (): string => asset('favicon.svg'))
            // PN Scripts Press brand: press teal on warm stone. Filament picks accessible text shades for each step.
            ->colors([
                'primary' => [
                    50 => '#effaf8', 100 => '#d5f1ec', 200 => '#ade2d9', 300 => '#79cdc0', 400 => '#3fae9f', 500 => '#1a8f82',
                    600 => '#0f6e66', 700 => '#0d5a54', 800 => '#0e4844', 900 => '#0f3b37', 950 => '#052421',
                ],
                'gray' => Color::Stone,
                'info' => Color::Sky,
                'warning' => Color::hex('#d99a1e'),
            ])
            ->font('Inter', provider: \Filament\FontProviders\BunnyFontProvider::class)
            ->sidebarCollapsibleOnDesktop()
            // The wordmark uses the Press display face.
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => '<link href="https://fonts.bunny.net/css?family=fraunces:600" rel="stylesheet">')
            ->navigationGroups(['Content', 'Blog', 'Appearance', 'Extend', 'Administration'])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            // Dashboard widgets live in app/Filament/Widgets (stats, recent posts, drafts, posts by category).
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                class_exists(PreventRequestForgery::class)
                    ? PreventRequestForgery::class
                    : VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
