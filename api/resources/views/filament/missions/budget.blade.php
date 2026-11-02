{{--
    What the missions come to for a car on a track, as `missions:budget` prints it: the report of MissionBudget, worked out for the form as it is now (not as it was saved).
    $budget is that report (null before it was asked for, and for content that has no missions).
--}}
@php
    // The comma of the languages that write it so, as the rest of the panel does (the English panel keeps the point).
    $point = app()->getLocale() === 'en' ? '.' : ',';
    $thousands = app()->getLocale() === 'en' ? ',' : ' ';
    $money = fn (int|float|null $cents): string => $cents === null ? '–' : '$'.number_format($cents / 100, 2, $point, $thousands);
    $number = fn (int|float|null $value): string => $value === null ? '–' : number_format((float) $value, 0, '.', ' ');
    $decimal = fn (int|float $value, int $decimals = 1): string => number_format($value, $decimals, $point, '');
    $percent = fn (int|float $value, int $decimals = 1): string => number_format($value * 100, $decimals, $point, '').'%';
    $failed = $budget === null ? [] : array_filter($budget['checks'], fn (array $check): bool => ! $check['ok']);
@endphp

<div data-test="missions-budget" style="margin-top: 16px; border: 1px solid rgba(128, 128, 128, .3); border-radius: 12px; padding: 12px 16px; display: grid; gap: 12px;">
    <div style="font-weight: 600;">{{ __('admin.missions.budget.heading') }}</div>

    @if ($budget === null)
        <p style="margin: 0; font-size: .875rem; opacity: .75;" data-test="budget-empty">{{ __('admin.missions.budget.empty') }}</p>
    @else
        @if ($budget['tank'] === null)
            <p style="margin: 0; font-size: .875rem; color: #b45309;" data-test="budget-no-tank">{{ __('admin.missions.budget.no_tank', ['car' => $budget['car'], 'track' => $budget['track']]) }}</p>
        @else
            <p style="margin: 0; font-size: .875rem;" data-test="budget-tank">
                {{ __('admin.missions.budget.tank', [
                    'car' => $budget['car'],
                    'track' => $budget['track'],
                    'laps' => $budget['tank']['laps'],
                    'gross' => $money($budget['tank']['gross_cents']),
                    'net' => $money($budget['tank']['net_cents']),
                    'xp' => $budget['tank']['xp'],
                    'fuel' => $money($budget['tank']['fuel_cents']),
                ]) }}
            </p>
        @endif

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: .875rem;" data-test="budget-periods">
                <thead>
                    <tr style="text-align: left; opacity: .7;">
                        <th style="padding: 4px 8px;">{{ __('admin.missions.budget.period') }}</th>
                        <th style="padding: 4px 8px;">{{ __('admin.missions.budget.missions') }}</th>
                        <th style="padding: 4px 8px;">{{ __('admin.missions.budget.worth') }}</th>
                        <th style="padding: 4px 8px;">{{ __('admin.missions.budget.cash') }}</th>
                        <th style="padding: 4px 8px;">{{ __('admin.missions.budget.xp') }}</th>
                        <th style="padding: 4px 8px;">{{ __('admin.missions.budget.coins') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($budget['periods'] as $period => $row)
                        <tr style="border-top: 1px solid rgba(128, 128, 128, .2);" data-period="{{ $period }}">
                            <td style="padding: 4px 8px; font-weight: 600;">{{ __("admin.missions.period.{$period}") }}</td>
                            <td style="padding: 4px 8px;">{{ $row['missions'] }}</td>
                            <td style="padding: 4px 8px;">{{ $decimal($row['worth'], 1) }} {{ __('admin.missions.tanks') }}</td>
                            <td style="padding: 4px 8px;">{{ $money($row['cash_cents']) }} <span style="opacity: .65;">({{ $percent($row['cash_ppm'] / 1000000, 0) }} {{ __('admin.missions.budget.of_worth') }})</span></td>
                            <td style="padding: 4px 8px;">{{ $number($row['xp']) }} <span style="opacity: .65;">({{ $percent($row['xp_ppm'] / 1000000, 0) }} {{ __('admin.missions.budget.of_worth') }})</span></td>
                            <td style="padding: 4px 8px;">{{ $row['coins'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p style="margin: 0; font-size: .875rem;" data-test="budget-month">
            @if ($budget['tank'] !== null)
                {{ __('admin.missions.budget.month', [
                    'cash' => $money($budget['month']['cash_cents']),
                    'cash_percent' => $budget['month']['cash_percent'],
                    'tanks' => $money($budget['month']['cash_of_tanks_cents']),
                    'xp' => $number($budget['month']['xp']),
                    'xp_percent' => $budget['month']['xp_percent'],
                    'xp_tanks' => $number($budget['month']['xp_of_tanks']),
                    'coins' => (int) round($budget['month']['coins']),
                ]) }}
            @else
                {{ __('admin.missions.budget.month_shares', ['cash' => $percent($budget['month']['cash_share']), 'xp' => $percent($budget['month']['xp_share']), 'coins' => (int) round($budget['month']['coins'])]) }}
            @endif
        </p>

        @if ($budget['vip'] !== null)
            <p style="margin: 0; font-size: .875rem; opacity: .85;" data-test="budget-vip">{{ __('admin.missions.budget.vip', ['coins' => $budget['vip']['coins'], 'percent' => $budget['vip']['month_percent']]) }}</p>
        @endif

        @if ($budget['pack'] !== null)
            <p style="margin: 0; font-size: .875rem; opacity: .85;" data-test="budget-pack">
                {{ __('admin.missions.budget.pack', [
                    'coins' => $budget['pack']['coins'],
                    'price' => $decimal($budget['pack']['price_minor'] / 100, 2),
                    'currency' => $budget['pack']['currency'],
                    'worth' => $decimal($budget['pack']['worth'], 2),
                    'minutes' => $budget['pack']['minutes_per_coin'],
                    'hours' => $decimal($budget['pack']['speedup_hours'], 1),
                ]) }}
            </p>
        @endif

        @if ($budget['main']['missions'] > 0 && $budget['main']['worth'] !== null)
            <p style="margin: 0; font-size: .875rem;" data-test="budget-main">{{ __('admin.missions.budget.main_line', ['count' => $budget['main']['missions'], 'worth' => $decimal($budget['main']['worth'], 1), 'cash' => $money($budget['main']['cash_cents']), 'xp' => $number($budget['main']['xp'])]) }}</p>
        @endif

        <div style="display: grid; gap: 4px;" data-test="budget-checks">
            <div style="font-weight: 600; font-size: .875rem;">{{ __('admin.missions.budget.limits') }}</div>
            @foreach ($budget['checks'] as $check)
                <div style="font-size: .875rem;" data-check="{{ $check['name'] }}" data-ok="{{ $check['ok'] ? 'yes' : 'no' }}">
                    <span style="display: inline-block; min-width: 3.5rem; font-weight: 600; color: {{ $check['ok'] ? '#15803d' : '#b91c1c' }};">{{ $check['ok'] ? __('admin.missions.budget.ok') : __('admin.missions.budget.fail') }}</span>
                    {{ __("admin.missions.budget.checks.{$check['name']}") }}:
                    {{ in_array($check['name'], ['cash_share', 'xp_share'], true) ? $percent($check['value']) : $decimal($check['value'], 2) }}
                    <span style="opacity: .65;">({{ __('admin.missions.budget.between') }}
                        {{ in_array($check['name'], ['cash_share', 'xp_share'], true) ? $percent($check['min'], 0).' – '.$percent($check['max'], 0) : number_format($check['min'], 0).' – '.number_format($check['max'], 0) }})</span>
                </div>
            @endforeach
            <p style="margin: 4px 0 0; font-size: .875rem; font-weight: 600; color: {{ $failed === [] ? '#15803d' : '#b91c1c' }};" data-test="budget-verdict">
                {{ $failed === [] ? __('admin.missions.budget.gate_passes') : __('admin.missions.budget.gate_stops') }}
            </p>
        </div>

        @if ($budget['tank'] !== null)
            @foreach ($budget['periods'] as $period => $row)
                <details>
                    <summary style="cursor: pointer; font-weight: 600; font-size: .875rem;">{{ __('admin.missions.budget.templates', ['period' => __("admin.missions.period.{$period}")]) }}</summary>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; font-size: .8125rem; margin-top: 4px;">
                            <thead>
                                <tr style="text-align: left; opacity: .7;">
                                    <th style="padding: 2px 8px;">{{ __('admin.missions.budget.slot') }}</th>
                                    <th style="padding: 2px 8px;">{{ __('admin.missions.budget.template') }}</th>
                                    <th style="padding: 2px 8px;">{{ __('admin.missions.budget.objective') }}</th>
                                    <th style="padding: 2px 8px;">{{ __('admin.missions.budget.asks') }}</th>
                                    <th style="padding: 2px 8px;">{{ __('admin.missions.budget.weight') }}</th>
                                    <th style="padding: 2px 8px;">{{ __('admin.missions.budget.share') }}</th>
                                    <th style="padding: 2px 8px;">{{ __('admin.missions.budget.worth') }}</th>
                                    <th style="padding: 2px 8px;">{{ __('admin.missions.budget.cash') }}</th>
                                    <th style="padding: 2px 8px;">{{ __('admin.missions.budget.xp') }}</th>
                                    <th style="padding: 2px 8px;">{{ __('admin.missions.budget.items') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($row['templates'] as $template)
                                    <tr style="border-top: 1px solid rgba(128, 128, 128, .15);">
                                        <td style="padding: 2px 8px;">{{ $template['slot'] }}</td>
                                        <td style="padding: 2px 8px;">{{ $template['template'] }}</td>
                                        <td style="padding: 2px 8px;">{{ $template['objective'] }}</td>
                                        <td style="padding: 2px 8px;">{{ $template['fits'] ? ($template['money'] ? '$'.number_format($template['asks'] / 100, 0) : $template['asks']) : __('admin.missions.budget.not_fit') }}</td>
                                        <td style="padding: 2px 8px;">{{ $template['weight'] }}</td>
                                        <td style="padding: 2px 8px;">{{ $template['share'] }}</td>
                                        <td style="padding: 2px 8px;">{{ $decimal($template['worth'], 2) }}</td>
                                        <td style="padding: 2px 8px;">{{ $money($template['cash_cents']) }}</td>
                                        <td style="padding: 2px 8px;">{{ $number($template['xp']) }}</td>
                                        <td style="padding: 2px 8px;">{{ $template['items'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            @endforeach
        @endif
    @endif
</div>
