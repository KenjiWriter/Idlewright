<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The limit of active cars. A car that drives has a number (its slot, 1 for the first car, kept in order with no gaps); a car in storage has none, and its run stands
 * with the stop `stored`. Nothing is set for the cars that exist: the number is given the first time somebody looks (`Slots`), and the `legacy_slots` of a wallet (how many
 * cars a driver may keep on the road above what the rules allow, because they had them before there was a limit) is worked out the same way, the first time, and only ever
 * falls from then on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cars', function (Blueprint $table): void {
            $table->unsignedSmallInteger('slot_no')->nullable();
        });

        Schema::table('wallets', function (Blueprint $table): void {
            // Null until it is worked out.
            $table->unsignedSmallInteger('legacy_slots')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table): void {
            $table->dropColumn('legacy_slots');
        });

        Schema::table('cars', function (Blueprint $table): void {
            $table->dropColumn('slot_no');
        });
    }
};
