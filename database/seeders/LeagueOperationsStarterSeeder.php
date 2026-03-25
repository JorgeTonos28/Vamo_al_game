<?php

namespace Database\Seeders;

use App\Models\League;
use App\Models\LeaguePlayer;
use App\Models\User;
use App\Services\LeagueOperations\LeagueManagementService;
use App\Services\LeagueOperations\LeagueOperationsService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class LeagueOperationsStarterSeeder extends Seeder
{
    public function run(): void
    {
        $league = League::query()->where('slug', 'liga-aurora')->first();
        $leagueAdmin = User::query()->where('email', 'adminleaguetest@vamoalgame.com')->first();
        $member = User::query()->where('email', 'membertest@vamoalgame.com')->first();

        if ($league === null || $leagueAdmin === null || $member === null) {
            return;
        }

        $leagueAdmin->forceFill([
            'active_league_id' => $league->id,
        ])->save();

        $operations = app(LeagueOperationsService::class);
        $management = app(LeagueManagementService::class);

        $names = [
            ['name' => 'Miembro Prueba', 'jersey' => 7, 'user_id' => $member->id],
            ['name' => 'Gregory Cruz', 'jersey' => 10],
            ['name' => 'Raul Veras', 'jersey' => 11],
            ['name' => 'Juan Luis Santana', 'jersey' => 12],
            ['name' => 'Christopher Reyes', 'jersey' => 15],
            ['name' => 'Leo Rosario', 'jersey' => 18],
            ['name' => 'Angel Marte', 'jersey' => 21],
            ['name' => 'Joel Peguero', 'jersey' => 23],
            ['name' => 'Misael Tapia', 'jersey' => 24],
            ['name' => 'Ramon Vargas', 'jersey' => 30],
            ['name' => 'Juan Estrella', 'jersey' => 31],
            ['name' => 'Pedro Guzman', 'jersey' => 33],
        ];

        $players = collect($names)->map(function (array $playerData) use ($league, $leagueAdmin): LeaguePlayer {
            /** @var LeaguePlayer $player */
            $player = $league->players()->updateOrCreate(
                ['display_name' => $playerData['name']],
                [
                    'user_id' => $playerData['user_id'] ?? null,
                    'jersey_number' => $playerData['jersey'],
                    'status' => 'active',
                    'created_by_user_id' => $leagueAdmin->id,
                    'updated_by_user_id' => $leagueAdmin->id,
                    'joined_at' => now()->subMonths(3),
                ],
            );

            return $player;
        })->values();

        $previousCut = $operations->activeCutForLeague(
            $league,
            CarbonImmutable::now()->subDays(20),
        );
        $currentCut = $operations->activeCutForLeague($league);

        $management->recordPayment($leagueAdmin, $players[0], 60000, false, $previousCut->id);
        $management->recordPayment($leagueAdmin, $players[1], 60000, false, $previousCut->id);
        $management->recordPayment($leagueAdmin, $players[2], 30000, false, $previousCut->id);
        $management->recordPayment($leagueAdmin, $players[3], 70000, false, $currentCut->id);
        $management->recordPayment($leagueAdmin, $players[4], 60000, false, $currentCut->id);
        $management->recordPayment($leagueAdmin, $players[5], 120000, false, $currentCut->id);
        $management->recordPayment($leagueAdmin, $players[6], 60000, false, $currentCut->id);
        $management->recordPayment($leagueAdmin, $players[7], 60000, false, $currentCut->id);

        $management->storeExpense(
            $leagueAdmin,
            'Marcadores nuevos',
            250000,
            'custom',
            $currentCut->id,
        );

        if ($players->count() >= 9) {
            $existingReferral = $league->referrals()
                ->where('referrer_player_id', $players[1]->id)
                ->where('referred_player_id', $players[8]->id)
                ->exists();

            if (! $existingReferral) {
                $management->storeReferral($leagueAdmin, $players[1], $players[8]);
            }
        }

        $session = $operations->currentSessionForLeague($league, $currentCut);

        if ($session === null) {
            return;
        }

        $session->entries()->delete();

        foreach ($players->take(8)->values() as $index => $player) {
            $session->entries()->create([
                'league_player_id' => $player->id,
                'entry_type' => 'player',
                'arrival_order' => $index + 1,
                'current_cut_paid' => in_array($player->id, [
                    $players[3]->id,
                    $players[4]->id,
                    $players[5]->id,
                    $players[6]->id,
                    $players[7]->id,
                ], true),
                'priority_bucket' => 'member_priority',
            ]);
        }

        $session->entries()->create([
            'guest_name' => 'Invitado A',
            'entry_type' => 'guest',
            'arrival_order' => 9,
            'guest_fee_paid' => true,
            'priority_bucket' => 'guest_paid',
        ]);

        $session->entries()->create([
            'guest_name' => 'Invitado B',
            'entry_type' => 'guest',
            'arrival_order' => 10,
            'guest_fee_paid' => false,
            'priority_bucket' => 'guest_paid',
        ]);

        $league->cutConfigurations()->updateOrCreate(
            [
                'effective_from' => now()->startOfMonth()->toDateString(),
            ],
            [
                'sessions_limit' => 4,
                'game_days' => ['Sabado', 'Domingo'],
                'cut_day' => 15,
                'created_by_user_id' => $leagueAdmin->id,
            ],
        );
    }
}
