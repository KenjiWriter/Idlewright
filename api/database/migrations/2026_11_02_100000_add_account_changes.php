<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What changing the sign-ins of an account needs to remember.
 *
 * - `account_tokens.device_token_id`: the device that asked for a link that proves who the driver is (`reauth`). The link
 *   may be opened in any browser, but the proof it gives belongs to the device that asked.
 * - `users.email_changed_at`: when the e-mail of the account was last changed, for the pause between two changes.
 * - `consent_records`: every time a driver accepted the terms and confirmed their age, with the version of the texts they were
 *   shown. `users.terms_accepted_at` stays the first consent and `users.terms_version` the latest version; this is the
 *   record that shows which version was accepted when. Accounts that consented before are given the one record they have.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_tokens', function (Blueprint $table): void {
            $table->unsignedBigInteger('device_token_id')->nullable();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('email_changed_at')->nullable();
        });

        Schema::create('consent_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // The day the texts were last changed, as it was when the driver accepted them. Null: accepted before the texts had a version.
            $table->string('terms_version', 32)->nullable();

            // `link`: with the first sign-in linked. `reconsent`: after the texts changed.
            $table->string('source', 16);

            $table->timestamp('accepted_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'accepted_at']);
        });

        DB::table('users')->whereNotNull('terms_accepted_at')->orderBy('id')->each(function (object $user): void {
            DB::table('consent_records')->insert([
                'user_id' => $user->id,
                'terms_version' => $user->terms_version,
                'source' => 'link',
                'accepted_at' => $user->terms_accepted_at,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_records');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('email_changed_at');
        });

        Schema::table('account_tokens', function (Blueprint $table): void {
            $table->dropColumn('device_token_id');
        });
    }
};
