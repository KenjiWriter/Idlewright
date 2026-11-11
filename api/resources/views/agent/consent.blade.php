@php
    /**
     * The page of consent of an AI assistant (Passport gives it the client, the person, the scopes, the request and the token of the page).
     *
     * @var \Laravel\Passport\Client $client
     * @var \App\Models\Admin $user
     * @var \Illuminate\Http\Request $request
     * @var string $authToken
     */
    $games = $user->allowedGames();
    $returnsTo = parse_url((string) $request->query('redirect_uri'), PHP_URL_HOST) ?: (string) $request->query('redirect_uri');
    $default = (int) config('ride.agent.default_hours');
    $max = (int) config('ride.agent.max_hours');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('admin.agent.consent.title') }} · {{ \App\Providers\Filament\AdminPanelProvider::ENGINE_NAME }}</title>
    <style>
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; background: #f4f4f5; color: #18181b; }
        main { max-width: 560px; margin: 40px auto; background: #fff; border-radius: 14px; padding: 28px; box-shadow: 0 1px 3px rgba(0, 0, 0, .12); }
        h1 { font-size: 1.35rem; margin: 0 0 12px; }
        p, li { line-height: 1.5; }
        .muted { color: #52525b; font-size: .9rem; }
        fieldset { border: 1px solid #e4e4e7; border-radius: 10px; padding: 12px 14px; margin: 16px 0; }
        legend { font-weight: 600; padding: 0 4px; }
        label { display: block; margin: 6px 0; }
        select, input[type=number] { font: inherit; padding: 6px 8px; border: 1px solid #d4d4d8; border-radius: 8px; }
        .level { display: flex; gap: 10px; align-items: flex-start; }
        .level small { display: block; color: #52525b; }
        .never { background: #fef2f2; border-color: #fecaca; }
        .buttons { display: flex; gap: 12px; margin-top: 20px; }
        button { font: inherit; padding: 10px 18px; border-radius: 10px; border: 0; cursor: pointer; }
        .allow { background: #f59e0b; color: #18181b; font-weight: 600; }
        .deny { background: #e4e4e7; color: #18181b; }
        .errors { background: #fef2f2; color: #991b1b; border-radius: 8px; padding: 8px 12px; }
    </style>
</head>
<body>
<main data-test="agent-consent">
    <h1>{{ __('admin.agent.consent.title') }}</h1>
    <p>{{ __('admin.agent.consent.intro', ['client' => $client->name]) }}</p>
    <p class="muted" data-test="agent-consent-return">{{ __('admin.agent.consent.returns_to', ['host' => $returnsTo]) }}</p>

    @if ($errors->any())
        <div class="errors">{{ $errors->first() }}</div>
    @endif

    <form method="post" action="{{ route('passport.authorizations.approve') }}">
        @csrf
        <input type="hidden" name="state" value="{{ $request->state }}">
        <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
        <input type="hidden" name="auth_token" value="{{ $authToken }}">

        <fieldset>
            <legend>{{ __('admin.agent.consent.game') }}</legend>
            <select name="game" data-test="agent-consent-game">
                @foreach ($games as $game)
                    <option value="{{ $game->slug }}" @selected($game->is_default)>{{ $game->name }}</option>
                @endforeach
            </select>
        </fieldset>

        <fieldset>
            <legend>{{ __('admin.agent.consent.level') }}</legend>
            @foreach (\App\Agent\AgentLevel::cases() as $level)
                <label class="level">
                    <input type="radio" name="level" value="{{ $level->value }}" @checked($level === \App\Agent\AgentLevel::Plan)>
                    <span>
                        <strong>{{ $level->value }}. {{ __('admin.agent.levels.'.$level->value.'.label') }}</strong>
                        @if ($level === \App\Agent\AgentLevel::Pack) <span class="muted">{{ __('admin.agent.consent.publisher_only') }}</span> @endif
                        <small>{{ __('admin.agent.levels.'.$level->value.'.help') }}</small>
                    </span>
                </label>
            @endforeach
            <p class="muted">{{ __('admin.agent.consent.now_note') }}</p>
        </fieldset>

        <fieldset>
            <legend>{{ __('admin.agent.consent.hours') }}</legend>
            <input type="number" name="hours" min="1" max="{{ $max }}" value="{{ $default }}" data-test="agent-consent-hours">
            <span class="muted">{{ __('admin.agent.consent.hours_help', ['max' => $max]) }}</span>
        </fieldset>

        <fieldset class="never">
            <legend>{{ __('admin.agent.consent.never') }}</legend>
            <p style="margin: 0">{{ __('admin.agent.consent.never_list') }}</p>
        </fieldset>

        <div class="buttons">
            <button type="submit" class="allow" data-test="agent-consent-allow">{{ __('admin.agent.consent.allow') }}</button>
        </div>
    </form>

    <form method="post" action="{{ route('passport.authorizations.deny') }}">
        @csrf
        @method('DELETE')
        <input type="hidden" name="state" value="{{ $request->state }}">
        <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
        <input type="hidden" name="auth_token" value="{{ $authToken }}">
        <div class="buttons">
            <button type="submit" class="deny" data-test="agent-consent-deny">{{ __('admin.agent.consent.deny') }}</button>
        </div>
    </form>
</main>
</body>
</html>
