<?php

declare(strict_types=1);

/*
 * The connection of an AI assistant to the panel (laravel/mcp). Only the apps of Claude may be sent back to after a person gives a consent: a client of OAuth that
 * registers itself (dynamic registration) with any other address of return is refused, so a strange page cannot have a consent made out to itself. Claude Code
 * comes back to http://localhost. The list can be changed with AGENT_REDIRECT_DOMAINS (comma separated).
 */
return [

    'redirect_domains' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'AGENT_REDIRECT_DOMAINS',
        'https://claude.ai,https://claude.com,http://localhost',
    ))))),

    // No custom schemes of apps: an address of return is http(s) or it is refused.
    'custom_schemes' => [],

    // The panel is its own authorization server (Passport), on its own host.
    'authorization_server' => null,

];
