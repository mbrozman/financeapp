@php
    $record = $getRecord();
    $state = (float) $getState(); // Reálne percentá (napr. 120)
    $displayWidth = min($state, 100); // Šírka baru pre CSS (max 100%)
    $type = $record->type ?? 'saving';
    
    // Dynamická farba podľa typu a stavu
    if ($type === 'debt') {
        // PRE DLHY: Viac je horšie (červená pri prešvihu)
        $color = '#22c55e'; // Zelená (splácame)
        if ($state > 80 && $state <= 100) {
            $color = '#f59e0b'; // Oranžová
        } elseif ($state > 100) {
            $color = '#ef4444'; // Červená (prekročený dlh?)
        }
    } else {
        // PRE SPORENIE: Viac je lepšie
        $color = '#3b82f6'; // Modrá (priebeh sporenia)
        if ($state >= 100) {
            $color = '#10b981'; // Sýto zelená (dosiahnutý cieľ)
        }
    }
@endphp

<div class="flex items-center w-full min-w-[120px] gap-x-2 px-2">
    <div class="flex-1 h-3 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden border border-gray-300/10">
        <div 
            class="h-full rounded-full transition-all duration-500 {{ ($state >= 100) ? 'animate-pulse' : '' }}" 
            style="width: {{ $displayWidth }}%; background-color: {{ $color }};"
        ></div>
    </div>

    <span class="text-xs font-bold tabular-nums w-12 {{ ($state >= 100 && $type === 'saving') ? 'text-success-600' : ($state > 100 ? 'text-danger-600' : 'text-gray-600') }}">
        {{ number_format($state, 0) }}%
    </span>
</div>