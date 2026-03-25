<?php

namespace Database\Seeders;

use App\Enums\AccountRole;
use App\Enums\LeagueMembershipRole;
use App\Models\League;
use App\Models\LeagueMembership;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class LocalStarterDataSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('TestUSER12345678');

        $generalAdmin = User::updateOrCreate([
            'email' => 'admingentest@vamoalgame.com',
        ], [
            'first_name' => 'Admin',
            'last_name' => 'General',
            'name' => 'Admin General',
            'document_id' => '00000000001',
            'phone' => '809-555-1001',
            'address' => 'Centro de mando principal',
            'password' => $password,
            'account_role' => AccountRole::GeneralAdmin,
            'email_verified_at' => now(),
            'invited_at' => now(),
            'onboarded_at' => now(),
        ]);

        $leagueAdmin = User::updateOrCreate([
            'email' => 'adminleaguetest@vamoalgame.com',
        ], [
            'first_name' => 'Admin',
            'last_name' => 'Liga',
            'name' => 'Admin Liga',
            'document_id' => '00000000002',
            'phone' => '809-555-1002',
            'address' => 'Liga Aurora',
            'password' => $password,
            'account_role' => AccountRole::LeagueAdmin,
            'email_verified_at' => now(),
            'invited_at' => now(),
            'onboarded_at' => now(),
        ]);

        $member = User::updateOrCreate([
            'email' => 'membertest@vamoalgame.com',
        ], [
            'first_name' => 'Miembro',
            'last_name' => 'Prueba',
            'name' => 'Miembro Prueba',
            'document_id' => '00000000003',
            'phone' => '809-555-1003',
            'address' => 'Liga Aurora',
            'password' => $password,
            'account_role' => AccountRole::Member,
            'email_verified_at' => now(),
            'invited_at' => now(),
            'onboarded_at' => now(),
        ]);

        $guest = User::updateOrCreate([
            'email' => 'guestest@vamoalgame.com',
        ], [
            'first_name' => 'Invitado',
            'last_name' => 'Prueba',
            'name' => 'Invitado Prueba',
            'document_id' => '00000000004',
            'phone' => '809-555-1004',
            'address' => 'Sin liga fija',
            'password' => $password,
            'account_role' => AccountRole::Guest,
            'email_verified_at' => now(),
            'invited_at' => now(),
            'onboarded_at' => now(),
        ]);

        $aurora = League::updateOrCreate([
            'slug' => 'liga-aurora',
        ], [
            'name' => 'Liga Aurora',
            'emoji' => '⚽',
            'is_active' => true,
            'created_by_user_id' => $leagueAdmin->id,
        ]);

        $titanes = League::updateOrCreate([
            'slug' => 'liga-titanes',
        ], [
            'name' => 'Liga Titanes',
            'emoji' => '🏀',
            'is_active' => true,
            'created_by_user_id' => $leagueAdmin->id,
        ]);

        $barrio = League::updateOrCreate([
            'slug' => 'liga-barrio-central',
        ], [
            'name' => 'Liga Barrio Central',
            'emoji' => '🏐',
            'is_active' => false,
            'created_by_user_id' => $leagueAdmin->id,
        ]);

        $memberships = [
            [$aurora->id, $leagueAdmin->id, LeagueMembershipRole::Admin],
            [$titanes->id, $leagueAdmin->id, LeagueMembershipRole::Admin],
            [$aurora->id, $member->id, LeagueMembershipRole::Member],
            [$aurora->id, $guest->id, LeagueMembershipRole::Guest],
            [$barrio->id, $member->id, LeagueMembershipRole::Admin],
        ];

        foreach ($memberships as [$leagueId, $userId, $role]) {
            LeagueMembership::updateOrCreate([
                'league_id' => $leagueId,
                'user_id' => $userId,
            ], [
                'role' => $role,
            ]);
        }

        $leagueAdmin->forceFill([
            'active_league_id' => $aurora->id,
        ])->save();

        $member->forceFill([
            'active_league_id' => $aurora->id,
        ])->save();

        $guest->forceFill([
            'active_league_id' => $aurora->id,
        ])->save();

        $this->call([
            LeagueOperationsStarterSeeder::class,
        ]);
    }
}
