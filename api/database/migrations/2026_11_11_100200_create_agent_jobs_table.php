<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the AI assistant had the simulators count: which study, on which content (the live pack, the drafts, or the drafts with its scratch), what came of it and how
 * long it took. A long study is done by the worker of the queue `agent`, and the assistant picks it up by its number; the same study on the same content is answered
 * from here, because the simulators are not random.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_jobs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agent_grant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->string('study', 40);
            $table->string('variant', 10);
            $table->text('options');
            // The documents of the scratch at the time it was asked for, so that the worker counts what the assistant asked about.
            $table->longText('replacements')->nullable();
            $table->string('key', 64);
            $table->string('status', 10);
            $table->longText('result')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('ms')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['game_id', 'key']);
            $table->index(['game_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_jobs');
    }
};
