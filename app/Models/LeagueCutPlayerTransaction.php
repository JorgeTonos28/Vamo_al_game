<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'league_cut_player_balance_id',
    'transaction_type',
    'amount_cents',
    'note',
    'source_cut_id',
    'recorded_by_user_id',
])]
class LeagueCutPlayerTransaction extends Model
{
    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
        ];
    }

    public function balance(): BelongsTo
    {
        return $this->belongsTo(LeagueCutPlayerBalance::class, 'league_cut_player_balance_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function sourceCut(): BelongsTo
    {
        return $this->belongsTo(LeagueCut::class, 'source_cut_id');
    }
}
