<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    @php
        $colors = \App\Models\Category::getPremiumPalette();
        $statePath = $field->getStatePath();
    @endphp

    <div
        x-data="{
            state: $wire.entangle('{{ $statePath }}'),
            colors: @js($colors)
        }"
        class="flex flex-wrap gap-3 mt-2"
    >
        @foreach ($colors as $color)
            <button
                type="button"
                x-on:click="state = '{{ $color }}'"
                class="w-8 h-8 rounded-full transition-all duration-200 transform hover:scale-110 focus:outline-none"
                :class="state === '{{ $color }}' ? 'ring-2 ring-offset-2 ring-primary-500 scale-110' : 'hover:ring-2 hover:ring-offset-1 hover:ring-gray-300'"
                style="background-color: {{ $color }};"
                title="{{ $color }}"
            >
                <span class="sr-only">{{ $color }}</span>
            </button>
        @endforeach
    </div>
</x-dynamic-component>
