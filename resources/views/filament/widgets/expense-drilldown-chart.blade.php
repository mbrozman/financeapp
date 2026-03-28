<div>
<div wire:key="expense-drilldown-{{ $period }}" class="h-full">
    <div class="flex items-center justify-between mb-4 gap-4">
        <div class="flex items-center gap-3">
            <div class="text-sm font-semibold text-gray-900 dark:text-white">Výdavky podľa kategórií</div>
            <div class="text-xs text-gray-500">{{ $periodLabel }}</div>
        </div>
        <div class="flex items-center gap-3">
            <select wire:model.live="period" class="text-xs bg-white border-gray-300 rounded-lg shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white">
                @foreach($periodOptions as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @php
        $chartSubLabels = $chartData['chart']['labels'] ?? [];
        $chartSubValues = $chartData['chart']['values'] ?? [];
        $chartSubColors = $chartData['chart']['colors'] ?? [];
        
        $legends = $chartData['legend'] ?? [];

        $chartPayload = [
            'labels' => $chartSubLabels, 
            'datasets' => [
                [
                    'data' => $chartSubValues,
                    'backgroundColor' => $chartSubColors,
                    'borderWidth' => 0,
                    'hoverOffset' => 10,
                ],
            ],
        ];

        $totalParent = array_sum(array_column($legends, 'total'));
    @endphp

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 h-[360px]">
        <div class="md:col-span-2 relative">
            @if($totalParent <= 0)
                <div class="h-full flex items-center justify-center text-sm text-gray-500">
                    Žiadne výdavky v tomto období
                </div>
            @else
                <div
                    x-load
                    x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('chart', 'filament/widgets') }}"
                    x-data="chart({
                        cachedData: @js($chartPayload),
                        options: expenseDrilldownOptions(),
                        type: 'doughnut'
                    })"
                    class="h-full"
                >
                    <canvas x-ref="canvas" class="w-full h-full"></canvas>
                    <span x-ref="backgroundColorElement" class="text-gray-100 dark:text-gray-800"></span>
                    <span x-ref="borderColorElement" class="text-gray-300 dark:text-gray-600"></span>
                    <span x-ref="gridColorElement" class="text-gray-200 dark:text-gray-800"></span>
                    <span x-ref="textColorElement" class="text-gray-500 dark:text-gray-400"></span>
                    
                    <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                        <div class="text-center">
                            <div class="text-xs text-gray-500">Spolu</div>
                            <div class="text-xl font-black text-gray-900 dark:text-white">
                                {{ number_format($totalParent, 2, ',', ' ') }} €
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <div class="h-full overflow-auto pr-2">
            <div class="text-xs font-semibold text-gray-500 mb-3">Hlavné kategórie</div>
            <div class="space-y-2">
                @foreach($legends as $legend)
                    @php
                        $value = $legend['total'] ?? 0;
                        $label = $legend['name'] ?? '';
                        $color = $legend['color'] ?? '#94a3b8';
                        $pct = $totalParent > 0 ? ($value / $totalParent) * 100 : 0;
                    @endphp
                    <div class="flex items-center justify-between text-xs">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full" style="background-color: {{ $color }}"></span>
                            <span class="text-gray-700 dark:text-gray-200">{{ $label }}</span>
                        </div>
                        <div class="text-gray-500">
                            {{ number_format($pct, 1, ',', ' ') }}%
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

<script>
    function expenseDrilldownFormatAmount(value) {
        return new Intl.NumberFormat('sk-SK', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value) + ' €';
    }

    function expenseDrilldownOptions() {
        return {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '50%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (ctx) => {
                            const label = ctx.chart.data.labels[ctx.dataIndex] ?? '';
                            const value = ctx.raw || 0;
                            return `${label}: ${expenseDrilldownFormatAmount(value)}`;
                        },
                    },
                },
            },
        };
    }
</script>
</div>
