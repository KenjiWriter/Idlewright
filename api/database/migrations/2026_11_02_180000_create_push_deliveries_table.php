<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which push messages were opened. A message carries a secret number (`nid`) that the service worker sends back when the notification is tapped, with no sign-in (the worker has no
 * token of the driver), so that it can be told how many messages are opened and whether the driver came back after them. The number cannot be guessed and is of no use to anyone
 * else: it says only that this message was opened.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('nid', 32)->unique();
            $table->string('kind', 24);
            $table->timestamp('opened_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_deliveries');
    }
};
