<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Web push: the key pair of a game (VAPID: the public key goes to the browsers, the private one signs what the game sends; the private one is kept encrypted) and the subscriptions
 * of the devices of the drivers. The address of a subscription and its keys are secrets, like a token of a device, so they are kept encrypted; the hash of the address is what
 * is looked up by.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table): void {
            $table->string('push_public_key', 100)->nullable();
            $table->text('push_private_key')->nullable();
            $table->string('push_subject')->nullable();
        });

        Schema::create('push_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // sha256 of the address: one device is one subscription, and it moves to the driver who is signed in on it now.
            $table->char('endpoint_hash', 64)->unique();
            $table->text('endpoint');
            $table->text('p256dh');
            $table->text('auth');
            // The public key of the game that the browser subscribed with: when the key of the game changes, the subscription no longer works and is asked for again.
            $table->string('vapid_key', 100);
            $table->string('timezone', 64)->nullable();
            $table->string('locale', 10)->nullable();
            $table->string('ua_family', 16)->nullable();
            $table->timestamp('last_ok_at')->nullable();
            $table->unsignedSmallInteger('failures')->default(0);
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();

            $table->index(['game_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');

        Schema::table('games', function (Blueprint $table): void {
            $table->dropColumn(['push_public_key', 'push_private_key', 'push_subject']);
        });
    }
};
