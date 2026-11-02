<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A purchase knows its game (the revenue of a game used to be read through the account, which a purchase loses when the account is erased), the attempt and the session it
 * came from, the mode it was paid in, the days of a pass, what was given back, and the consents that were given for it.
 *
 * The purchases that exist take the game of their account; one without an account (it was erased) cannot be told, and goes to the first game. No payment provider was
 * connected before this, so those are the purchases of the people who run the game.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table): void {
            $table->unsignedBigInteger('game_id')->default(1)->index()->after('id');
            $table->foreignId('attempt_id')->nullable()->after('user_id')->constrained('payment_attempts')->nullOnDelete();
            $table->string('provider_session', 128)->nullable()->after('provider_ref');
            // False for a payment made with test money: it is kept (it proves the flow) and never counted as revenue.
            $table->boolean('livemode')->default(true)->after('provider_session');
            $table->unsignedInteger('grant_days')->nullable()->after('coins');
            $table->unsignedInteger('refunded_amount_minor')->default(0)->after('amount_minor');
            // open, won or lost, once the payer disputed the payment with their bank.
            $table->string('dispute_state', 16)->nullable()->after('status');
            // Why a person has to look at it: account_erased (paid for an account that was erased in the meantime), partial_refund.
            $table->string('review_reason', 32)->nullable()->after('dispute_state');
            $table->string('terms_version', 32)->nullable();
            $table->timestamp('consent_at')->nullable();
            $table->timestamp('waiver_at')->nullable();
            $table->timestamp('adult_declared_at')->nullable();
        });

        DB::statement('update purchases set game_id = coalesce((select users.game_id from users where users.id = purchases.user_id), 1)');
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('attempt_id');
            $table->dropIndex(['game_id']);
            $table->dropColumn(['game_id', 'provider_session', 'livemode', 'grant_days', 'refunded_amount_minor', 'dispute_state', 'review_reason', 'terms_version', 'consent_at', 'waiver_at', 'adult_declared_at']);
        });
    }
};
