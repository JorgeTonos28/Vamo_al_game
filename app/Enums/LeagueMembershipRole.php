<?php

namespace App\Enums;

enum LeagueMembershipRole: string
{
    case Admin = 'admin';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador de liga',
            self::Member => 'Miembro',
        };
    }
}
