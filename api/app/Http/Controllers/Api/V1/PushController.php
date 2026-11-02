<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\RuleRefusedException;
use App\Http\Controllers\Controller;
use App\Models\PushEvent;
use App\Services\Push;
use App\Services\PushPreferences;
use App\Services\PushSubscriptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Web push: what the app needs to ask a browser to subscribe (`GET /push/config`), the subscription of a device (`PUT` and `DELETE /push/subscription`) and a message to the
 * driver's own devices to see that it works (`POST /push/test`).
 */
final class PushController extends Controller
{
    public function __construct(private readonly Push $push, private readonly PushSubscriptions $subscriptions, private readonly PushPreferences $preferences) {}

    /** Whether the game can send push messages, the public key a browser subscribes with, and how many devices of the driver are subscribed. */
    public function config(Request $request): JsonResponse
    {
        $key = $this->push->publicKey();

        return response()->json(['data' => [
            'enabled' => $key !== null,
            'public_key' => $key,
            'devices' => $this->subscriptions->devices($request->user()),
        ]])->header('Cache-Control', 'no-store');
    }

    /** Saves the subscription of the device the driver is on (what `PushSubscription.toJSON()` of the browser says). The same call again changes nothing. */
    public function save(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:2048'],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string', 'max:128'],
            'keys.auth' => ['required', 'string', 'max:64'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'locale' => ['nullable', 'string', 'max:10'],
        ]);

        $user = $request->user();
        $this->subscriptions->save($user, [
            'endpoint' => $data['endpoint'],
            'p256dh' => $data['keys']['p256dh'],
            'auth' => $data['keys']['auth'],
            'timezone' => $data['timezone'] ?? null,
            'locale' => $data['locale'] ?? null,
        ], $request->userAgent());

        return response()->json(['data' => ['subscribed' => true, 'devices' => $this->subscriptions->devices($user)]])->header('Cache-Control', 'no-store');
    }

    /** Takes the subscription of the device away (the driver switched it off, or signed out of the device). */
    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:2048']]);
        $user = $request->user();
        $removed = $this->subscriptions->remove($user, $data['endpoint']);

        return response()->json(['data' => ['removed' => $removed, 'devices' => $this->subscriptions->devices($user)]])->header('Cache-Control', 'no-store');
    }

    /** What the driver is told of: each kind of message with whether it is on (the default of the game for a kind the driver has not chosen). */
    public function preferences(Request $request): JsonResponse
    {
        return response()->json(['data' => ['kinds' => $this->preferences->kindsOf($request->user())]])->header('Cache-Control', 'no-store');
    }

    /** Switches kinds of messages on or off; the kinds that are not given stay as they were. */
    public function updatePreferences(Request $request): JsonResponse
    {
        $rules = ['kinds' => ['required', 'array', 'min:1']];

        foreach (PushEvent::KINDS as $kind) {
            $rules["kinds.{$kind}"] = ['sometimes', 'boolean'];
        }

        $data = $request->validate($rules);

        return response()->json(['data' => ['kinds' => $this->preferences->save($request->user(), $data['kinds'])]])->header('Cache-Control', 'no-store');
    }

    /** A message to the devices of the driver, to see that it arrives. It goes through the queue like every other one. */
    public function test(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($this->push->publicKey() === null) {
            throw new RuleRefusedException('push_unavailable');
        }

        $queued = $this->push->notify($user, 'test', '/garage');

        if ($queued === 0) {
            throw new RuleRefusedException('push_no_device');
        }

        return response()->json(['data' => ['queued' => $queued]])->header('Cache-Control', 'no-store');
    }
}
