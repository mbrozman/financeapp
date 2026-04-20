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
            ->brandLogo(fn () => view('filament.components.brand'))
            ->sidebarCollapsibleOnDesktop()
            ->globalSearch(false)
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
                \Filament\View\PanelsRenderHook::SIDEBAR_FOOTER,
                fn (): string => \Illuminate\Support\Facades\Blade::render('
                    <div class="flex items-center justify-center p-4 border-t border-gray-100 dark:border-white/5">
                        <button 
                            x-on:click="window.dispatchEvent(new CustomEvent(\'toggle-sidebar\'))"
                            type="button"
                            class="flex items-center justify-center p-2 text-gray-500 rounded-lg hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5 transition-colors"
                        >
                            <x-heroicon-m-chevron-double-left class="w-6 h-6 transition-transform" x-bind:class="$store.sidebar.isOpen ? \'\' : \'rotate-180\'" />
                        </button>
                    </div>
                '),
            )
            ->renderHook(
                \Filament\View\PanelsRenderHook::HEAD_END,
                fn (): string => \Illuminate\Support\Facades\Blade::render('
                    <style>
                        /* Skryť pôvodné ovládacie prvky (šípky hore) */
                        .fi-topbar .fi-sidebar-collapse-button, 
                        .fi-sidebar-header .fi-sidebar-collapse-button,
                        header button[x-on*="sidebar"] {
                            display: none !important;
                        }
                        
                        /* Tmavší a oddelený sidebar */
                        .fi-sidebar {
                            background-color: #f8faf8 !important;
                            border-right: 1px solid #e2e8e2 !important;
                            position: relative !important;
                        }
                        .dark .fi-sidebar {
                            background-color: #0d130d !important;
                            border-right: 1px solid rgba(255, 255, 255, 0.05) !important;
                        }

                        /* Fix loga a názvu VAULTY */
                        .fi-sidebar-header {
                            padding: 1.25rem !important;
                            display: flex !important;
                            justify-content: flex-start !important;
                            align-items: center !important;
                        }

                        .fi-sidebar-header a {
                            display: flex !important;
                            align-items: center !important;
                            gap: 0.75rem !important;
                            text-decoration: none !important;
                        }

                        .vaulty-brand-name {
                            display: inline-block !important;
                            opacity: 1 !important;
                            visibility: visible !important;
                        }
                        
                        /* Centrovanie loga v zbalenom stave */
                        aside:not(.fi-main-sidebar-open-desktop) .vaulty-brand-name {
                            display: none !important;
                        }
                        aside:not(.fi-main-sidebar-open-desktop) .fi-sidebar-header {
                            justify-content: center !important;
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
