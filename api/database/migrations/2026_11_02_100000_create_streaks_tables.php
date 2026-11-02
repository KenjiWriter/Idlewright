<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The streak of days of a driver: one row for the driver, which holds how long it is, the best it was, the shields they keep and the last day that was settled, and one
 * row for every day that counted (or was saved by a shield), which holds what the day pays, as the rules said when it counted, and whether it was taken.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_streaks', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('current')->default(0);
            $table->unsignedInteger('best')->default(0);
            // The place in the cycle of rewards: the day of the cycle that the last counted day paid (0: none yet).
            $table->unsignedSmallInteger('cycle_day')->default(0);
            $table->unsignedSmallInteger('shields')->default(0);
            // The last day of the missions (2026-10-30) that is settled: counted, saved by a shield or missed.
            $table->string('last_day', 10)->nullable();
            // The streak that was broken last, and the day it was found out: for the summary of the break.
            $table->unsignedInteger('lost')->default(0);
            $table->string('lost_day', 10)->nullable();
            $table->timestamps();
        });

        Schema::create('streak_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('day_key', 10);
            // goal: the goal of the day was done; shield: the day was missed and a shield saved the streak.
            $table->string('kind', 8);
            // The length of the streak on that day, and the day of the cycle it paid.
            $table->unsignedInteger('streak');
            $table->unsignedSmallInteger('cycle_day')->default(0);
            // What the day pays, as it was worked out when it counted: cash_cents, xp and items.
            $table->json('reward')->nullable();
            $table->timestamp('claimed_at')->nullable();
            // Taken by the server after the day ended, not by the driver.
            $table->boolean('auto_claimed')->default(false);
            $table->json('claim_result')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'day_key']);
            $table->index(['user_id', 'claimed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('streak_days');
        Schema::dropIfExists('user_streaks');
    }
};
