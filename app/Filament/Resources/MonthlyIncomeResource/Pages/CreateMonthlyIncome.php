<?php

namespace App\Filament\Resources\MonthlyIncomeResource\Pages;

use App\Filament\Resources\MonthlyIncomeResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateMonthlyIncome extends CreateRecord
{
    protected static string $resource = MonthlyIncomeResource::class;
}
