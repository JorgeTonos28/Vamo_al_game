<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'league_session_id',
    'league_player_id',
    'guest_name',
    'entry_type',
    'arrival_order',
    'current_cut_paid',
    'guest_fee_paid',
    'was_marked_paid_on_arrival',
    'priority_bucket',
    'queue_seed',
    'session_state',
    'team_side',
    'queue_position',
])]
class LeagueSessionEntry extends Model
{
    protected function casts(): array
    {
        return [
            'current_cut_paid' => 'boolean',
            'guest_fee_paid' => 'boolean',
            'was_marked_paid_on_arrival' => 'boolean',
            'queue_position' => 'integer',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(LeagueSession::class, 'league_session_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(LeaguePlayer::class, 'league_player_id');
    }
}
