<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AccountRefusedException;
use App\Models\ConsentRecord;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * The consent of a driver to the terms and the privacy policy, and asking for it again when they change.
 *
 * The texts have a version, the day they were last changed (`ride.legal.version`), and a second one, the day of the last
 * change that needs a new consent (`ride.legal.consent_version`; when it is not set, every change does). A driver who
 * accepted a version older than that one is asked again, and cannot go on until they have: the API answers 403
 * `consent_required` (see EnsureConsent). A driver who accepted before the texts had a version has none and is asked too.
 * The versions are days written as `2026-10-23`, so they compare as text.
 */
final class Consent
{
    /** The version of the texts as they are now: it is what a driver is shown, and what they accept. */
    public static function current(): string
    {
        return (string) config('ride.legal.version');
    }

    /** The oldest version a driver can have accepted and not be asked again. */
    public static function required(): string
    {
        return (string) (config('ride.legal.consent_version') ?: self::current());
    }

    /** Whether an accepted version (none: accepted before the texts had one) is older than the one that is required. */
    public static function isOutdated(?string $accepted): bool
    {
        return $accepted === null || $accepted === '' || strcmp($accepted, self::required()) < 0;
    }

    /**
     * Writes that the driver accepted the texts as they are now. Accepted with the first sign-in, it also sets the moments
     * of the first consent (see Accounts::link); accepted again, only the version moves on. Every acceptance is a record.
     */
    public function accept(User $user, string $source, ?CarbonInterface $at = null): void
    {
        $at ??= now();

        DB::transaction(function () use ($user, $source, $at): void {
            $user->forceFill(['terms_version' => self::current()])->save();

            $user->consents()->create(['terms_version' => self::current(), 'source' => $source, 'accepted_at' => $at]);
        });
    }

    /** The refusal a driver gets for what they do before they accepted the texts as they are now. */
    public function refusal(User $user): AccountRefusedException
    {
        return AccountRefusedException::forbidden('consent_required', ['accepted_version' => $user->terms_version, 'current_version' => self::current()]);
    }

    /** What the screen that asks again needs to know. @return array{required: bool, accepted_version: string|null, current_version: string} */
    public function state(User $user): array
    {
        return [
            'required' => $user->needsConsent(),
            'accepted_version' => $user->terms_version,
            'current_version' => self::current(),
        ];
    }

    /** @return list<array{terms_version: string|null, source: string, accepted_at: string|null}> */
    public function history(User $user): array
    {
        return $user->consents()->orderBy('accepted_at')->orderBy('id')->get()->map(fn (ConsentRecord $record): array => [
            'terms_version' => $record->terms_version,
            'source' => $record->source,
            'accepted_at' => $record->accepted_at?->toIso8601String(),
        ])->all();
    }
}
