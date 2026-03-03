<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringTransaction extends Model
{
    use BelongsToUser; // Zabezpečí, že každý vidí len svoje platby

    protected $fillable = [
        'user_id',
        'account_id',
        'category_id',
        'name',
        'amount',
        'type',
        'interval',
        'next_date',
        'is_active'
    ];

    protected $casts = [
        'next_date' => 'date',
        'amount' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    // Vzťah k účtu
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    // Vzťah ku kategórii
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}