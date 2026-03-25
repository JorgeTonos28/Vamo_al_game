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
        Schema::create('league_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('display_name');
            $table->unsignedTinyInteger('jersey_number')->nullable();
            $table->string('status')->default('active');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();

            $table->unique(['league_id', 'display_name']);
            $table->index(['league_id', 'status']);
        });

        Schema::create('league_player_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained()->cascadeOnDelete();
            $table->foreignId('referrer_player_id')->constrained('league_players')->cascadeOnDelete();
            $table->foreignId('referred_player_id')->constrained('league_players')->cascadeOnDelete();
            $table->unsignedInteger('credit_amount_cents')->default(20000);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['league_id', 'referred_player_id']);
        });

        Schema::create('league_cut_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('sessions_limit')->default(4);
            $table->json('game_days')->nullable();
            $table->unsignedTinyInteger('cut_day')->default(15);
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['league_id', 'effective_from']);
        });

        Schema::create('league_fee_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained()->cascadeOnDelete();
            $table->string('fee_type');
            $table->unsignedInteger('amount_cents');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['league_id', 'fee_type', 'effective_from']);
        });

        Schema::create('league_cuts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained()->cascadeOnDelete();
            $table->foreignId('league_cut_configuration_id')->nullable()->constrained('league_cut_configurations')->nullOnDelete();
            $table->string('label');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->date('due_on');
            $table->unsignedTinyInteger('sessions_limit');
            $table->json('game_days')->nullable();
            $table->unsignedInteger('member_fee_amount_cents');
            $table->unsignedInteger('guest_fee_amount_cents');
            $table->string('status')->default('open');
            $table->timestamps();

            $table->unique(['league_id', 'starts_on']);
            $table->index(['league_id', 'starts_on', 'ends_on']);
        });

        Schema::create('league_cut_player_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_cut_id')->constrained('league_cuts')->cascadeOnDelete();
            $table->foreignId('league_player_id')->constrained('league_players')->cascadeOnDelete();
            $table->unsignedInteger('carry_in_cents')->default(0);
            $table->unsignedInteger('amount_due_cents')->default(0);
            $table->unsignedInteger('amount_paid_cents')->default(0);
            $table->unsignedInteger('referral_credit_applied_cents')->default(0);
            $table->unsignedInteger('extra_credit_cents')->default(0);
            $table->string('status')->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('last_payment_at')->nullable();
            $table->timestamps();

            $table->unique(['league_cut_id', 'league_player_id']);
            $table->index(['league_cut_id', 'status']);
        });

        Schema::create('league_cut_player_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_cut_player_balance_id');
            $table->string('transaction_type');
            $table->integer('amount_cents');
            $table->string('note')->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('league_cut_player_balance_id', 'lcpt_balance_fk')
                ->references('id')
                ->on('league_cut_player_balances')
                ->cascadeOnDelete();
            $table->index(['league_cut_player_balance_id', 'transaction_type'], 'lcpt_balance_type_idx');
        });

        Schema::create('league_cut_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_cut_id')->constrained('league_cuts')->cascadeOnDelete();
            $table->string('expense_type')->default('custom');
            $table->string('name');
            $table->unsignedInteger('amount_cents');
            $table->boolean('is_system_generated')->default(false);
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('spent_on')->nullable();
            $table->timestamps();

            $table->index(['league_cut_id', 'expense_type']);
        });

        Schema::create('league_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained()->cascadeOnDelete();
            $table->foreignId('league_cut_id')->nullable()->constrained('league_cuts')->nullOnDelete();
            $table->date('session_date');
            $table->string('status')->default('arrival_open');
            $table->json('initial_pool')->nullable();
            $table->json('initial_queue')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('prepared_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['league_id', 'session_date']);
            $table->index(['league_id', 'status']);
        });

        Schema::create('league_session_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_session_id')->constrained('league_sessions')->cascadeOnDelete();
            $table->foreignId('league_player_id')->nullable()->constrained('league_players')->nullOnDelete();
            $table->string('guest_name')->nullable();
            $table->string('entry_type');
            $table->unsignedInteger('arrival_order');
            $table->boolean('current_cut_paid')->default(false);
            $table->boolean('guest_fee_paid')->default(false);
            $table->boolean('was_marked_paid_on_arrival')->default(false);
            $table->string('priority_bucket')->default('member_priority');
            $table->unsignedInteger('queue_seed')->nullable();
            $table->timestamps();

            $table->unique(['league_session_id', 'league_player_id']);
            $table->index(['league_session_id', 'entry_type', 'arrival_order'], 'lse_session_type_arrival_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('league_session_entries');
        Schema::dropIfExists('league_sessions');
        Schema::dropIfExists('league_cut_expenses');
        Schema::dropIfExists('league_cut_player_transactions');
        Schema::dropIfExists('league_cut_player_balances');
        Schema::dropIfExists('league_cuts');
        Schema::dropIfExists('league_fee_schedules');
        Schema::dropIfExists('league_cut_configurations');
        Schema::dropIfExists('league_player_referrals');
        Schema::dropIfExists('league_players');
    }
};
