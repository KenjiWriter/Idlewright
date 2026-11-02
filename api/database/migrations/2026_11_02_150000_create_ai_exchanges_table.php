<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What passes between a person and an AI through the panel: the instruction that was made for the AI (what it contained, which is what an answer to it may change) and,
 * later, the answers that were pasted back with what they did. An instruction is kept by its code, which the AI copies into its answer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_exchanges', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('game_id')->index();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            // The code the instruction carries and the answer copies back.
            $table->string('code', 12)->unique();
            // brief (an instruction for the AI) or paste (an answer from it).
            $table->string('kind', 8);
            // What the instruction contained: kind:part, kind:vehicle, rules:starter, rules:starter#fuel.
            $table->json('scope');
            $table->string('locale', 8)->nullable();
            $table->string('pack', 32)->nullable();
            // The mark of the content the instruction was made from.
            $table->string('snapshot', 8)->nullable();
            // The same choices on the same content make the same instruction, so they are the same row.
            $table->string('selection', 64)->nullable()->index();
            $table->unsignedInteger('bytes')->default(0);
            $table->unsignedInteger('tokens')->default(0);
            $table->string('sha256', 64)->nullable();
            // prepared, checked, applied, reverted or refused.
            $table->string('status', 12)->default('prepared');
            // The text of an answer that was pasted.
            $table->mediumText('text')->nullable();
            $table->json('resolved')->nullable();
            $table->json('before')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('reverted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_exchanges');
    }
};
