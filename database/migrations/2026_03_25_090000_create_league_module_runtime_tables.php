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
        Schema::create('league_seasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('status')->default('active');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['league_id', 'status']);
        });

        Schema::table('league_sessions', function (Blueprint $table) {
            $table->foreignId('league_season_id')->nullable()->after('league_cut_id')->constrained('league_seasons')->nullOnDelete();
            $table->unsignedSmallInteger('current_game_number')->default(1)->after('status');
            $table->json('rotation_state')->nullable()->after('initial_queue');
        });

        Schema::table('league_session_entries', function (Blueprint $table) {
            $table->string('session_state')->default('arrival')->after('queue_seed');
            $table->string('team_side')->nullable()->after('session_state');
            $table->unsignedInteger('queue_position')->nullable()->after('team_side');

            $table->index(
                ['league_session_id', 'session_state', 'queue_position'],
                'lse_session_state_queue_idx',
            );
        });

        Schema::create('league_session_games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_session_id')->constrained('league_sessions')->cascadeOnDelete();
            $table->unsignedSmallInteger('game_number');
            $table->string('draft_mode')->nullable();
            $table->string('status')->default('open');
            $table->string('phase')->default('standard');
            $table->unsignedSmallInteger('team_a_score')->default(0);
            $table->unsignedSmallInteger('team_b_score')->default(0);
            $table->string('winner_side')->nullable();
            $table->json('team_a_snapshot')->nullable();
            $table->json('team_b_snapshot')->nullable();
            $table->json('player_points')->nullable();
            $table->json('player_shots')->nullable();
            $table->json('notes')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('finished_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['league_session_id', 'game_number']);
            $table->index(['league_session_id', 'status']);
        });

        Schema::create('league_session_action_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_session_id')->constrained('league_sessions')->cascadeOnDelete();
            $table->foreignId('league_session_game_id')->nullable()->constrained('league_session_games')->nullOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('action_type');
            $table->json('before_state');
            $table->timestamp('undone_at')->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['league_session_id', 'league_session_game_id'], 'lsal_session_game_idx');
            $table->index(['league_session_id', 'sequence'], 'lsal_session_sequence_idx');
        });

        Schema::create('league_player_scout_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_player_id')->constrained('league_players')->cascadeOnDelete();
            $table->string('position')->nullable();
            $table->string('role')->nullable();
            $table->string('offensive_consistency')->nullable();
            $table->unsignedTinyInteger('speed_rating')->default(0);
            $table->unsignedTinyInteger('dribbling_rating')->default(0);
            $table->unsignedTinyInteger('scoring_rating')->default(0);
            $table->unsignedTinyInteger('team_play_rating')->default(0);
            $table->unsignedTinyInteger('court_knowledge_rating')->default(0);
            $table->unsignedTinyInteger('defense_rating')->default(0);
            $table->unsignedTinyInteger('triples_rating')->default(0);
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_reviewed_at')->nullable();
            $table->timestamps();

            $table->unique('league_player_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('league_player_scout_profiles');
        Schema::dropIfExists('league_session_action_logs');
        Schema::dropIfExists('league_session_games');

        Schema::table('league_session_entries', function (Blueprint $table) {
            $table->dropIndex('lse_session_state_queue_idx');
            $table->dropColumn(['session_state', 'team_side', 'queue_position']);
        });

        Schema::table('league_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('league_season_id');
            $table->dropColumn(['current_game_number', 'rotation_state']);
        });

        Schema::dropIfExists('league_seasons');
    }
};
