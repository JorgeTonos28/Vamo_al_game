<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leagues', function (Blueprint $table) {
            $table->unsignedTinyInteger('incoming_team_guest_limit')
                ->default(2)
                ->after('emoji');
        });

        DB::table('leagues')
            ->whereNull('incoming_team_guest_limit')
            ->update([
                'incoming_team_guest_limit' => 2,
            ]);
    }

    public function down(): void
    {
        Schema::table('leagues', function (Blueprint $table) {
            $table->dropColumn('incoming_team_guest_limit');
        });
    }
};
