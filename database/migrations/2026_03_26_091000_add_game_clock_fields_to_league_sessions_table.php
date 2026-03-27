<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('league_sessions', function (Blueprint $table) {
            $table->unsignedSmallInteger('clock_duration_seconds')->nullable()->after('current_game_number');
            $table->unsignedSmallInteger('clock_remaining_seconds')->nullable()->after('clock_duration_seconds');
            $table->string('clock_state')->default('paused')->after('clock_remaining_seconds');
            $table->timestamp('clock_started_at')->nullable()->after('clock_state');
        });
    }

    public function down(): void
    {
        Schema::table('league_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'clock_duration_seconds',
                'clock_remaining_seconds',
                'clock_state',
                'clock_started_at',
            ]);
        });
    }
};
