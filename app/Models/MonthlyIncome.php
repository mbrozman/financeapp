<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class MonthlyIncome extends Model
{
    use BelongsToUser;
    protected $fillable = ['user_id', 'amount', 'period', 'note'];
}