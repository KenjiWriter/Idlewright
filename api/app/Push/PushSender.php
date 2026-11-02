<?php

declare(strict_types=1);

namespace App\Push;

use App\Models\Game;
use App\Models\PushSubscription;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Sends one message to one subscription: encrypts it for the browser, signs the request as the game, and reads what the push service says. It decides nothing about whether a
 * driver should be told (that is `Push`); it only does the request and says what came of it.
 */
final class PushSender
{
    /** @return array{outcome: PushOutcome, status: int, retry_after: int} */
    public function send(Game $game, PushSubscription $subscription, PushMessage $message): array
    {
        $public = $game->push_public_key;
        $private = $game->push_private_key;
        $browserPoint = Base64Url::decode((string) $subscription->p256dh);
        $secret = Base64Url::decode((string) $subscription->auth);

        // A subscription made with another key of the game cannot be served: the push service answers 403 to it, so it is not even tried.
        if ($public === null || $private === null || $browserPoint === null || $secret === null || $subscription->vapid_key !== $public) {
            return $this->result(PushOutcome::Rejected, 0);
        }

        $endpoint = (string) $subscription->endpoint;
        $body = WebPushEncryption::encrypt($message->content(), $browserPoint, $secret);

        try {
            $response = Http::withHeaders([
                'Authorization' => Vapid::authorization($endpoint, $this->subject($game), $public, $private, time()),
                'Content-Encoding' => 'aes128gcm',
                'TTL' => (string) $message->ttl,
                'Urgency' => $message->urgency,
                'Topic' => $message->topic(),
            ])->withBody($body, 'application/octet-stream')->timeout(10)->post($endpoint);
        } catch (ConnectionException) {
            return $this->result(PushOutcome::Retry, 0, 60);
        }

        return $this->read($response);
    }

    /** The address that the push service can write to about the game: the `sub` of the token. */
    public function subject(Game $game): string
    {
        return $game->push_subject ?? PushKeys::defaultSubject($game);
    }

    /** @return array{outcome: PushOutcome, status: int, retry_after: int} */
    private function read(Response $response): array
    {
        $status = $response->status();

        return match (true) {
            $status >= 200 && $status < 300 => $this->result(PushOutcome::Sent, $status),
            $status === 404 || $status === 410 => $this->result(PushOutcome::Gone, $status),
            $status === 429 || $status >= 500 => $this->result(PushOutcome::Retry, $status, max(60, min(3600, (int) $response->header('Retry-After')))),
            default => $this->result(PushOutcome::Rejected, $status),
        };
    }

    /** @return array{outcome: PushOutcome, status: int, retry_after: int} */
    private function result(PushOutcome $outcome, int $status, int $retryAfter = 0): array
    {
        return ['outcome' => $outcome, 'status' => $status, 'retry_after' => $retryAfter];
    }
}
