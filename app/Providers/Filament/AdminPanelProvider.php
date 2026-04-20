<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
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
            ->brandName('Vault')
            ->sidebarCollapsibleOnDesktop()
            ->globalSearch(false)
            ->brandLogo(asset('images/logo.svg'))
            ->brandLogoHeight('52px')
            ->favicon(asset('images/logo.svg'))
            ->profile()
            ->colors([
                'primary' => '#fbcc01',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                \App\Filament\Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([

            ])
            ->renderHook(
                \Filament\View\PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn (): string => \Illuminate\Support\Facades\Blade::render('@livewire(\'global-currency-switcher\')'),
            )
            ->renderHook(
                \Filament\View\PanelsRenderHook::HEAD_END,
                fn (): string => \Illuminate\Support\Facades\Blade::render('
                    <style>
                        /* Presun šípky na spodok */
                        .fi-sidebar-collapse-button {
                            position: absolute;
                            bottom: 10px;
                            left: 50%;
                            transform: translateX(-50%);
                            z-index: 50;
                        }
                        
                        /* Logo neschovávať v collapsed režime */
                        .fi-sidebar-header {
                            display: flex !important;
                            justify-content: center;
                            padding-top: 15px !important;
                        }

                        .fi-sidebar-header a::after {
                            content: "Vault";
                            font-weight: 700;
                            font-size: 1.8rem;
                            margin-left: 12px;
                            color: #1a3e10;
                            display: inline-block;
                            vertical-align: middle;
                            transition: all 0.2s;
                        }

                        /* Schovať text Vault v collapsed režime, ale nechať logo */
                        .fi-main-sidebar-open-desktop .fi-sidebar-header a::after,
                        aside:not(.fi-sidebar-open-desktop) .fi-sidebar-header a::after {
                            display: none;
                        }

                        .fi-sidebar-header a {
                            display: flex;
                            align-items: center;
                            text-decoration: none;
                        }
                        
                        /* Odstrániť pôvodné umiestnenie tlačidla v headeri */
                        .fi-sidebar-header .fi-sidebar-collapse-button {
                            position: absolute !important;
                        }
                    </style>
                '),
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
