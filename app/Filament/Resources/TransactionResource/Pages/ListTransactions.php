<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use App\Models\Account;
use App\Models\Transaction;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\DB;

class ListTransactions extends ListRecords
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),

            // NAŠE NOVÉ TLAČIDLO PRE PREVOD
            Actions\Action::make('transfer')
                ->label('Interný prevod')
                ->icon('heroicon-m-arrows-right-left')
                ->color('info')
                ->form([
                    Forms\Components\Select::make('from_account_id')
                        ->label('Z účtu')
                        ->options(Account::pluck('name', 'id'))
                        ->required(),

                    Forms\Components\Select::make('to_account_id')
                        ->label('Na účet')
                        ->options(Account::pluck('name', 'id'))
                        ->required()
                        ->different('from_account_id'), // Nemôžeš poslať z účtu na ten istý účet

                    Forms\Components\TextInput::make('amount')
                        ->label('Suma')
                        ->numeric()
                        ->required()
                        ->minValue(0.01),

                    Forms\Components\DatePicker::make('transaction_date')
                        ->label('Dátum')
                        ->default(now())
                        ->required(),
                ])
                ->action(function (array $data) {
                    DB::transaction(function () use ($data) {
                        // 1. Odchod peňazí (Vždy MÍNUS)
                        Transaction::create([
                            'account_id' => $data['from_account_id'],
                            'type' => 'transfer', // Zmenené z 'expense'
                            'amount' => -abs($data['amount']), // Vynútime mínus
                            'transaction_date' => $data['transaction_date'],
                            'description' => 'Interný prevod (Odchod)',
                        ]);

                        // 2. Príchod peňazí (Vždy PLUS)
                        Transaction::create([
                            'account_id' => $data['to_account_id'],
                            'type' => 'transfer', // Zmenené z 'income'
                            'amount' => abs($data['amount']), // Vynútime plus
                            'transaction_date' => $data['transaction_date'],
                            'description' => 'Interný prevod (Príchod)',
                        ]);
                    });
                })
                ->successNotificationTitle('Prevod úspešne prebehol'),
        ];
    }
}
