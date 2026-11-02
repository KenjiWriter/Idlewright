<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\AccountRefusedException;
use App\Http\Controllers\Controller;
use App\Http\Resources\MeResource;
use App\Models\ConsentRecord;
use App\Services\Consent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AccountConsentController extends Controller
{
    /**
     * Accepts the terms and the privacy policy as they are now, after they changed. The driver says again that they accept and
     * that they have the age, and which version of the texts they were shown; if the texts changed while the screen was open, that
     * is a 409 and the app shows the new ones. Accepting when nothing needs accepting changes nothing, so a double click is harmless.
     *
     * @throws AccountRefusedException `consent_version_changed` (409)
     */
    public function store(Request $request, Consent $consent): JsonResponse
    {
        $data = $request->validate([
            'accept_terms' => ['accepted'],
            'confirm_age' => ['accepted'],
            'version' => ['required', 'string', 'max:32'],
        ]);

        if ($data['version'] !== Consent::current()) {
            throw AccountRefusedException::conflict('consent_version_changed', ['current_version' => Consent::current()]);
        }

        $user = $request->user();

        if ($user->needsConsent()) {
            $consent->accept($user, ConsentRecord::RECONSENT);
        }

        return response()->json(['data' => new MeResource($user)]);
    }
}
