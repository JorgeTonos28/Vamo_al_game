<?php

namespace App\Models;

use App\Enums\LeagueMembershipRole;
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
        return $this->memberships()->where('role', LeagueMembershipRole::Admin);
    }

    public function memberMemberships(): HasMany
    {
        return $this->memberships()->where('role', LeagueMembershipRole::Member);
    }

    public function guestMemberships(): HasMany
    {
        return $this->memberships()->where('role', LeagueMembershipRole::Guest);
    }

    public function players(): HasMany
    {
        return $this->hasMany(LeaguePlayer::class);
    }

    public function activePlayers(): HasMany
    {
        return $this->players()->where('status', 'active');
    }

    public function cutConfigurations(): HasMany
    {
        return $this->hasMany(LeagueCutConfiguration::class);
    }

    public function feeSchedules(): HasMany
    {
        return $this->hasMany(LeagueFeeSchedule::class);
    }

    public function cuts(): HasMany
    {
        return $this->hasMany(LeagueCut::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(LeagueSession::class);
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(LeaguePlayerReferral::class);
    }
}
