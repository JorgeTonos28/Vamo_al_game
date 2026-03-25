<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'league_session_id',
    'game_number',
    'draft_mode',
    'status',
    'phase',
    'team_a_score',
    'team_b_score',
    'winner_side',
    'team_a_snapshot',
    'team_b_snapshot',
    'player_points',
    'player_shots',
    'notes',
    'started_at',
    'ended_at',
    'created_by_user_id',
    'finished_by_user_id',
])]
class LeagueSessionGame extends Model
{
    protected function casts(): array
    {
        return [
            'team_a_snapshot' => 'array',
            'team_b_snapshot' => 'array',
            'player_points' => 'array',
            'player_shots' => 'array',
            'notes' => 'array',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(LeagueSession::class, 'league_session_id');
    }

    public function actionLogs(): HasMany
    {
        return $this->hasMany(LeagueSessionActionLog::class, 'league_session_game_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function finisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finished_by_user_id');
    }
}
