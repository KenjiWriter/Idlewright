<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The files of a release that the content of the panel has taken in (content:rebase): the base of the next merge, so that it sees only what the files changed since.
 * It is not a pack: a pack would give its documents to the definitions as their latest version, and the panel would show the files as what is published.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_upstreams', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->char('checksum', 64);           // of the content of the files, as a pack has it
            $table->string('source', 500);          // the folder the files were read from
            $table->string('base', 200);            // what the merge started from (a pack, or the files of an earlier rebase)
            $table->longText('documents');          // the text of every document by key, the manifest as `pack`
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_upstreams');
    }
};
