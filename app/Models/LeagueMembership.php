<?php

namespace App\Models;

use App\Enums\LeagueMembershipRole;
use Database\Factories\LeagueMembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['league_id', 'user_id', 'role'])]
class LeagueMembership extends Model
{
    /** @use HasFactory<LeagueMembershipFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'role' => LeagueMembershipRole::class,
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
}
