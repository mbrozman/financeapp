<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait BelongsToUser
{
    protected static function bootBelongsToUser(): void
    {
        static::addGlobalScope('user_id', function (Builder $builder) {
            if (Auth::check()) {
                // Trik: qualifyColumn pridá názov tabuľky pred názov stĺpca
                // Takže namiesto "user_id" vznikne "categories.user_id"
                $builder->where($builder->getModel()->qualifyColumn('user_id'), Auth::id());
            }
        });

        static::creating(function ($model) {
            if (Auth::check() && ! $model->user_id) {
                $model->user_id = Auth::id();
            }
        });
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}