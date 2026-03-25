<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'league_id',
    'sessions_limit',
    'game_days',
    'cut_day',
    'effective_from',
    'effective_until',
    'created_by_user_id',
])]
class LeagueCutConfiguration extends Model
{
    protected function casts(): array
    {
        return [
            'game_days' => 'array',
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function cuts(): HasMany
    {
        return $this->hasMany(LeagueCut::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
