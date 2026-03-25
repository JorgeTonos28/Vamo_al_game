<?php

namespace App\Enums;

enum LeagueMembershipRole: string
{
    case Admin = 'admin';
    case Member = 'member';
    case Guest = 'guest';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador de liga',
            self::Member => 'Miembro',
            self::Guest => 'Invitado',
        };
    }

    public function sortOrder(): int
    {
        return match ($this) {
            self::Admin => 0,
            self::Member => 1,
            self::Guest => 2,
        };
    }

    public function canAccessOperationalModules(): bool
    {
        return $this !== self::Guest;
    }

    public function canManageLeague(): bool
    {
        return $this === self::Admin;
    }
}
