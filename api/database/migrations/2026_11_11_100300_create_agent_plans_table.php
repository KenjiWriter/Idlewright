<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The plans of the AI assistant (D8 of the design): what it wants to change and why, with targets in numbers, measured by the server on the live pack and on the plan,
 * and the decision of a person of the panel. A plan is bound to its block of changes and to the drafts it was made on by a hash; a change of a plan is a new version
 * with a new decision, and an approval holds for 24 hours.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_grant_id')->nullable()->constrained()->nullOnDelete();
            // The person on whose behalf the assistant made the plan.
            $table->foreignId('admin_id')->nullable()->constrained()->nullOnDelete();
            $table->string('client_name', 255);
            $table->unsignedSmallInteger('version')->default(1);
            $table->foreignId('previous_id')->nullable()->constrained('agent_plans')->nullOnDelete();
            $table->string('status', 12);
            $table->string('title', 200);
            $table->text('goal');
            $table->text('targets');
            $table->text('assumptions')->nullable();
            $table->text('strategy');
            $table->text('alternatives')->nullable();
            $table->text('levers');
            $table->longText('block');
            $table->longText('replacements');
            $table->longText('changes');
            $table->text('outside_levers')->nullable();
            $table->text('risks')->nullable();
            $table->text('unverified')->nullable();
            $table->string('content_hash', 64);
            $table->string('drafts_snapshot', 16);
            $table->string('measure_status', 10);
            $table->longText('measured')->nullable();
            $table->text('measure_error')->nullable();
            $table->timestamp('measured_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('approved_until')->nullable();
            $table->text('reject_reason')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('noted_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('noted_at')->nullable();
            $table->timestamps();

            $table->index(['game_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_plans');
    }
};
