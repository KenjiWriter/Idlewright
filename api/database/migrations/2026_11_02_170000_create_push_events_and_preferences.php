<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Web push, the part that decides when a driver is told: the events (a car that is about to stop, a car that has arrived, a job that is done) and what a driver wants to be told of.
 *
 * An event is a row for one thing (a kind, what it is about and when), so it is told once. For a car on the road it is a forecast: `due_ms` is the next moment the server looks at the car
 * (the moment it will stop, or the moment the forecast ends), and a forecast that is wrong is made again by the next look. A message that was sent is written down (`sent_at`, the
 * same for the events that went in one message), which is what the limits of a day and the gap between messages are counted from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 24);
            $table->string('ref_type', 24);
            $table->unsignedBigInteger('ref_id');
            $table->bigInteger('due_ms');
            // planned: waits for its time; sent: the driver was told; skipped: the driver was not told, and `reason` says why.
            $table->string('state', 12)->default('planned');
            $table->timestamp('sent_at')->nullable();
            $table->string('reason', 32)->nullable();
            $table->timestamps();

            // The same thing at the same moment is one event; a car that stops again later is another.
            $table->unique(['kind', 'ref_type', 'ref_id', 'due_ms']);
            $table->index(['state', 'due_ms']);
            $table->index(['user_id', 'sent_at']);
        });

        Schema::create('push_preferences', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            // What the driver switched on or off, by kind of message; a kind that is not here has the default of the game.
            $table->json('kinds')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_preferences');
        Schema::dropIfExists('push_events');
    }
};
