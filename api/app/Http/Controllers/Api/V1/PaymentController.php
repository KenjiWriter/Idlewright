<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Payments\Checkout;
use App\Payments\Payments;
use App\Services\RunService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Paying with real money for a pack of coins or the pass: the driver says what they want and what they confirm, and is sent to the page of the payment provider; coming back,
 * they ask how it went. The money itself is learned from the provider (the webhook), never from the driver.
 */
final class PaymentController extends Controller
{
    public function __construct(private readonly Checkout $checkout) {}

    /**
     * Starts a purchase of `product` (the slug of a pack, or `infinity`) and answers with where to send the driver. `request_id` makes a repeated request the same purchase
     * (200, `repeated: true`). The confirmations the game asks for (`GET /premium` says which: `payments.consents`) are booleans of the same names.
     */
    public function checkout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product' => ['required', 'string', 'max:64'],
            'request_id' => ['required', 'string', 'max:'.RunService::MAX_REQUEST_ID_LENGTH],
            Payments::CONSENT_TERMS => ['sometimes', 'boolean'],
            Payments::CONSENT_ADULT => ['sometimes', 'boolean'],
            Payments::CONSENT_WAIVER => ['sometimes', 'boolean'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $consents = [];

        foreach ([Payments::CONSENT_TERMS, Payments::CONSENT_ADULT, Payments::CONSENT_WAIVER] as $name) {
            $consents[$name] = $request->boolean($name);
        }

        [$attempt, $created] = $this->checkout->start($user, $data['product'], $data['request_id'], $consents);

        return $this->respond($attempt, $created ? 201 : 200, ['repeated' => ! $created]);
    }

    /** How a purchase of the driver stands (`pending`, `paid`...), asking the provider when the webhook has not said yet. Another driver's purchase is a 404. */
    public function status(Request $request, string $attempt): JsonResponse
    {
        $found = PaymentAttempt::query()->where('user_id', (int) $request->user()->getAuthIdentifier())->where('public_id', $attempt)->firstOrFail();

        return $this->respond($this->checkout->refresh($found));
    }

    /** @param  array<string, mixed>  $extra  more keys of the top level of the answer */
    private function respond(PaymentAttempt $attempt, int $status = 200, array $extra = []): JsonResponse
    {
        return response()->json(['data' => $this->checkout->view($attempt), ...$extra], $status)->header('Cache-Control', 'no-store');
    }
}
