@php
    $state = (float) $getState();
    $color = $getRecord()->color;

    // Poistka pre farbu (ak by bola v DB neplatná)
    if (!preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color)) {
        $color = '#3b82f6';
    }
@endphp

<div class="flex items-center w-full min-w-[120px] gap-x-2 px-2">
    {{-- Kontajner baru --}}
    <div class="flex-1 h-3 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden border border-gray-300/10">
        {{-- Farebný progres --}}
        <div 
            class="h-full rounded-full transition-all duration-500 shadow-sm" 
            style="width: {{ $state }}%; background-color: {{ $color }};"
        ></div>
    </div>

    {{-- Textové percentá --}}
    <span class="text-xs font-bold tabular-nums text-gray-600 dark:text-gray-400 w-9">
        {{ number_format($state, 0) }}%
    </span>
</div>