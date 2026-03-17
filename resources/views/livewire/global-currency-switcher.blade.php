<div class="flex items-center space-x-2 me-4">
    <x-filament::input.wrapper>
        <x-filament::input.select wire:model.live="currencyId" class="text-sm font-medium focus:ring-primary-500">
            @foreach($currencies as $currency)
                <option value="{{ $currency->id }}">{{ $currency->code }}</option>
            @endforeach
        </x-filament::input.select>
    </x-filament::input.wrapper>
</div>
