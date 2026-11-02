<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The settings of the payments of a game (`games.payments`: whether it takes real money at all, how the right of withdrawal is handled, the limits of spending), and the
 * block of buying on an account (set when the payer disputes a payment with their bank).
 *
 * `games.payments` is empty for every game: a game takes no payments until somebody turns them on, and in production that is a decision that comes after the lawyer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table): void {
            $table->json('payments')->nullable();
        });

        Schema::table('wallets', function (Blueprint $table): void {
            $table->timestamp('purchases_blocked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table): void {
            $table->dropColumn('purchases_blocked_at');
        });

        Schema::table('games', function (Blueprint $table): void {
            $table->dropColumn('payments');
        });
    }
};
