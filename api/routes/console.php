<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Guests nobody returns to expire after ride.guest_expiry_days days.
Schedule::command('accounts:prune-guests')->dailyAt('03:30')->withoutOverlapping();

// The one-time secrets of the account flows live for minutes; the spent ones are only kept for a day.
Schedule::command('accounts:prune-tokens')->hourly()->withoutOverlapping();

// The sets of missions of days, weeks and months that are long over are removed; what was done and not taken is taken first, whenever the driver looks.
Schedule::command('missions:prune')->dailyAt('03:50')->withoutOverlapping();

// A mail that could not be sent is kept in failed_jobs, with the address it was for, so it can be tried again. A week is
// enough to notice and retry it; after that the address does not need to stay in the database.
Schedule::command('queue:prune-failed --hours=168')->dailyAt('03:45')->withoutOverlapping();

// Push messages: what happened to the drivers who are away is looked for every minute, in every game that has push.
Schedule::command('push:send-due')->everyMinute()->withoutOverlapping();

// Pages of payment that nobody paid on, and the old events of the payment providers, go after a month and thirteen months; an event that came before its payment is worked again
// every ten minutes for three days; the purchases themselves go when the years the shop has to keep them for are over.
Schedule::command('payments:prune')->dailyAt('03:55')->withoutOverlapping();
Schedule::command('payments:reprocess')->everyTenMinutes()->withoutOverlapping();
Schedule::command('purchases:prune')->dailyAt('04:05')->withoutOverlapping();
