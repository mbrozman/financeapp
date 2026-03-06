@php
    $state = (float) $getState(); // Reálne percentá (napr. 120)
    $displayWidth = min($state, 100); // Šírka baru pre CSS (max 100%)
    
    // Dynamická farba podľa čerpania
    $color = '#22c55e'; // Zelená (do 80%)
    if ($state > 80 && $state <= 100) {
        $color = '#f59e0b'; // Oranžová (varovanie)
    } elseif ($state > 100) {
        $color = '#ef4444'; // Červená (prekročené)
    }
@endphp

<div class="flex items-center w-full min-w-[120px] gap-x-2 px-2">
    <div class="flex-1 h-3 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden border border-gray-300/10">
        <div 
            class="h-full rounded-full transition-all duration-500 {{ $state > 100 ? 'animate-pulse' : '' }}" 
            style="width: {{ $displayWidth }}%; background-color: {{ $color }};"
        ></div>
    </div>

    <span class="text-xs font-bold tabular-nums w-12 {{ $state > 100 ? 'text-red-600 animate-bounce' : 'text-gray-600' }}">
        {{ number_format($state, 0) }}%
    </span>
</div>