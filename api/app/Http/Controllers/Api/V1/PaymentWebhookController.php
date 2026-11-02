<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Payments\InvalidWebhook;
use App\Payments\PaymentEvents;
use App\Payments\Payments;
use App\Payments\PaymentsUnavailable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Where a payment provider tells the game what happened to a payment. It is not for a driver and has no game of its own (see the route): the signature is what lets a request
 * in, and the answer tells the provider whether to try again (a 5xx) or not. The body is read as it came, because the signature is over it, and is never written to the logs.
 */
final class PaymentWebhookController extends Controller
{
    /** The events of Stripe: a 400 for a request that is not from Stripe, a 503 while Stripe is not set up, a 200 for everything the game took in or has no use for. */
    public function stripe(Request $request, Payments $payments, PaymentEvents $events): JsonResponse
    {
        try {
            $provider = $payments->provider('stripe');
            $event = $provider->parseWebhook($request->getContent(), $request->header('Stripe-Signature'));
        } catch (InvalidWebhook $e) {
            Log::warning('A webhook of Stripe was refused.', ['why' => $e->getMessage()]);

            return response()->json(['message' => 'The signature is not valid.', 'code' => 'invalid_webhook'], 400);
        } catch (PaymentsUnavailable) {
            return response()->json(['message' => 'Payments are not set up.', 'code' => 'payments_unavailable'], 503);
        }

        if ($event === null) {
            return response()->json(['received' => true, 'result' => 'ignored']);
        }

        // The key of the account says the mode of the money, and an event of the other mode is not this account's business (a test event sent to the live endpoint).
        if ($event->livemode !== $provider->livemode()) {
            Log::warning('A webhook of Stripe was for the other mode.', ['event' => $event->id]);

            return response()->json(['received' => true, 'result' => 'other_mode']);
        }

        return response()->json(['received' => true, 'result' => $events->handle($event)]);
    }
}
