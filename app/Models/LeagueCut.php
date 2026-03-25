<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'league_id',
    'league_cut_configuration_id',
    'label',
    'starts_on',
    'ends_on',
    'due_on',
    'sessions_limit',
    'game_days',
    'member_fee_amount_cents',
    'guest_fee_amount_cents',
    'status',
])]
class LeagueCut extends Model
{
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'due_on' => 'date',
            'game_days' => 'array',
            'member_fee_amount_cents' => 'integer',
            'guest_fee_amount_cents' => 'integer',
        ];
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function configuration(): BelongsTo
    {
        return $this->belongsTo(LeagueCutConfiguration::class, 'league_cut_configuration_id');
    }

    public function balances(): HasMany
    {
        return $this->hasMany(LeagueCutPlayerBalance::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(LeagueCutExpense::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(LeagueSession::class);
    }
}
