<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PartResource;
use App\Services\PartTransmutations;
use App\Services\RunService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Transmutation of the rarity of a part of the inventory: what it would be and cost (`GET`), and the transmutation itself (`POST`, once for a `request_id`).
 */
final class TransmuteController extends Controller
{
    public function __construct(private readonly PartTransmutations $transmutations) {}

    /** What the part would be after a transmutation, with the affixes it would have, and what it costs: nothing is changed. */
    public function show(Request $request, int $part): JsonResponse
    {
        return response()->json(['data' => $this->transmutations->preview((int) $request->user()->getAuthIdentifier(), $part)])->header('Cache-Control', 'no-store');
    }

    /** Makes the part one rarity better, for cash and coins. `repeated` says that the request was done before and nothing was charged. */
    public function store(Request $request, int $part): JsonResponse
    {
        $data = $request->validate(['request_id' => ['required', 'string', 'max:'.RunService::MAX_REQUEST_ID_LENGTH]]);
        $done = $this->transmutations->transmute((int) $request->user()->getAuthIdentifier(), $part, $data['request_id']);

        return response()->json([
            'repeated' => $done['repeated'],
            'coins' => $done['coins'],
            'cash_cents' => $done['cash_cents'],
            'data' => PartResource::make($done['part'])->resolve(),
        ], $done['repeated'] ? 200 : 201)->header('Cache-Control', 'no-store');
    }
}
