<?php

namespace App\Models;

use Database\Factories\LeaguePlayerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'league_id',
    'user_id',
    'display_name',
    'jersey_number',
    'status',
    'created_by_user_id',
    'updated_by_user_id',
    'joined_at',
    'removed_at',
])]
class LeaguePlayer extends Model
{
    /** @use HasFactory<LeaguePlayerFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'removed_at' => 'datetime',
        ];
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function balances(): HasMany
    {
        return $this->hasMany(LeagueCutPlayerBalance::class);
    }

    public function referralsMade(): HasMany
    {
        return $this->hasMany(LeaguePlayerReferral::class, 'referrer_player_id');
    }

    public function referredBy(): HasMany
    {
        return $this->hasMany(LeaguePlayerReferral::class, 'referred_player_id');
    }

    public function sessionEntries(): HasMany
    {
        return $this->hasMany(LeagueSessionEntry::class);
    }
}
