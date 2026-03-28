<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\Category;
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

            // NAŠE VYLEPŠENÉ TLAČIDLO PRE PREVOD
            Actions\Action::make('transfer')
                ->label('Interný prevod')
                ->icon('heroicon-m-arrows-right-left')
                ->color('info')
                ->form([
                    Forms\Components\Select::make('from_account_id')
                        ->label('Z účtu')
                        ->options(Account::where('user_id', auth()->id())->pluck('name', 'id'))
                        ->required(),

                    Forms\Components\Select::make('to_account_id')
                        ->label('Na účet')
                        ->options(Account::where('user_id', auth()->id())->pluck('name', 'id'))
                        ->required()
                        ->different('from_account_id'),

                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\Select::make('parent_category_id')
                                ->label('Hlavná skupina (nepovinné)')
                                ->options(Category::where('user_id', auth()->id())
                                    ->whereNull('parent_id')
                                    ->where('type', 'expense')
                                    ->pluck('name', 'id')
                                )
                                ->live()
                                ->searchable(),

                            Forms\Components\Select::make('category_id')
                                ->label('Podkategória / Detail')
                                ->options(function (Forms\Get $get) {
                                    $parentId = $get('parent_category_id');
                                    $query = Category::where('user_id', auth()->id())
                                        ->whereNotNull('parent_id')
                                        ->where('type', 'expense');
                                        
                                    if ($parentId) {
                                        $query->where('parent_id', $parentId);
                                    }
                                    
                                    return $query->with('parent')
                                        ->get()
                                        ->groupBy('parent.name')
                                        ->map(fn($categories) => $categories->pluck('name', 'id'))
                                        ->toArray();
                                })
                                ->searchable(),
                        ]),

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
                        // 1. Odchod peňazí (Vždy MÍNUS) + PRIRADENIE KATEGÓRIE
                        $t1 = Transaction::create([
                            'user_id' => auth()->id(),
                            'account_id' => $data['from_account_id'],
                            'category_id' => $data['category_id'] ?? null,
                            'type' => 'transfer',
                            'amount' => -abs($data['amount']),
                            'transaction_date' => $data['transaction_date'],
                            'description' => 'Interný prevod (Odchod)',
                        ]);

                        // 2. Príchod peňazí (Vždy PLUS) - Bez kategórie (aby sa nezapočítalo 2x)
                        $t2 = Transaction::create([
                            'user_id' => auth()->id(),
                            'account_id' => $data['to_account_id'],
                            'linked_transaction_id' => $t1->id, // Prepojíme na t1
                            'type' => 'transfer',
                            'amount' => abs($data['amount']),
                            'transaction_date' => $data['transaction_date'],
                            'description' => 'Interný prevod (Príchod)',
                        ]);

                        // 3. Spätne prepojíme t1 na t2
                        $t1->update(['linked_transaction_id' => $t2->id]);
                    });
                })
                ->successNotificationTitle('Prevod úspešne prebehol'),
        ];
    }
}
