<x-filament-widgets::widget>
    <div class="mb-6 text-center">
        <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-2">Benchmarking</h3>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left border-separate border-spacing-y-2">
            <thead>
                <tr class="text-gray-400 uppercase text-[9px] font-black tracking-widest">
                    <th class="pb-2 pl-4">Názov / Index</th>
                    <th class="pb-2 text-center">1D</th>
                    <th class="pb-2 text-center">1W</th>
                    <th class="pb-1 text-center">1M</th>
                    <th class="pb-2 text-center">3M</th>
                    <th class="pb-2 text-center">6M</th>
                    <th class="pb-2 text-center font-bold text-primary-500">YTD</th>
                    <th class="pb-2 text-center">1Y</th>
                </tr>
            </thead>
            <tbody class="text-xs">
                @foreach($this->getComparisonData() as $row)
                <tr class="group">
                    <td class="py-3 px-4 bg-gray-50/50 dark:bg-gray-800/50 rounded-l-lg border-l border-t border-b border-gray-100 dark:border-gray-700 font-bold text-gray-700 dark:text-gray-200">
                        {{ $row['label'] }}
                        <span class="block text-[9px] uppercase tracking-tighter text-gray-400 font-medium">{{ $row['ticker'] === 'portfolio' ? 'Vaše úspory' : $row['ticker'] }}</span>
                    </td>
                    
                    @foreach(['1D', '1W', '1M', '3M', '6M', 'YTD', '1Y'] as $period)
                        @php 
                            $val = $row['data'][$period] ?? null; 
                            $colorClass = $val > 0 ? 'text-emerald-500 bg-emerald-50/50 dark:bg-emerald-500/10' : ($val < 0 ? 'text-rose-500 bg-rose-50/50 dark:bg-rose-500/10' : 'text-gray-400 bg-gray-50 dark:bg-gray-800/50');
                        @endphp
                        <td class="py-3 text-center bg-gray-50/50 dark:bg-gray-800/50 border-t border-b border-gray-100 dark:border-gray-700 {{ $period === '1Y' ? 'rounded-r-lg border-r' : '' }}">
                            @if($val !== null)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md font-bold text-[10px] {{ $colorClass }}">
                                    {{ $val > 0 ? '+' : '' }}{{ number_format($val, 2) }}%
                                </span>
                            @else
                                <span class="text-[9px] font-bold text-gray-300">N/A</span>
                            @endif
                        </td>
                    @endforeach
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    
    <div class="mt-6 text-center text-[9px] text-gray-400 font-medium uppercase tracking-widest">
        Dáta sú aktualizované podľa trhových cien • zohľadňuje čistý trhový výnos
    </div>
</x-filament-widgets::widget>
