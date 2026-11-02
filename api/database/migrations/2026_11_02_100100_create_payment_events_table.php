<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a payment provider told the game (stage S1 of the payments): one row per event, whatever the provider's own id for it is, so an event told twice (they are, by
 * design) is worked once, and an event that arrived before the thing it speaks of (a refund before the payment) is kept and worked again later.
 *
 * Only the facts the game needs are kept (`data`): ids, amounts, the mode, never the e-mail or the address of the payer, which are in the provider's own books.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_events', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 32);
            $table->string('event_id', 128);
            // The kind of event in the words of the game (payment.paid, payment.refunded...) and in the provider's own.
            $table->string('type', 32);
            $table->string('source_type', 64);
            $table->boolean('livemode')->default(true);
            $table->unsignedBigInteger('game_id')->nullable();
            // The thing the event is about: the session or the payment at the provider.
            $table->string('object_ref', 128)->nullable();
            // received, processed, ignored (nothing to do), deferred (waits for what it speaks of), rejected (refused on purpose), failed (an error: the provider tries again).
            $table->string('state', 16);
            $table->unsignedSmallInteger('tries')->default(0);
            $table->string('note', 255)->nullable();
            $table->json('data')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'event_id']);
            $table->index(['state', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
    }
};
