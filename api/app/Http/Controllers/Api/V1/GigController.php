<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\GigPolice;
use App\Services\Gigs;
use App\Services\Ledger;
use App\Services\RunService;
use App\Services\Workshop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The gigs of a driver: the board of the city a car stands in, taking an offer with a car, and taking it back in the first seconds. Looking brings the gigs up to date
 * (a car that is back is on its track, and paid), so the answer is true only at the moment of the call and is never cached.
 */
final class GigController extends Controller
{
    public function __construct(
        private readonly Gigs $gigs,
        private readonly GigPolice $police,
        private readonly Workshop $workshop,
        private readonly Ledger $ledger,
    ) {}

    /** The board for a car (`car`, the one the driver plays when left out): the offers with what each would pay and take, and why the car cannot take one. */
    public function show(Request $request): JsonResponse
    {
        $data = $request->validate(['car' => ['nullable', 'integer', 'min:1']]);
        $userId = $this->userId($request);
        $this->workshop->advance($userId);

        return $this->respond($userId, $data['car'] ?? null);
    }

    /**
     * Takes an offer (`offer`, its key from the board) with a car (`car`, the one the driver plays when left out). The car earns until it leaves, then stands for the time
     * of the trip. Taking the same offer again changes nothing and answers with `repeated: true`.
     */
    public function take(Request $request): JsonResponse
    {
        $data = $request->validate(['offer' => ['required', 'string', 'max:96'], 'car' => ['nullable', 'integer', 'min:1']]);
        $userId = $this->userId($request);
        $this->workshop->advance($userId);
        $taken = $this->gigs->take($userId, $data['offer'], $data['car'] ?? null);

        return $this->respond($userId, $data['car'] ?? null, $taken['repeated'] ? 200 : 201, ['gig' => $this->gigs->gigView($taken['gig']), 'repeated' => $taken['repeated']]);
    }

    /** Takes a gig back in the first seconds after it was taken: the fuel goes back, the car is on its track, nothing is paid. */
    public function cancel(Request $request, int $gig): JsonResponse
    {
        $userId = $this->userId($request);
        $this->workshop->advance($userId);
        $this->gigs->cancel($userId, $gig);

        return $this->respond($userId, null);
    }

    /** Pays the bail of the arrest the driver is under: it ends at once, and the bail is taken from their cash. */
    public function bail(Request $request): JsonResponse
    {
        $userId = $this->userId($request);
        $this->workshop->advance($userId);
        $this->police->bail($userId);

        return $this->respond($userId, null);
    }

    private function userId(Request $request): int
    {
        return (int) $request->user()->getAuthIdentifier();
    }

    /** @param array<string, mixed> $extra */
    private function respond(int $userId, ?int $carId, int $status = 200, array $extra = []): JsonResponse
    {
        $nowMs = RunService::nowMs();

        return response()->json(['data' => $this->gigs->view($userId, $carId, $nowMs), 'now_ms' => $nowMs, 'wallet' => ['cash_cents' => $this->ledger->balance($userId), 'xp' => $this->ledger->xp($userId)], ...$extra], $status)->header('Cache-Control', 'no-store');
    }
}
