<!DOCTYPE html>
<html>
<head>
    {{-- 1. DÔLEŽITÉ: Meta tag pre kódovanie --}}
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Mesačný výpis transakcií</title>
    
    <style>
        body { 
            /* 2. DÔLEŽITÉ: DejaVu Sans podporuje slovenské znaky */
            font-family: 'DejaVu Sans', sans-serif; 
            font-size: 10px; 
            color: #333; 
            line-height: 1.5;
        }
        .header { text-align: center; margin-bottom: 30px; }
        h1 { font-size: 18px; margin-bottom: 5px; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background-color: #f8f9fa; border-bottom: 1px solid #dee2e6; padding: 8px; text-align: left; font-weight: bold; }
        td { border-bottom: 1px solid #eee; padding: 8px; vertical-align: top; }
        
        .amount { text-align: right; white-space: nowrap; font-weight: bold; }
        .expense { color: #e53e3e; }
        .income { color: #38a169; }
        
        .footer { position: fixed; bottom: -30px; left: 0; right: 0; text-align: center; font-size: 8px; color: #aaa; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Finančný report</h1>
        <p>Vygenerované dňa: {{ now()->format('d. m. Y H:i') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 15%">Dátum</th>
                <th style="width: 35%">Popis / Poznámka</th>
                <th style="width: 20%">Kategória</th>
                <th style="width: 15%">Účet</th>
                <th style="width: 15%" class="amount">Suma</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transactions as $transaction)
            <tr>
                <td>{{ $transaction->transaction_date->format('d.m.Y') }}</td>
                <td>{{ $transaction->description ?? 'Bez popisu' }}</td>
                <td>{{ $transaction->category->name ?? '-' }}</td>
                <td>{{ $transaction->account->name }}</td>
                <td class="amount {{ $transaction->amount < 0 ? 'expense' : 'income' }}">
                    {{ number_format($transaction->amount, 2, ',', ' ') }} {{ $transaction->account->currency->symbol }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Vygenerované aplikáciou FinanceApp | Strana {PAGE_NUM}
    </div>
</body>
</html>