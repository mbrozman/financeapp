<x-filament-panels::page>
    <div class="space-y-6 -mt-4">
        {{-- 1. KPI Karty: Likvidita (bankové účty, hotovosť) --}}
        <div>
            @livewire(\App\Filament\Widgets\NetWorthOverview::class)
        </div>

        {{-- 2. Finančný Cockpit: Piliere --}}
        <div>
            @livewire(\App\Filament\Widgets\PillarPerformanceWidget::class)
        </div>

        {{-- 3. Rezervný fond: Progress --}}
        <div>
            @livewire(\App\Filament\Widgets\ReserveFundWidget::class)
        </div>

        {{-- 2. Grafy v mriežke pre úsporu miesta --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            {{-- Graf: Mesačný cashflow --}}
            <div class="bg-white dark:bg-gray-800 p-2 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                @livewire(\App\Filament\Widgets\MonthlyCashflowChart::class)
            </div>

            {{-- Graf: Rozdelenie výdavkov (Treemap) --}}
            <div class="bg-white dark:bg-gray-800 p-2 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                @livewire(\App\Filament\Widgets\CategoryTreemapChart::class)
            </div>
        </div>

        {{-- 3. Graf: Mesačný rast (Realita vs. Plán) --}}
        <div class="bg-white dark:bg-gray-800 p-2 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            @livewire(\App\Filament\Widgets\NetWorthRealityVsPlanChart::class)
        </div>
    </div>
</x-filament-panels::page>
