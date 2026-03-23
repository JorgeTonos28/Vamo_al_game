<?php

namespace App\Enums;

enum AccountRole: string
{
    case GeneralAdmin = 'general_admin';
    case LeagueAdmin = 'league_admin';
    case Member = 'member';
    case Guest = 'guest';

    public function label(): string
    {
        return match ($this) {
            self::GeneralAdmin => 'Administrador general',
            self::LeagueAdmin => 'Administrador de liga',
            self::Member => 'Miembro',
            self::Guest => 'Invitado',
        };
    }

    public function isGeneralAdmin(): bool
    {
        return $this === self::GeneralAdmin;
    }
}
