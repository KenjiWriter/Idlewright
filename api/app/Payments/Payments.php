<?php

declare(strict_types=1);

namespace App\Payments;

use App\Exceptions\RuleRefusedException;
use App\Games\CurrentGame;
use App\Models\Game;

/**
 * Whether the game in play takes real money, with which provider, and by what rules. Three things must all be so: a provider is named in the configuration and is ready (set up,
 * in a mode this installation allows), and the game has payments turned on. The last is a decision of the people who run the network and, in production, one that waits for the
 * lawyer: a game that was never told says no there (and yes in development, where nothing is real).
 */
final class Payments
{
    /** What a driver has to confirm before they are sent to pay, always. The right of withdrawal adds one more when the game has the driver give it up (see `consents()`). */
    public const CONSENT_TERMS = 'accept_terms';

    public const CONSENT_ADULT = 'adult_or_guardian';

    public const CONSENT_WAIVER = 'acknowledge_digital_content';

    public const WITHDRAWAL_UNTIL_USED = 'until_used';

    public const WITHDRAWAL_WAIVED = 'waived';

    public function __construct(private readonly CurrentGame $games) {}

    /** The name of the provider in the configuration, or null when none is named. */
    public function providerKey(): ?string
    {
        $key = config('ride.premium.provider');

        return is_string($key) && $key !== '' ? $key : null;
    }

    /**
     * The provider of that name (the one of the configuration when left out).
     *
     * @throws PaymentsUnavailable when none is named, or the name is not one the game knows
     */
    public function provider(?string $key = null): PaymentProvider
    {
        return match ($key ?? $this->providerKey()) {
            'stripe' => app(StripeProvider::class),
            'fake' => app(FakeProvider::class),
            default => throw new PaymentsUnavailable('There is no payment provider.'),
        };
    }

    /** Whether a driver of the game in play can be sent to pay now. */
    public function available(): bool
    {
        try {
            return $this->enabledForGame() && $this->provider()->ready();
        } catch (PaymentsUnavailable) {
            return false;
        }
    }

    /** @throws RuleRefusedException `payments_unavailable` */
    public function assertAvailable(): void
    {
        if (! $this->available()) {
            throw new RuleRefusedException('payments_unavailable');
        }
    }

    /** The game's own say: its payments are turned on. A game that was never told is on outside production and off in it. */
    public function enabledForGame(): bool
    {
        $enabled = $this->game()?->payments['enabled'] ?? null;

        return is_bool($enabled) ? $enabled : ! app()->isProduction();
    }

    /** How the right of withdrawal is handled in the game in play (`until_used` or `waived`). */
    public function withdrawalMode(): string
    {
        $mode = $this->game()?->payments['withdrawal'] ?? config('ride.premium.payments.defaults.withdrawal');

        return $mode === self::WITHDRAWAL_WAIVED ? self::WITHDRAWAL_WAIVED : self::WITHDRAWAL_UNTIL_USED;
    }

    /**
     * What a driver may spend and have waiting, in the game in play: `per_day_minor` (in any 24 hours), `per_month_minor` (in any 30 days) and `max_open_attempts`.
     *
     * @return array{per_day_minor: int, per_month_minor: int, max_open_attempts: int}
     */
    public function limits(): array
    {
        $defaults = (array) config('ride.premium.payments.defaults.limits');
        $own = (array) ($this->game()?->payments['limits'] ?? []);

        return [
            'per_day_minor' => max(0, (int) ($own['per_day_minor'] ?? $defaults['per_day_minor'])),
            'per_month_minor' => max(0, (int) ($own['per_month_minor'] ?? $defaults['per_month_minor'])),
            'max_open_attempts' => max(1, (int) ($own['max_open_attempts'] ?? $defaults['max_open_attempts'])),
        ];
    }

    /**
     * The things a driver has to confirm before they are sent to pay, by the name the request uses.
     *
     * @return list<string>
     */
    public function consents(): array
    {
        return [self::CONSENT_TERMS, self::CONSENT_ADULT, ...($this->withdrawalMode() === self::WITHDRAWAL_WAIVED ? [self::CONSENT_WAIVER] : [])];
    }

    /**
     * What a client needs to know to offer a purchase: whether it can be made, whether it is with test money, and what the driver has to confirm.
     *
     * @return array{available: bool, test_mode: bool, withdrawal: string, consents: list<string>, limits: array{per_day_minor: int, per_month_minor: int, max_open_attempts: int}}
     */
    public function summary(): array
    {
        $available = $this->available();

        return [
            'available' => $available,
            'test_mode' => $available && ! $this->provider()->livemode(),
            'withdrawal' => $this->withdrawalMode(),
            'consents' => $this->consents(),
            'limits' => $this->limits(),
        ];
    }

    /** The game in play as a row (the first one is in play when none was put in force). */
    private function game(): ?Game
    {
        return $this->games->game() ?? Game::query()->find($this->games->id());
    }
}
