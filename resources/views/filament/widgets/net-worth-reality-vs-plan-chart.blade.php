<x-filament-widgets::widget>
    <div wire:key="net-worth-chart-{{ $this->filter }}-{{ time() }}" class="h-full">
        <x-filament::section>
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-base font-semibold leading-6 text-gray-900 dark:text-white">
                    {{ $heading }}
                </h2>

                <div class="flex items-center gap-3">
                    @if($filters)
                        <select wire:model.live="filter" class="text-xs bg-white border-gray-300 rounded-lg shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white">
                            @foreach($filters as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    @endif

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
                        options: netWorthRealityOptions(),
                        type: 'line'
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
    function netWorthRealityOptions() {
        return {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom',
                },
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
                y: {
                    ticks: {
                        callback: (value) => new Intl.NumberFormat('sk-SK').format(value) + ' €',
                    },
                },
            },
        };
    }
</script>
