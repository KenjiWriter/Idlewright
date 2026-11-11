<?php

declare(strict_types=1);

use App\Agent\AgentServer;
use App\Http\Controllers\Agent\ApproveAgentConsent;
use App\Http\Middleware\AgentConnection;
use App\Http\Middleware\EndKeysWithoutConsent;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use Laravel\Passport\Http\Controllers\AuthorizationController;
use Laravel\Passport\Http\Controllers\DenyAuthorizationController;

/*
 * The connection of the AI assistant (most AI v2), on the host of the panel: the routes of OAuth that an app of Claude follows to be given a key by a person (Passport's,
 * with the page of consent and its "Pozwól" of the panel), the metadata and the registration of clients of MCP, and the server of MCP itself. Passport registers none of
 * its own routes (see AppServiceProvider), so nothing else of it is open.
 */

Route::name('passport.')->prefix('oauth')->group(function (): void {
    Route::post('token', [AccessTokenController::class, 'issueToken'])->middleware('throttle:60,1')->name('token');
    Route::get('authorize', [AuthorizationController::class, 'authorize'])->middleware(['web', EndKeysWithoutConsent::class])->name('authorizations.authorize');

    Route::middleware(['web', 'auth:admin', EndKeysWithoutConsent::class])->group(function (): void {
        Route::post('authorize', ApproveAgentConsent::class)->name('authorizations.approve');
        Route::delete('authorize', [DenyAuthorizationController::class, 'deny'])->name('authorizations.deny');
    });
});

Route::middleware('throttle:30,1')->group(function (): void {
    Mcp::oauthRoutes('oauth');
});

Mcp::web('agent/mcp', AgentServer::class)->middleware(['auth:agent', AgentConnection::class])->name('agent.mcp');
