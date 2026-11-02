<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** An answer that was pasted knows the instruction it answers. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_exchanges', function (Blueprint $table): void {
            $table->foreignId('parent_id')->nullable()->after('kind')->constrained('ai_exchanges')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ai_exchanges', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};
