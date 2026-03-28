<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    @php
        $statePath = $field->getStatePath();
        
        // baseColor je teraz odovzdaná cez viewData z Resource
        $shades = \App\Models\Category::getShadesForBase($baseColor ?? '#94a3b8');
        $labels = [
            'Vibrant',
            'Professional',
            'Pastel',
            'Deep',
            'Electric'
        ];
    @endphp

    <div
        x-data="{
            state: $wire.entangle('{{ $statePath }}')
        }"
        class="flex flex-wrap gap-4 mt-3"
    >
        @foreach ($shades as $i => $shade)
                <div
                    x-on:click="$wire.set('{{ $statePath }}', '{{ $shade }}')"
                    class="w-8 h-8 rounded-full cursor-pointer transition-all duration-200 border-2"
                    :class="$wire.get('{{ $statePath }}') === '{{ $shade }}' ? 'ring-2 ring-offset-2 ring-primary-500 border-white scale-110 shadow-lg' : 'border-transparent hover:scale-105'"
                    style="background-color: {{ $shade }};"
                ></div>
            @endforeach
    </div>
</x-dynamic-component>
