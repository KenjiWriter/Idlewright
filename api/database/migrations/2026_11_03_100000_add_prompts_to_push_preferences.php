<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How many times the card that asks a driver about notifications was put off, and when last: the server keeps the count (the rules say how many times and how long apart), so that the
 * driver is asked the same way on every device and the browser does not decide it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('push_preferences', function (Blueprint $table): void {
            $table->unsignedSmallInteger('prompts')->default(0);
            $table->timestamp('prompted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('push_preferences', function (Blueprint $table): void {
            $table->dropColumn(['prompts', 'prompted_at']);
        });
    }
};
