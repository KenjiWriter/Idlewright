<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The bonuses that a driver put on a part (`App\Domain\Market\Tuning`), as JSON: a list with one entry for each place of the part, `{key, ppm, tries, fails}` for a
 * place that holds a bonus and null for an empty one. Null for a part that was never tuned, which is every part there is today.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parts', function (Blueprint $table): void {
            $table->text('tuning')->nullable()->after('affixes');
        });
    }

    public function down(): void
    {
        Schema::table('parts', function (Blueprint $table): void {
            $table->dropColumn('tuning');
        });
    }
};
