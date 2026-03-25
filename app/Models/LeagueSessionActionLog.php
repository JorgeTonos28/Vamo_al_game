<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'league_session_id',
    'league_session_game_id',
    'sequence',
    'action_type',
    'before_state',
    'undone_at',
    'recorded_by_user_id',
])]
class LeagueSessionActionLog extends Model
{
    protected function casts(): array
    {
        return [
            'before_state' => 'array',
            'undone_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(LeagueSession::class, 'league_session_id');
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(LeagueSessionGame::class, 'league_session_game_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
