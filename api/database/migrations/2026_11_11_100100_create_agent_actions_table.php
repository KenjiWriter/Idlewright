<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every call of a tool by an AI assistant: under which consent, which tool, what it was asked (cut short), what came of it in a line, how long it took and whether it failed.
 * The table only grows; it is what the page of the assistant shows and what answers "what did the assistant do".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agent_grant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->string('tool', 64);
            $table->text('input')->nullable();
            $table->string('summary', 500)->nullable();
            $table->boolean('ok');
            $table->unsignedInteger('ms');
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_actions');
    }
};
