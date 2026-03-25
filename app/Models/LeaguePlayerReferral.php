<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'league_id',
    'referrer_player_id',
    'referred_player_id',
    'credit_amount_cents',
    'created_by_user_id',
])]
class LeaguePlayerReferral extends Model
{
    protected function casts(): array
    {
        return [
            'credit_amount_cents' => 'integer',
        ];
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(LeaguePlayer::class, 'referrer_player_id');
    }

    public function referred(): BelongsTo
    {
        return $this->belongsTo(LeaguePlayer::class, 'referred_player_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
