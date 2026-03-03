<?php

namespace App\Models\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait BelongsToUser
{
    protected static function bootBelongsToUser(): void
    {
        static::addGlobalScope('user_id', function (Builder $builder) {
            if (Auth::check()) {
                $builder->where('user_id', Auth::id());
            }
        });

        static::creating(function ($model) {
            if (Auth::check() && ! $model->user_id) {
                $model->user_id = Auth::id();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}