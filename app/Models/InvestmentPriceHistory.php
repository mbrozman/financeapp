Illuminate\Database\QueryException
vendor\laravel\framework\src\Illuminate\Database\Connection.php:838
SQLSTATE[22P02]: Invalid text representation: 7 ERROR: invalid input syntax for type uuid: "19" CONTEXT: unnamed portal parameter $1 = '...' (Connection: pgsql, Host: 127.0.0.1, Port: 5432, Database: finance_app, SQL: select SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income, SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expense from "transactions" where "user_id" = 19 and extract(year from "transaction_date") = 2026 and "transactions"."user_id" = 019d07df-2ae4-7080-98af-0b4e8f061599 limit 1)<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestmentPriceHistory extends Model
{
    protected $fillable = ['investment_id', 'price', 'recorded_at'];

    protected $casts = [
        'recorded_at' => 'date',
        'price' => 'decimal:4'
    ];

    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class);
    }
}
