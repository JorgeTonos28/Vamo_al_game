<?php

namespace App\Models;

use Database\Factories\LeagueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'is_active', 'created_by_user_id'])]
class League extends Model
{
    /** @use HasFactory<LeagueFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(LeagueMembership::class);
    }

    public function adminMemberships(): HasMany
    {
        return $this->memberships()->where('role', \App\Enums\LeagueMembershipRole::Admin);
    }

    public function memberMemberships(): HasMany
    {
        return $this->memberships()->where('role', \App\Enums\LeagueMembershipRole::Member);
    }
}
