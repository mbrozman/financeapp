<div>
<x-filament-widgets::widget>
    <div wire:key="monthly-cashflow-chart-{{ time() }}" class="h-full">
        <x-filament::section>
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-base font-semibold leading-6 text-gray-900 dark:text-white">
                    {{ $heading }}
                </h2>

                <div class="flex items-center gap-2">
                    <x-filament::icon-button
                        wire:click="mountAction('zoom')"
                        icon="heroicon-m-magnifying-glass-plus"
                        color="gray"
                        label="Zväčšiť"
                        tooltip="Zväčšiť graf"
                    />
                </div>
            </div>

            <div style="height: 300px;">
                <div
                    x-load
                    x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('chart', 'filament/widgets') }}"
                    x-data="chart({
                        cachedData: @js($data),
                        options: monthlyCashflowOptions(),
                        type: 'bar'
                    })"
                    class="h-full w-full"
                >
                    <canvas x-ref="canvas" class="w-full h-full"></canvas>
                </div>
            </div>
        </x-filament::section>
    </div>
    
    <x-filament-actions::modals />
</x-filament-widgets::widget>

<script>
    function monthlyCashflowOptions() {
        return {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (ctx) => {
                            const label = ctx.dataset.label || '';
                            const value = ctx.raw || 0;
                            return `${label}: ${new Intl.NumberFormat('sk-SK').format(value)} €`;
                        },
                    },
                },
            },
            scales: {
                x: { stacked: false },
                y: {
                    stacked: false,
                    ticks: {
                        callback: (value) => new Intl.NumberFormat('sk-SK').format(value) + ' €',
                    },
                },
            },
        };
    }
</script>
</div>
