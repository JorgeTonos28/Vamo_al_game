<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'league_id',
    'fee_type',
    'amount_cents',
    'effective_from',
    'effective_until',
    'created_by_user_id',
])]
class LeagueFeeSchedule extends Model
{
    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
