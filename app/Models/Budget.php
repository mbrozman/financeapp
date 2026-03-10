<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Brick\Math\BigDecimal;

class Budget extends Model
{
    use BelongsToUser;

    protected $fillable = ['user_id', 'category_id', 'financial_plan_item_id', 'limit_amount', 'period', 'valid_from'];

    protected $casts = [
        'valid_from' => 'date',
        'limit_amount' => 'string',
    ];
    // VZŤAHY
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
    public function planItem(): BelongsTo
    {
        return $this->belongsTo(FinancialPlanItem::class, 'financial_plan_item_id');
    }

    /**
     * VÝPOČET REÁLNEHO ČERPANIA
     * Prebehne tvoje transakcie a sčíta výdavky pre túto kategóriu v danom mesiaci.
     */
    protected function actualAmount(): Attribute
    {
        return Attribute::make(
            get: function () {
                // Berieme do úvahy aktuálny mesiac a rok
                $now = now();

                // Ak pravidlo začína platiť až v budúcnosti, aktuálne čerpanie je 0
                if ($this->valid_from && $this->valid_from->copy()->startOfMonth()->isFuture()) {
                    return '0.00';
                }

                $sum = Transaction::where('category_id', $this->category_id)
                    ->where('type', 'expense')
                    ->whereYear('transaction_date', $now->year)
                    ->whereMonth('transaction_date', $now->month)
                    ->sum('amount');

                // Vrátime kladné číslo (absolútnu hodnotu)
                return (string) BigDecimal::of($sum ?? 0)->abs();
            }
        );
    }
}
