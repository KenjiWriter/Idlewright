<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A gig that a driver took: a round trip of one of their cars with people or a load. The offers themselves are not stored (the board is made again from a seed);
 * what the driver took is, with every number frozen at the moment they took it, so that nothing the rules say later changes a gig on the road.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gigs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('race_run_id')->constrained('race_runs')->cascadeOnDelete();

            // The offer: the key it had on the board (a driver takes an offer once), the template it was drawn from and what it asked.
            $table->string('offer_key', 96);
            $table->string('template', 64);
            $table->string('kind', 32);
            $table->string('region', 64);
            $table->string('to_region', 64)->nullable();     // null: a trip in the city
            $table->unsignedInteger('distance_km');          // the whole trip, there and back
            $table->unsignedInteger('amount');               // people or kilograms
            $table->string('capacity', 12);                  // seats, cargo_kg
            $table->string('rarity', 16);
            $table->string('phase', 8);

            // What it takes and what it pays, as it was when the driver took it.
            $table->unsignedInteger('fuel_ml');              // taken from the tank when the car leaves
            $table->bigInteger('start_ms');
            $table->bigInteger('finish_ms');
            $table->unsignedBigInteger('reward_cents');      // all of it, with the bonus of the VIP account
            $table->unsignedBigInteger('bonus_cents')->default(0);
            $table->unsignedInteger('xp')->default(0);
            $table->unsignedInteger('car_xp')->default(0);

            $table->string('status', 12)->default('active'); // active, done, cancelled
            $table->bigInteger('done_ms')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'offer_key']);
            $table->index(['user_id', 'status']);
            $table->index(['race_run_id', 'status']);
            $table->index(['user_id', 'start_ms']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gigs');
    }
};
