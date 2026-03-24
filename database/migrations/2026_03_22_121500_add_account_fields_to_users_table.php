<?php

use App\Enums\AccountRole;
use App\Support\UserName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('id');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('document_id')->nullable()->unique()->after('name');
            $table->string('phone')->nullable()->after('document_id');
            $table->string('address')->nullable()->after('phone');
            $table->string('account_role')->default(AccountRole::Guest->value)->after('avatar');
            $table->foreignId('invited_by_user_id')->nullable()->after('account_role')->constrained('users')->nullOnDelete();
            $table->timestamp('invited_at')->nullable()->after('invited_by_user_id');
            $table->timestamp('onboarded_at')->nullable()->after('invited_at');
        });

        DB::table('users')
            ->select(['id', 'name', 'created_at'])
            ->orderBy('id')
            ->get()
            ->each(function (object $user): void {
                $parsed = UserName::fromFullName((string) $user->name);

                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'first_name' => $parsed['first_name'],
                        'last_name' => $parsed['last_name'],
                        'invited_at' => $user->created_at,
                        'onboarded_at' => $user->created_at,
                    ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invited_by_user_id');
            $table->dropUnique(['document_id']);
            $table->dropColumn([
                'first_name',
                'last_name',
                'document_id',
                'phone',
                'address',
                'account_role',
                'invited_at',
                'onboarded_at',
            ]);
        });
    }
};
