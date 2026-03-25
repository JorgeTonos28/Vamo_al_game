<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'league_cut_id',
    'league_player_id',
    'carry_in_cents',
    'amount_due_cents',
    'amount_paid_cents',
    'referral_credit_applied_cents',
    'extra_credit_cents',
    'status',
    'paid_at',
    'last_payment_at',
])]
class LeagueCutPlayerBalance extends Model
{
    protected function casts(): array
    {
        return [
            'carry_in_cents' => 'integer',
            'amount_due_cents' => 'integer',
            'amount_paid_cents' => 'integer',
            'referral_credit_applied_cents' => 'integer',
            'extra_credit_cents' => 'integer',
            'paid_at' => 'datetime',
            'last_payment_at' => 'datetime',
        ];
    }

    public function cut(): BelongsTo
    {
        return $this->belongsTo(LeagueCut::class, 'league_cut_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(LeaguePlayer::class, 'league_player_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(LeagueCutPlayerTransaction::class);
    }
}
