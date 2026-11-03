<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The night run: what the police made of a gig. Whether a night run is stopped and how it ends is drawn when it is taken, from the server's secret, and frozen here with the
 * numbers it is paid and fined by, so that nothing the rules say later changes it. The arrest is not a state of the account but of this row: the driver is arrested from the
 * moment of the stop until `arrest_until_ms`, or until they pay the bail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gigs', function (Blueprint $table): void {
            $table->unsignedInteger('police_control_ppm')->nullable()->after('car_xp'); // the chance of a stop that the driver was shown
            $table->string('police_outcome', 12)->nullable()->after('police_control_ppm'); // clean, escaped, arrested: null for a gig that the police do not look at
            $table->bigInteger('police_at_ms')->nullable()->after('police_outcome');       // the moment of the stop, when there is one
            $table->bigInteger('arrest_until_ms')->nullable()->after('police_at_ms');
            $table->bigInteger('bailed_ms')->nullable()->after('arrest_until_ms');
            $table->unsignedBigInteger('fine_cents')->nullable()->after('bailed_ms');
            $table->unsignedBigInteger('bail_cents')->nullable()->after('fine_cents');
            $table->unsignedBigInteger('paid_cents')->nullable()->after('bail_cents');      // what the gig was paid when it ended (null while it is on the road)
            $table->unsignedBigInteger('fine_paid_cents')->nullable()->after('paid_cents');

            $table->index(['user_id', 'police_outcome', 'police_at_ms']);
        });
    }

    public function down(): void
    {
        Schema::table('gigs', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'police_outcome', 'police_at_ms']);
            $table->dropColumn(['police_control_ppm', 'police_outcome', 'police_at_ms', 'arrest_until_ms', 'bailed_ms', 'fine_cents', 'bail_cents', 'paid_cents', 'fine_paid_cents']);
        });
    }
};
