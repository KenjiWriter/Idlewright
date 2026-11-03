@php
    /**
     * @var array<string, mixed> $settings
     * @var array<string, array<string, mixed>> $bonuses
     * @var string $studioUrl
     * @var \Illuminate\Support\HtmlString $guide
     * @var \Illuminate\Support\HtmlString $guideTitle
     * @var array<string, mixed>|null $budget
     * @var bool $calculated
     */
    $box = 'border: 1px solid rgba(128, 128, 128, .3); border-radius: 12px; padding: 16px; display: flex; flex-direction: column; gap: 10px;';
    $muted = 'font-size: 13px; opacity: .7;';
    $cell = 'padding: 6px 10px; text-align: left; border-bottom: 1px solid rgba(128, 128, 128, .2); font-size: .875rem; vertical-align: top;';
    $locales = ['pl', 'en', 'fr', 'de', 'pt-BR'];
    $percent = fn (int $ppm): string => rtrim(rtrim(number_format($ppm / 10_000, 1, '.', ''), '0'), '.').'%';
    $tries = fn (?float $value): string => $value === null ? '—' : ($value < 10 ? number_format($value, 1, '.', '') : number_format(round($value), 0, '.', ' '));
@endphp

<x-filament-panels::page>
    <div style="display: flex; flex-direction: column; gap: 16px;" data-test="tuning-page">
        <div style="{{ $box }}">
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
                <strong data-test="tuning-summary">{{ __('admin.tuning.summary', ['count' => count($bonuses)]) }}</strong>
                <x-filament::button tag="a" :href="$studioUrl" size="sm" color="gray" data-test="tuning-studio">{{ __('admin.tuning.studio') }}</x-filament::button>
            </div>
            <div style="{{ $muted }}">{{ __('admin.tuning.intro') }}</div>
            <div style="{{ $muted }}" data-test="tuning-settings">
                {{ __('admin.tuning.settings', [
                    'slots' => $settings['slots'] ?? 0,
                    'level' => $settings['unlock_level'] ?? 1,
                    'random' => $percent((int) ($settings['cost_random_ppm'] ?? 0)),
                    'pick' => $percent((int) ($settings['cost_pick_ppm'] ?? 0)),
                    'plain' => $percent((int) ($settings['max_ppm_plain'] ?? 0)),
                    'context' => $percent((int) ($settings['max_ppm_context'] ?? 0)),
                    'cap' => $percent((int) ($settings['car_cap_ppm'] ?? 0)),
                ]) }}
            </div>
            <details>
                <summary style="cursor: pointer;"><strong>{{ $guideTitle }}</strong></summary>
                <div style="font-size: .875rem; margin-top: 8px;">{{ $guide }}</div>
            </details>
        </div>

        @foreach ($bonuses as $key => $bonus)
            <div style="{{ $box }}" data-test="tuning-bonus" data-key="{{ $key }}">
                <div style="display: flex; align-items: baseline; gap: 10px; flex-wrap: wrap;">
                    <strong>{{ \App\Support\I18nText::from($bonus['name'])->for() }}</strong>
                    <code style="font-size: .75rem; opacity: .7;">{{ $key }}</code>
                    <x-filament::badge size="sm" color="gray">{{ __('admin.tuning.effect.'.$bonus['effect']) }}{{ isset($bonus['stat']) ? ' / '.$bonus['stat'] : '' }}</x-filament::badge>
                </div>
                <div style="{{ $muted }}">{{ \App\Support\I18nText::from($bonus['description'])->for() }}</div>
                <div style="{{ $muted }}" data-test="tuning-names">
                    @foreach ($locales as $locale)
                        <span>{{ $locale }} = {{ $bonus['name'][$locale] ?? '—' }}@if (! $loop->last) · @endif</span>
                    @endforeach
                </div>

                <div style="display: flex; gap: 24px; flex-wrap: wrap; align-items: flex-start;">
                    <table style="border-collapse: collapse;">
                        <tbody>
                            <tr><td style="{{ $cell }}">{{ __('admin.tuning.slots') }}</td><td style="{{ $cell }}">{{ implode(', ', $bonus['slots']) }}</td></tr>
                            <tr><td style="{{ $cell }}">{{ __('admin.tuning.weight') }}</td><td style="{{ $cell }}">{{ $bonus['weight'] }}</td></tr>
                            <tr><td style="{{ $cell }}">{{ __('admin.tuning.from_level') }}</td><td style="{{ $cell }}">{{ $bonus['from_level'] }}</td></tr>
                            <tr>
                                <td style="{{ $cell }}">{{ __('admin.tuning.where') }}</td>
                                <td style="{{ $cell }}">
                                    @php($context = $bonus['context'] ?? [])
                                    @if ($context === [])
                                        {{ __('admin.tuning.everywhere') }}
                                    @else
                                        @foreach (['kinds', 'surfaces', 'weathers'] as $part)
                                            @isset($context[$part])
                                                <div>{{ __('admin.tuning.context.'.$part) }} = {{ implode(', ', $context[$part]) }}</div>
                                            @endisset
                                        @endforeach
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td style="{{ $cell }}">{{ __('admin.tuning.bell') }}</td>
                                <td style="{{ $cell }}" data-test="tuning-bell">{{ __('admin.tuning.bell_values', ['mean' => $percent($bonus['mean_ppm']), 'sigma' => $percent($bonus['sigma_ppm']), 'min' => $percent($bonus['min_ppm']), 'max' => $percent($bonus['max_ppm'])]) }}</td>
                            </tr>
                        </tbody>
                    </table>

                    @php($peak = max(array_column($bonus['bars'], 'chance')) ?: 1)
                    <svg width="{{ max(12, count($bonus['bars'])) * 22 + 8 }}" height="86" role="img" aria-label="{{ __('admin.tuning.chart') }}" data-test="tuning-chart">
                        @foreach ($bonus['bars'] as $index => $bar)
                            @php($height = (int) round($bar['chance'] / $peak * 56))
                            <rect x="{{ 4 + $index * 22 }}" y="{{ 62 - $height }}" width="18" height="{{ $height }}" rx="2" fill="currentColor" opacity=".55"><title>{{ $bar['percent'] }}%: {{ number_format($bar['chance'] * 100, 2, '.', '') }}%</title></rect>
                            <text x="{{ 13 + $index * 22 }}" y="78" font-size="9" text-anchor="middle" fill="currentColor" opacity=".7">{{ $bar['percent'] }}</text>
                        @endforeach
                    </svg>

                    <table style="border-collapse: collapse;" data-test="tuning-reach">
                        <thead><tr><th style="{{ $cell }}" colspan="2">{{ __('admin.tuning.reach') }}</th></tr></thead>
                        <tbody>
                            @foreach ($bonus['reach'] as $value => $count)
                                <tr><td style="{{ $cell }}">≥ {{ $value }}%</td><td style="{{ $cell }}" data-reach="{{ $value }}">{{ $tries($count) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach

        <div style="{{ $box }}" data-test="tuning-budget">
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
                <strong>{{ __('admin.tuning.budget.title') }}</strong>
                <x-filament::button size="sm" color="gray" wire:click="calculateBudget" data-test="tuning-budget-button">{{ __('admin.tuning.budget.button') }}</x-filament::button>
            </div>
            <div style="{{ $muted }}">{{ __('admin.tuning.budget.intro') }}</div>

            @if ($calculated && $budget === null)
                <div data-test="tuning-budget-none">{{ __('admin.tuning.budget.none') }}</div>
            @elseif ($budget !== null)
                @php($drop = -$budget['worst']['lap_percent'])
                @if ($drop > $budget['target'] && $drop <= $budget['approved'])
                    <div style="color: rgb(217, 119, 6);" data-test="tuning-budget-warning">⚠ {{ __('admin.tuning.budget.warning', ['drop' => number_format($drop, 1, '.', ''), 'car' => $budget['worst']['car'], 'track' => $budget['worst']['track'], 'target' => $budget['target'], 'approved' => $budget['approved']]) }}</div>
                @endif
                @if ($budget['richest']['income_percent'] > $budget['income_target'] && $budget['richest']['income_percent'] <= $budget['income_approved'])
                    <div style="color: rgb(217, 119, 6);" data-test="tuning-budget-warning">⚠ {{ __('admin.tuning.budget.income_warning', ['value' => number_format($budget['richest']['income_percent'], 1, '.', ''), 'car' => $budget['richest']['car'], 'track' => $budget['richest']['track'], 'target' => $budget['income_target'], 'approved' => $budget['income_approved']]) }}</div>
                @endif
                @foreach ($budget['checks'] as $check)
                    <div style="{{ $check['ok'] ? '' : 'color: rgb(220, 38, 38);' }}" data-test="tuning-budget-check">{{ $check['ok'] ? '✓' : '✗' }} {{ __($loop->first ? 'admin.tuning.budget.check' : 'admin.tuning.budget.income_check', ['value' => number_format($check['value'], 1, '.', ''), 'max' => $check['max']]) }}</div>
                @endforeach
                <div style="{{ $muted }}" data-test="tuning-budget-tries">{{ __('admin.tuning.budget.tries', ['to8' => $budget['tries']['to_8'] ?? '—', 'to10' => $budget['tries']['to_10'] ?? '—']) }}</div>
                <table style="border-collapse: collapse;">
                    <thead>
                        <tr>
                            @foreach (['car', 'track', 'lap', 'best', 'lap_percent', 'income_percent', 'hours_8', 'hours_10'] as $head)
                                <th style="{{ $cell }}">{{ __('admin.tuning.budget.'.$head) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($budget['rows'] as $row)
                            <tr data-test="tuning-budget-row">
                                <td style="{{ $cell }}">{{ $row['car'] }}</td>
                                <td style="{{ $cell }}">{{ $row['track'] }}</td>
                                <td style="{{ $cell }}">{{ number_format($row['lap_ms'] / 1000, 1, '.', '') }} s</td>
                                <td style="{{ $cell }}">{{ number_format($row['best_lap_ms'] / 1000, 1, '.', '') }} s</td>
                                <td style="{{ $cell }}">{{ sprintf('%+.1f', $row['lap_percent']) }}%</td>
                                <td style="{{ $cell }}">{{ sprintf('%+.1f', $row['income_percent']) }}%</td>
                                <td style="{{ $cell }}">{{ $row['hours_to_8'] ?? '—' }}</td>
                                <td style="{{ $cell }}">{{ $row['hours_to_10'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-filament-panels::page>
