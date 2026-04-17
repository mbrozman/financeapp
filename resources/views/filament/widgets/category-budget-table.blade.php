<div class="fi-wi-widget" wire:ignore.self>
    <div class="rounded-2xl overflow-hidden bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">

        {{-- ─── HEADER ────────────────────────────────────────────────── --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-800">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-primary-50 dark:bg-primary-950 flex items-center justify-center shrink-0">
                    <x-heroicon-o-banknotes class="w-4 h-4 text-primary-600 dark:text-primary-400" />
                </div>
                <div>
                    <p class="text-sm font-bold text-gray-900 dark:text-white leading-none">Čerpanie rozpočtu</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                        Celkové výdavky:
                        <span class="font-semibold text-gray-600 dark:text-gray-300">{{ number_format($data['total'], 2, ',', ' ') }} €</span>
                    </p>
                </div>
            </div>
            <select wire:model.live="selectedPeriod"
                class="text-xs border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-lg px-3 py-1.5 font-semibold focus:ring-2 focus:ring-primary-500 focus:border-transparent cursor-pointer">
                @foreach($periodOptions as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        {{-- ─── EMPTY STATE ────────────────────────────────────────────── --}}
        @if(empty($data['pillars']))
            <div class="py-16 flex flex-col items-center justify-center gap-3 text-gray-300 dark:text-gray-600">
                <x-heroicon-o-banknotes class="w-10 h-10" />
                <span class="text-sm font-medium">Žiadne výdavky v tomto období</span>
            </div>

        {{-- ─── PILLARS GRID ────────────────────────────────────────────── --}}
        @else
            {{-- Using inline style grid so Filament wrappers don't override Tailwind responsive --}}
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));">
                @foreach($data['pillars'] as $pName => $pData)
                    @php
                        $pillarColor = $pData['color'] ?? '#94a3b8';
                    @endphp

                    <div style="border-right: 1px solid rgba(0,0,0,0.05); border-bottom: 1px solid rgba(0,0,0,0.05);"
                        class="flex flex-col dark:border-gray-800">

                        {{-- Pillar color header stripe --}}
                        <div class="px-6 py-4 flex items-center justify-between"
                            style="background: linear-gradient(135deg, {{ $pillarColor }}18, {{ $pillarColor }}05);">
                            <div class="flex items-center gap-2.5">
                                <span class="w-2.5 h-2.5 rounded-full shrink-0"
                                    style="background-color: {{ $pillarColor }}; box-shadow: 0 0 0 3px {{ $pillarColor }}33;">
                                </span>
                                <span class="text-[10px] font-black uppercase tracking-[0.15em] text-gray-500 dark:text-gray-400">
                                    {{ $pName }}
                                </span>
                            </div>
                            <span class="text-xl font-black text-gray-900 dark:text-white tabular-nums"
                                style="color: {{ $pillarColor }}dd">
                                {{ number_format($pData['amount'], 0, ',', ' ') }} €
                            </span>
                        </div>

                        {{-- Thin accent line --}}
                        <div class="h-px mx-6" style="background: linear-gradient(to right, {{ $pillarColor }}66, transparent)"></div>

                        {{-- Categories list --}}
                        <div class="p-4 flex flex-col gap-2">
                            @foreach($pData['parent_categories'] as $cName => $cData)
                                @php
                                    $isSavings  = $pData['is_savings'] ?? false;
                                    $hasLimit   = $cData['limit'] > 0;
                                    $pct        = $cData['percent'];
                                    $overBudget = $pct >= 101;
                                    $nearLimit  = $pct >= 85 && !$overBudget;

                                    if ($isSavings) {
                                        // LOGIKA PRE SPORENIE: Nad 100% = Super (Zelená), Málo = Červená
                                        $barHex = $pct >= 100 ? '#22c55e' : ($pct >= 50 ? '#f59e0b' : '#ef4444');
                                        $pctClass = $pct >= 100
                                            ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400'
                                            : ($pct >= 50
                                                ? 'bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400'
                                                : 'bg-red-50 dark:bg-red-950/40 text-red-600 dark:text-red-400');
                                        
                                        $amountColorClass = $pct >= 100 ? 'text-emerald-500' : 'text-gray-900 dark:text-white';
                                    } else {
                                        // LOGIKA PRE VÝDAVKY: Nad 100% = Zle (Červená)
                                        $barHex = $overBudget ? '#ef4444' : ($nearLimit ? '#f59e0b' : '#22c55e');
                                        $pctClass = $overBudget
                                            ? 'bg-red-50 dark:bg-red-950/40 text-red-600 dark:text-red-400'
                                            : ($nearLimit
                                                ? 'bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400'
                                                : 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400');
                                        
                                        $amountColorClass = $overBudget ? 'text-red-500' : 'text-gray-900 dark:text-white';
                                    }

                                    $barWidth = min($pct, 100);
                                @endphp

                                <div class="group rounded-xl px-4 py-3 transition-colors hover:bg-gray-50 dark:hover:bg-gray-800"
                                    style="border: 1px solid rgba(0,0,0,0.04);">

                                    {{-- Category name + amount --}}
                                    <div class="flex items-start justify-between gap-2">
                                        <span class="text-[13px] font-bold text-gray-800 dark:text-gray-200 leading-snug">{{ $cName }}</span>
                                        <div class="text-right shrink-0">
                                            <div class="text-sm font-extrabold tabular-nums leading-none {{ $amountColorClass }}">
                                                {{ number_format($cData['amount'], 0, ',', ' ') }} €
                                            </div>
                                            @if($hasLimit)
                                                <div class="text-[10px] text-gray-400 tabular-nums mt-0.5">
                                                    z {{ number_format($cData['limit'], 0, ',', ' ') }} €
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Progress bar + badge --}}
                                    @if($hasLimit)
                                        <div class="flex items-center gap-2 mt-2">
                                            <div class="flex-1 h-1.5 rounded-full overflow-hidden bg-gray-100 dark:bg-gray-700">
                                                <div class="h-full rounded-full transition-all duration-700"
                                                    style="width: {{ $barWidth }}%; background-color: {{ $barHex }};">
                                                </div>
                                            </div>
                                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded {{ $pctClass }} tabular-nums">
                                                {{ round($pct) }}%
                                            </span>
                                        </div>
                                    @endif

                                    {{-- Subcategories --}}
                                    @if(!empty($cData['subcategories']))
                                        <div class="mt-2.5 pt-2 border-t border-gray-100 dark:border-gray-700 space-y-1.5">
                                            @foreach($cData['subcategories'] as $sName => $sData)
                                                @php 
                                                    $sOver = $sData['limit'] > 0 && $sData['percent'] >= 101; 
                                                    $sAmountColor = $isSavings 
                                                        ? ($sData['percent'] >= 100 ? 'text-emerald-500' : 'text-gray-700 dark:text-gray-300')
                                                        : ($sOver ? 'text-red-500' : 'text-gray-700 dark:text-gray-300');
                                                @endphp
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="text-[11px] text-gray-500 dark:text-gray-400 truncate">{{ $sName }}</span>
                                                    <div class="flex items-center gap-1.5 shrink-0">
                                                        <span class="text-[11px] font-semibold tabular-nums {{ $sAmountColor }}">
                                                            {{ number_format($sData['amount'], 0, ',', ' ') }} €
                                                        </span>
                                                        @if($sData['limit'] > 0)
                                                            <span class="text-[10px] text-gray-400 tabular-nums">
                                                                / {{ number_format($sData['limit'], 0, ',', ' ') }} €
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif

                                </div>
                            @endforeach
                        </div>

                    </div>
                @endforeach
            </div>
        @endif

    </div>
</div>
