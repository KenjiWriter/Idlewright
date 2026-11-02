<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transmutation of the rarity of a part: the part keeps when it was transmuted (a transmuted part is not used to merge), and every transmutation is written down with what
 * it cost, once for a request id of the driver.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parts', function (Blueprint $table): void {
            $table->timestamp('transmuted_at')->nullable();
        });

        Schema::create('part_transmutations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // The part can be gone later (sold with a car is not a thing, but a part can be used up), so it is only remembered.
            $table->unsignedBigInteger('part_id');
            $table->string('part_slug', 64);
            $table->unsignedTinyInteger('from_rarity');
            $table->unsignedTinyInteger('to_rarity');
            $table->unsignedInteger('coins')->default(0);
            $table->unsignedBigInteger('cash_cents')->default(0);
            $table->string('request_id', 64);
            $table->timestamps();
            $table->unique(['user_id', 'request_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('part_transmutations');

        Schema::table('parts', function (Blueprint $table): void {
            $table->dropColumn('transmuted_at');
        });
    }
};
