<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'league_id',
    'league_cut_id',
    'session_date',
    'status',
    'initial_pool',
    'initial_queue',
    'started_at',
    'prepared_at',
    'ended_at',
    'created_by_user_id',
])]
class LeagueSession extends Model
{
    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'initial_pool' => 'array',
            'initial_queue' => 'array',
            'started_at' => 'datetime',
            'prepared_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function cut(): BelongsTo
    {
        return $this->belongsTo(LeagueCut::class, 'league_cut_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LeagueSessionEntry::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
