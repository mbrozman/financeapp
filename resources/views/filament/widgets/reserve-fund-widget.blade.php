<div>
@php $r = $reserve; @endphp

@if(!$r)
    <div style="display:none"></div>
@else
<x-filament-widgets::widget>
    <x-filament::section>
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4" style="margin-bottom: 1.75rem;">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-amber-50 dark:bg-amber-950/30 border border-amber-100 dark:border-amber-900/30 rounded-xl">
                    <x-heroicon-o-shield-check class="w-5 h-5 text-amber-500" />
                </div>
                <div>
                    <h2 class="text-base font-extrabold text-gray-900 dark:text-white tracking-tight">Rezervný fond</h2>
                    <p class="text-xs text-gray-400 mt-0.5">
                        {{ $r['index'] }}. {{ $r['name'] }} · {{ $r['percentage'] }}% príjmu · Cieľ: {{ number_format($r['target'], 0, ',', ' ') }} €
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3">

                <div class="text-center bg-gray-50 dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-xl px-4 py-2">
                    <p class="text-[10px] font-bold uppercase text-gray-400">Do cieľa</p>
                    <p class="text-lg font-black text-gray-700 dark:text-gray-200">{{ $r['months_remaining'] }} mes.</p>
                </div>
            </div>
        </div>

        {{-- Progress bar --}}
        <div class="space-y-3">
            {{-- Amounts above bar --}}
            <div class="flex justify-between items-baseline">
                <span class="text-sm font-black text-gray-900 dark:text-white">
                    {{ number_format($r['saved'], 0, ',', ' ') }} €
                    <span class="text-xs font-normal text-gray-400 ml-1">nasporeného</span>
                </span>
                <span class="text-sm font-semibold text-amber-600">
                    {{ $r['progress'] }}% —
                    zostáva {{ number_format($r['remaining'], 0, ',', ' ') }} €
                </span>
            </div>

            {{-- Progress track --}}
            <div class="w-full h-4 bg-gray-100 dark:bg-gray-800 rounded-full overflow-hidden border border-gray-200 dark:border-gray-700">
                <div class="h-full rounded-full transition-all duration-1000"
                     style="width: {{ $r['progress'] }}%; background: linear-gradient(90deg, #fbbf24, #d97706);"></div>
            </div>

            {{-- 8 markers representing segments of the target --}}
            <div class="flex">
                @for($i = 1; $i <= 8; $i++)
                    <div class="flex-1 flex flex-col items-center">
                        <div class="w-px h-2 bg-gray-300 dark:bg-gray-700"></div>
                        <span class="text-[9px] text-gray-400 mt-0.5">{{ round(($r['target'] / 8) * $i, 0) }}</span>
                    </div>
                @endfor
            </div>
        </div>

        

        @if($r['progress'] >= 100)
        <div class="mt-4 flex items-center justify-center gap-2 bg-success-50 dark:bg-success-900/20 border border-success-200 dark:border-success-800 rounded-xl px-4 py-3">
            <x-heroicon-m-check-badge class="w-5 h-5 text-success-600" />
            <span class="text-sm font-bold text-success-700">🎉 Rezervný fond je plne naplnený!</span>
        </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
@endif
</div>
