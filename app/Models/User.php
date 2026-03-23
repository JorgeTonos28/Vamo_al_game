<?php

namespace App\Models;

use App\Enums\AccountRole;
use App\Policies\UserPolicy;
use App\Notifications\VerifyEmailNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

#[UsePolicy(UserPolicy::class)]
#[Fillable([
    'first_name',
    'last_name',
    'name',
    'document_id',
    'phone',
    'address',
    'email',
    'password',
    'account_role',
    'google_id',
    'avatar',
    'invited_by_user_id',
    'invited_at',
    'onboarded_at',
    'active_league_id',
])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'account_role' => AccountRole::class,
            'email_verified_at' => 'datetime',
            'invited_at' => 'datetime',
            'onboarded_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(self::class, 'invited_by_user_id');
    }

    public function invitedUsers(): HasMany
    {
        return $this->hasMany(self::class, 'invited_by_user_id');
    }

    public function leagueMemberships(): HasMany
    {
        return $this->hasMany(LeagueMembership::class);
    }

    public function activeLeague(): BelongsTo
    {
        return $this->belongsTo(League::class, 'active_league_id');
    }

    public function invitation(): HasOne
    {
        return $this->hasOne(UserInvitation::class);
    }

    public function hasPendingInvitation(): bool
    {
        if ($this->relationLoaded('invitation')) {
            return $this->invitation?->isPending() ?? false;
        }

        return $this->invitation()
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->exists();
    }

    public function isGeneralAdmin(): bool
    {
        return $this->account_role?->isGeneralAdmin() ?? false;
    }

    public function hasCompletedOnboarding(): bool
    {
        return $this->onboarded_at !== null;
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }
}
