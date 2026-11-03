@php
    /** @var array<string, mixed> $stats */
    $box = 'border: 1px solid rgba(128, 128, 128, .3); border-radius: 12px; padding: 16px; display: flex; flex-direction: column; gap: 10px;';
    $muted = 'font-size: 13px; opacity: .7;';
    $cell = 'padding: 6px 10px; text-align: left; border-bottom: 1px solid rgba(128, 128, 128, .2); font-size: .875rem;';
    $percent = fn (?float $rate): string => $rate === null ? '-' : number_format($rate * 100, 1).'%';
@endphp

<x-filament-panels::page>
    <div style="display: flex; flex-direction: column; gap: 16px;" data-test="push-page">
        <div style="{{ $box }}">
            <div style="{{ $muted }}">{{ __('admin.push_page.intro') }}</div>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <x-filament::badge size="sm" :color="$stats['enabled'] ? 'success' : 'gray'" data-test="push-rules">{{ $stats['enabled'] ? __('admin.push_page.rules_on') : __('admin.push_page.rules_off') }}</x-filament::badge>
                <x-filament::badge size="sm" :color="$stats['has_keys'] ? 'success' : 'warning'" data-test="push-keys">{{ $stats['has_keys'] ? __('admin.push_page.keys_made') : __('admin.push_page.keys_missing') }}</x-filament::badge>
                <x-filament::badge size="sm" color="gray" data-test="push-holdout">{{ __('admin.push_page.holdout', ['percent' => rtrim(rtrim(number_format($stats['holdout_ppm'] / 10000, 1), '0'), '.')]) }}</x-filament::badge>
            </div>
            @if ($stats['has_keys'])
                <div style="{{ $muted }}" data-test="push-public-key">{{ __('admin.push_page.public_key') }}: <code style="word-break: break-all;">{{ $stats['public_key'] }}</code></div>
                <div style="{{ $muted }}">{{ __('admin.push_page.subject') }}: <code>{{ $stats['subject'] }}</code></div>
            @else
                <div style="{{ $muted }}" data-test="push-keys-hint">{{ __('admin.push_page.keys_hint') }}</div>
            @endif
        </div>

        <div style="{{ $box }}" data-test="push-agreed">
            <strong>{{ __('admin.push_page.agreed') }}</strong>
            <div>{{ __('admin.push_page.devices', ['active' => $stats['devices']['active'], 'disabled' => $stats['devices']['disabled']]) }}</div>
            <div>{{ __('admin.push_page.accounts', ['with' => $stats['accounts']['with_device'], 'eligible' => $stats['accounts']['eligible'], 'share' => $percent($stats['accounts']['share'])]) }}</div>
            @if ($stats['devices']['browsers'] !== [])
                <div style="{{ $muted }}">{{ __('admin.push_page.browsers') }}: @foreach ($stats['devices']['browsers'] as $browser => $count){{ $browser }} {{ $count }}@if (! $loop->last), @endif @endforeach</div>
            @endif
        </div>

        <div style="{{ $box }}" data-test="push-week">
            <strong>{{ __('admin.push_page.week') }}</strong>
            <div>{{ __('admin.push_page.week_summary', ['queued' => $stats['week']['queued'], 'opened' => $stats['week']['opened'], 'off' => $stats['week']['switched_off']]) }}@if ($stats['week']['per_device_per_day'] !== null) {{ __('admin.push_page.per_device', ['count' => $stats['week']['per_device_per_day']]) }}@endif</div>
            <table style="border-collapse: collapse; width: 100%;">
                <thead>
                    <tr>
                        <th style="{{ $cell }}">{{ __('admin.push_page.day') }}</th>
                        <th style="{{ $cell }}">{{ __('admin.push_page.queued') }}</th>
                        <th style="{{ $cell }}">{{ __('admin.push_page.opened') }}</th>
                        <th style="{{ $cell }}">{{ __('admin.push_page.told') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($stats['days'] as $day)
                        <tr data-test="push-day" data-day="{{ $day['day'] }}">
                            <td style="{{ $cell }}">{{ $day['day'] }}</td>
                            <td style="{{ $cell }}">{{ $day['queued'] }}</td>
                            <td style="{{ $cell }}">{{ $day['opened'] }}</td>
                            <td style="{{ $cell }}">{{ $day['told'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if ($stats['skipped'] !== [])
                <div style="{{ $muted }}" data-test="push-skipped">{{ __('admin.push_page.skipped') }}: @foreach ($stats['skipped'] as $reason => $count){{ __('admin.push_page.reason.'.$reason) }} {{ $count }}@if (! $loop->last), @endif @endforeach</div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
