<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\AccountIdentity;
use App\Models\User;
use App\Services\Accounts;
use App\Services\Consent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
final class MeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'locale' => $this->locale,
            'is_guest' => $this->isGuest(),
            'has_recovery_code' => $this->hasRecoveryCode(),
            // The ways this account can be signed in to. The address is masked: it is enough to tell which one it is.
            'identities' => $this->identities->sortBy('id')->map(fn (AccountIdentity $identity): array => [
                'provider' => $identity->provider,
                'email' => Accounts::maskEmail($identity->email),
            ])->values()->all(),
            // Whether the terms changed since the driver accepted them: if so, the game waits until they accept again.
            'consent' => app(Consent::class)->state($this->resource),
        ];
    }
}
