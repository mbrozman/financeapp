<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class RoleServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        \Filament\Support\Facades\FilamentView::registerRenderHook(
            'panels::body.start',
            fn (): string => ''
        );

        \Filament\Facades\Filament::serving(function () {
            $user = auth()->user();

            if ($user && $user->is_superadmin) {
                // Skryjeme všetko, čo by inak registrovali iné Resources
                \Filament\Facades\Filament::registerNavigationGroups([
                    \Filament\Navigation\NavigationGroup::make('Systém'),
                ]);

                // Cez registerNavigationItems môžeme nanútiť len tie, ktoré chceme 
                // a Filament v core väčšinou beží na registrovaní v provideroch, 
                // takže najlepšia cesta ako plošne skryť ostatné položky okrem UserResource
                // je pre samotné Resources definovať zmenu nad `shouldRegisterNavigation` metodou.
            }
        });
    }
}
