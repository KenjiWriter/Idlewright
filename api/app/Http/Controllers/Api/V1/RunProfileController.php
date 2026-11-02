<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Content\Rules;
use App\Http\Controllers\Controller;
use App\Services\Garage;
use App\Services\RunProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The picture of the lap of the run the driver plays: the line of the thread of the track, the speed of the car along it and how far through the lap it is at each
 * point. `data` is null when the track has no thread, or the driver has no run, and then the screen has nothing to draw. Never cached: it is made from the car as it is now.
 */
final class RunProfileController extends Controller
{
    public function __construct(
        private readonly Garage $garage,
        private readonly RunProfile $profile,
        private readonly Rules $rules,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $data = $request->validate(['weather' => ['nullable', 'string', Rule::in(array_keys($this->rules->weather()))]]);
        $run = $this->garage->selectedRun((int) $request->user()->getAuthIdentifier());

        return response()->json(['data' => $run === null ? null : $this->profile->of($run, $data['weather'] ?? 'dry')])
            ->header('Cache-Control', 'no-store');
    }
}
