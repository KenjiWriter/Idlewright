<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What came of an approved plan of the AI assistant (D9 and D10 of the design): when its block was written as drafts and on whose behalf (through the bridge, so the answer
 * of the bridge it became is kept), the report of what was promised against what was measured on the drafts, whether the writing was undone, and the pack it asked for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_plans', function (Blueprint $table): void {
            $table->timestamp('executed_at')->nullable();
            $table->foreignId('executed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('ai_exchange_id')->nullable()->constrained('ai_exchanges')->nullOnDelete();
            $table->string('report_status', 10)->nullable();
            $table->longText('report')->nullable();
            $table->text('report_error')->nullable();
            $table->timestamp('reported_at')->nullable();
            $table->timestamp('reverted_at')->nullable();
            $table->foreignId('reverted_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('content_publication_id')->nullable()->constrained('content_publications')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('agent_plans', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('executed_by');
            $table->dropConstrainedForeignId('ai_exchange_id');
            $table->dropConstrainedForeignId('reverted_by');
            $table->dropConstrainedForeignId('content_publication_id');
            $table->dropColumn(['executed_at', 'report_status', 'report', 'report_error', 'reported_at', 'reverted_at']);
        });
    }
};
