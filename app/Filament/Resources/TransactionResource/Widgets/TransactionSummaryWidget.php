<?php

namespace App\Filament\Resources\TransactionResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Transaction;
use Livewire\Attributes\Url;
use Livewire\Attributes\On;
use Carbon\Carbon;

class TransactionSummaryWidget extends BaseWidget
{
    protected int | string | array $columnSpan = [
        'default' => 12,
        'md' => 8,
        'xl' => 9,
    ];

    #[Url(as: 'month')]
    public ?string $month = null;

    public function mount(): void
    {
        if (!$this->month) {
            $this->month = now()->format('Y-m');
        }
    }

    #[On('filterUpdated')]
    public function handleFilterUpdate($month): void
    {
        $this->month = $month;
    }

    protected function getStats(): array
    {
        $query = Transaction::where('user_id', auth()->id());

        if ($this->month) {
            $date = Carbon::parse($this->month . '-01');
            $query->whereMonth('transaction_date', $date->month)
                  ->whereYear('transaction_date', $date->year);
        }
        
        $income = (float) $query->clone()->where('type', 'income')->sum('amount');
        $expense = (float) $query->clone()->where('type', 'expense')->sum('amount');
        
        $balance = $income - abs($expense);
        
        return [
            Stat::make('Príjmy', number_format($income, 2, ',', ' ') . ' €')
                ->description('Celkové príjmy za obdobie')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
            Stat::make('Výdavky', number_format(abs($expense), 2, ',', ' ') . ' €')
                ->description('Celkové výdavky za obdobie')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('danger'),
            Stat::make('Bilancia', number_format($balance, 2, ',', ' ') . ' €')
                ->description($balance >= 0 ? 'Ste v pluse' : 'Ste v mínuse')
                ->color($balance >= 0 ? 'success' : 'danger'),
        ];
    }
}
