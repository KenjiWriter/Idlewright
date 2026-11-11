<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The consents that people of the panel give an AI assistant (a client of OAuth, such as the Claude app): in which game it works, at which level, until when, and on whose
 * behalf. A key of the assistant is good only while a consent of its client and its person is in force, so ending a consent ends what the assistant can do at once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_grants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->uuid('oauth_client_id')->index();
            $table->string('client_name', 255);
            $table->unsignedTinyInteger('level');
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['admin_id', 'oauth_client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_grants');
    }
};
