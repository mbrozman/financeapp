<?php

namespace App\Filament\Resources\TransactionResource\Widgets;

use Filament\Widgets\Widget;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Form;
use Filament\Forms\Components\DatePicker;
use Livewire\Attributes\Url;
use Livewire\Attributes\On;

class TransactionMonthFilter extends Widget implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.resources.transaction-resource.widgets.transaction-month-filter';

    protected int | string | array $columnSpan = [
        'default' => 12,
        'md' => 4,
        'xl' => 3,
    ];

    #[Url(as: 'month')]
    public ?string $month = null;

    public function mount(): void
    {
        if (!$this->month) {
            $this->month = now()->format('Y-m');
        }

        $this->form->fill([
            'month' => $this->month,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                \Filament\Forms\Components\Select::make('month')
                    ->label('Mesiac')
                    ->hiddenLabel()
                    ->options($this->getMonthOptions())
                    ->selectablePlaceholder(false)
                    ->live()
                    ->afterStateUpdated(function ($state) {
                        if ($state) {
                            $this->month = $state;
                            $this->dispatch('filterUpdated', month: $this->month);
                        }
                    })
                    ->prefixIcon('heroicon-m-calendar-days')
                    ->extraAttributes(['class' => 'min-w-[180px]'])
            ]);
    }

    protected function getMonthOptions(): array
    {
        $options = [];
        $start = now()->subMonths(12)->startOfMonth();
        $end = now()->addMonths(6)->startOfMonth();

        $current = clone $start;
        while ($current <= $end) {
            $key = $current->format('Y-m');
            // Formátovanie názvu mesiaca v slovenčine cez Carbon
            $label = $current->translatedFormat('F Y');
            $options[$key] = ucfirst($label);
            $current->addMonth();
        }

        return array_reverse($options, true);
    }

    #[On('filterUpdated')]
    public function handleFilterUpdate($month): void
    {
        // Táto metóda zabezpečí, že ak niekto iný zmení filter, tento widget sa aktualizuje
        if ($this->month !== $month) {
            $this->month = $month;
            $this->form->fill([
                'month' => $month,
            ]);
        }
    }

    public function resetFilter(): void
    {
        $this->month = now()->format('Y-m');
        $this->form->fill(['month' => $this->month]);
        $this->dispatch('filterUpdated', month: $this->month);
    }
}
