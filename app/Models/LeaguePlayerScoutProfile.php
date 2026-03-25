<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'league_player_id',
    'position',
    'role',
    'offensive_consistency',
    'speed_rating',
    'dribbling_rating',
    'scoring_rating',
    'team_play_rating',
    'court_knowledge_rating',
    'defense_rating',
    'triples_rating',
    'updated_by_user_id',
    'last_reviewed_at',
])]
class LeaguePlayerScoutProfile extends Model
{
    protected function casts(): array
    {
        return [
            'last_reviewed_at' => 'datetime',
        ];
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(LeaguePlayer::class, 'league_player_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
