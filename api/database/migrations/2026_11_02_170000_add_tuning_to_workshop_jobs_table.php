<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A job of tuning a part makes one try at one place of it (`tune_place`, from 1), and may name the bonus to put there (`tune_key`: null for a draw, or for another
 * try at the bonus that the place holds). The draw itself is made when the mechanic is done.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshop_jobs', function (Blueprint $table): void {
            $table->unsignedTinyInteger('tune_place')->nullable()->after('upgrade_to');
            $table->string('tune_key', 64)->nullable()->after('tune_place');
        });
    }

    public function down(): void
    {
        Schema::table('workshop_jobs', function (Blueprint $table): void {
            $table->dropColumn(['tune_place', 'tune_key']);
        });
    }
};
