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
            ['name' => 'Juan Luis Santana', 'jersey' => 1],
            ['name' => 'Elvis Espinal', 'jersey' => 23],
            ['name' => 'Levsky Abud', 'jersey' => null],
            ['name' => 'Edward Smith', 'jersey' => 11],
            ['name' => 'Gilbert Tejeda', 'jersey' => 21],
            ['name' => 'Edsel Tineo', 'jersey' => 5],
            ['name' => 'Gregory Cruz', 'jersey' => 7],
            ['name' => 'Leonardo Pujols', 'jersey' => 24],
            ['name' => 'Alexis Carmona', 'jersey' => 10],
            ['name' => 'Michael Troncoso', 'jersey' => 3],
            ['name' => 'Jose Medos', 'jersey' => 8],
            ['name' => 'David Mejia', 'jersey' => null],
            ['name' => 'Raul Veras', 'jersey' => 15],
            ['name' => 'Radziwill de Jesus', 'jersey' => null],
            ['name' => 'Deivi Dominguez', 'jersey' => 32],
            ['name' => 'Manuel Emilio', 'jersey' => null],
            ['name' => 'Manuel Cabral', 'jersey' => null],
            ['name' => 'Elvis Rosado', 'jersey' => null],
            ['name' => 'Gilbert Mendez', 'jersey' => null],
            ['name' => 'Gilberg Jimenez', 'jersey' => null],
            ['name' => 'Ariel Brown', 'jersey' => null],
            ['name' => 'Gregory Conde', 'jersey' => null],
            ['name' => 'Johan Follon', 'jersey' => null],
            ['name' => 'Namil Correa', 'jersey' => null],
            ['name' => 'Lazaro Garcia', 'jersey' => null],
            ['name' => 'Wilbert Lachapel', 'jersey' => null],
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

        collect([
            [
                'name' => 'Alquiler cancha',
                'amount_cents' => LeagueOperationsService::DEFAULT_COURT_RENT_CENTS,
                'expense_type' => 'fixed',
            ],
            [
                'name' => 'Arbitros',
                'amount_cents' => LeagueOperationsService::DEFAULT_REFEREE_FEE_CENTS * $currentCut->sessions_limit,
                'expense_type' => 'fixed',
            ],
            [
                'name' => 'Agua, vasos e hielo',
                'amount_cents' => LeagueOperationsService::DEFAULT_SUPPLIES_FEE_CENTS * $currentCut->sessions_limit,
                'expense_type' => 'fixed',
            ],
            [
                'name' => 'Marcadores nuevos',
                'amount_cents' => 250000,
                'expense_type' => 'custom',
            ],
        ])->each(function (array $expense) use ($currentCut): void {
            $currentCut->expenses()->updateOrCreate(
                [
                    'name' => $expense['name'],
                    'expense_type' => $expense['expense_type'],
                ],
                [
                    'amount_cents' => $expense['amount_cents'],
                    'spent_on' => $currentCut->ends_on,
                    'is_system_generated' => false,
                ],
            );
        });

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
            'priority_bucket' => 'guest_unpaid',
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
