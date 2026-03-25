<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'league_cut_id',
    'expense_type',
    'name',
    'amount_cents',
    'is_system_generated',
    'recorded_by_user_id',
    'spent_on',
])]
class LeagueCutExpense extends Model
{
    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'is_system_generated' => 'boolean',
            'spent_on' => 'date',
        ];
    }

    public function cut(): BelongsTo
    {
        return $this->belongsTo(LeagueCut::class, 'league_cut_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
