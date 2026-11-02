<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The attempts to pay (P3 of the paid features, stage S1): a driver chose a pack or the pass, and the game asked a payment provider for a page where they pay. The row keeps
 * what the driver SAW (the product, the coins or days, the price) so that what the provider reports is checked against it and what is paid out is that, whatever the rules say
 * by then, and the consents given before the redirect (the evidence of them in a dispute).
 *
 * An attempt that is not paid says nothing about money and goes after a month; a paid one stays with its purchase.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_attempts', function (Blueprint $table): void {
            $table->id();
            // What the client and the provider call it: the reference that comes back with the payment. Not the id, which counts the attempts of the network.
            $table->ulid('public_id')->unique();
            $table->foreignId('game_id')->constrained();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Makes a repeated request one attempt (the client sends the same one again when it does not know whether the first arrived).
            $table->string('request_id', 64);
            $table->string('product', 64);
            $table->string('kind', 8);
            $table->unsignedInteger('coins')->default(0);
            $table->unsignedInteger('grant_days')->nullable();
            $table->unsignedInteger('amount_minor');
            $table->string('currency', 3);
            $table->string('provider', 32);
            $table->string('provider_session', 128)->nullable();
            // The payment at the provider (a PaymentIntent for Stripe), known once it is made.
            $table->string('provider_ref', 128)->nullable();
            $table->text('checkout_url')->nullable();
            $table->string('state', 16);
            $table->boolean('livemode')->default(true);
            $table->string('locale', 8)->nullable();
            // The consents of the driver before they were sent to pay: the version of the texts and the moments.
            $table->string('terms_version', 32)->nullable();
            $table->timestamp('consent_at')->nullable();
            $table->timestamp('waiver_at')->nullable();
            $table->timestamp('adult_declared_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_session']);
            $table->unique(['user_id', 'request_id']);
            $table->index(['user_id', 'state']);
            $table->index(['state', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_attempts');
    }
};
