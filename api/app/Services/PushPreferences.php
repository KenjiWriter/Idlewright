<?php

declare(strict_types=1);

namespace App\Services;

use App\Content\Rules;
use App\Models\PushEvent;
use App\Models\PushPreference;
use App\Models\User;

/** What a driver wants to be told of by push: each kind of message is on or off, and a kind the driver has not chosen has the default of the game (`push.default_kinds`). */
final class PushPreferences
{
    public function __construct(private readonly Rules $rules) {}

    /**
     * Every kind with whether it is on for the driver.
     *
     * @return array<string, bool>
     */
    public function kindsOf(User $user): array
    {
        $chosen = PushPreference::query()->find($user->id)?->kinds ?? [];
        $defaults = $this->rules->push()['default_kinds'] ?? [];
        $kinds = [];

        foreach (PushEvent::KINDS as $kind) {
            $kinds[$kind] = (bool) ($chosen[$kind] ?? $defaults[$kind] ?? false);
        }

        return $kinds;
    }

    public function enabled(User $user, string $kind): bool
    {
        return $this->kindsOf($user)[$kind] ?? false;
    }

    /**
     * Saves what the driver chose (only the kinds that are given change).
     *
     * @param  array<string, bool>  $choice
     * @return array<string, bool> every kind with whether it is on
     */
    public function save(User $user, array $choice): array
    {
        $chosen = PushPreference::query()->find($user->id)?->kinds ?? [];

        foreach (PushEvent::KINDS as $kind) {
            if (array_key_exists($kind, $choice)) {
                $chosen[$kind] = (bool) $choice[$kind];
            }
        }

        PushPreference::query()->updateOrCreate(['user_id' => $user->id], ['kinds' => $chosen]);

        return $this->kindsOf($user);
    }
}
