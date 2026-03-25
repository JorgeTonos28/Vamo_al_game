<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('league_players', function (Blueprint $table): void {
            $table->unique(['league_id', 'user_id'], 'league_players_league_user_unique');
        });

        Schema::table('league_cut_player_transactions', function (Blueprint $table): void {
            $table->foreignId('source_cut_id')
                ->nullable()
                ->after('league_cut_player_balance_id')
                ->constrained('league_cuts')
                ->nullOnDelete();

            $table->index(['source_cut_id', 'transaction_type'], 'lcpt_source_type_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('league_cut_player_transactions', function (Blueprint $table): void {
            $table->dropIndex('lcpt_source_type_idx');
            $table->dropConstrainedForeignId('source_cut_id');
        });

        Schema::table('league_players', function (Blueprint $table): void {
            $table->dropUnique('league_players_league_user_unique');
        });
    }
};
