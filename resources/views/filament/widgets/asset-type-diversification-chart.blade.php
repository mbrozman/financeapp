@php
    $color = $this->getColor();
    $maxHeight = $this->getMaxHeight();
@endphp

<div
    @if ($pollingInterval = $this->getPollingInterval())
        wire:poll.{{ $pollingInterval }}="updateChartData"
    @endif
>
    <div
        @if (\Filament\Support\Facades\FilamentView::hasSpaMode())
            x-load="visible"
        @else
            x-load
        @endif
        x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('chart', 'filament/widgets') }}"
        wire:ignore
        x-data="chart({
                    cachedData: @js($this->getCachedData()),
                    options: @js($this->getOptions()),
                    type: @js($this->getType()),
                })"
        @class([
            match ($color) {
                'gray' => null,
                default => 'fi-color-custom',
            },
            is_string($color) ? "fi-color-{$color}" : null,
        ])
    >
        <canvas
            x-ref="canvas"
            @if ($maxHeight)
                style="max-height: {{ $maxHeight }}"
            @endif
        ></canvas>

        <span
            x-ref="backgroundColorElement"
            @class([
                match ($color) {
                    'gray' => 'text-gray-100 dark:text-gray-800',
                    default => 'text-custom-50 dark:text-custom-400/10',
                },
            ])
            @style([
                \Filament\Support\get_color_css_variables(
                    $color,
                    shades: [50, 400],
                    alias: 'widgets::chart-widget.background',
                ) => $color !== 'gray',
            ])
        ></span>

        <span
            x-ref="borderColorElement"
            @class([
                match ($color) {
                    'gray' => 'text-gray-400',
                    default => 'text-custom-500 dark:text-custom-400',
                },
            ])
            @style([
                \Filament\Support\get_color_css_variables(
                    $color,
                    shades: [400, 500],
                    alias: 'widgets::chart-widget.border',
                ) => $color !== 'gray',
            ])
        ></span>

        <span
            x-ref="gridColorElement"
            class="text-gray-200 dark:text-gray-800"
        ></span>

        <span
            x-ref="textColorElement"
            class="text-gray-500 dark:text-gray-400"
        ></span>
    </div>
</div>
