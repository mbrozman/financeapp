<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    @php
        $statePath = $field->getStatePath();
        $colors = \App\Models\FinancialPlanItem::getBaseColors();
    @endphp

    <div x-data="{ state: $wire.$entangle('{{ $statePath }}') }">
        <div class="flex flex-wrap gap-4 mt-2">
            @foreach ($colors as $hex => $label)
                <div 
                    x-on:click="state = '{{ $hex }}'"
                    class="w-10 h-10 rounded-full cursor-pointer transition-all duration-200 border-2 shadow-sm flex items-center justify-center group relative"
                    :class="state === '{{ $hex }}' ? 'ring-2 ring-offset-2 ring-primary-500 border-white scale-110 shadow-md' : 'border-transparent hover:scale-105 hover:border-gray-300'"
                    style="background-color: {{ $hex }};"
                    title="{{ $label }}"
                >
                    {{-- Tooltip / Label indicator on hover (optional) --}}
                    <div class="absolute -bottom-8 left-1/2 -translate-x-1/2 bg-gray-800 text-white text-[10px] px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none z-10">
                        {{ $label }}
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-dynamic-component>
