@php
    /**
     * @var array<string, mixed>|null $result
     */
    $result = $this->result;
    $box = 'border: 1px solid rgba(128, 128, 128, .3); border-radius: 12px; padding: 16px; display: flex; flex-direction: column; gap: 10px;';
    $muted = 'font-size: 13px; opacity: .7;';
    $cell = 'padding: 6px 10px; text-align: left; border-bottom: 1px solid rgba(128, 128, 128, .2); font-size: .875rem; vertical-align: top;';
@endphp

<x-filament-panels::page>
    <div style="display: flex; flex-direction: column; gap: 16px;" data-test="progress-page">
        <div style="{{ $box }}">
            <div style="{{ $muted }}">{{ __('admin.progress.intro') }}</div>
            {{ $this->form }}
            <div>
                <x-filament::button wire:click="simulate" wire:loading.attr="disabled" data-test="progress-simulate">{{ __('admin.progress.simulate') }}</x-filament::button>
                <span wire:loading wire:target="simulate" style="{{ $muted }}">{{ __('admin.progress.counting') }}</span>
            </div>
        </div>

        @if ($result !== null)
            @php
                $summary = $result['summary'];
            @endphp
            <div style="{{ $box }}" data-test="progress-summary">
                <strong>{{ __('admin.progress.summary', ['minutes' => $summary['minutes'], 'level' => $summary['level'], 'xp' => $summary['xp'], 'cash' => number_format((float) $summary['cash_usd'], 2, ',', ' ')]) }}</strong>
                <div style="{{ $muted }}">{{ __('admin.progress.summary_more', ['tutorial' => $summary['xp_tutorial'], 'laps' => $summary['xp_laps'], 'missions' => $summary['xp_missions'], 'count' => $summary['laps'], 'driving' => $summary['driving_minutes'], 'car' => $summary['car'], 'car_level' => $summary['car_level'], 'track' => $summary['track'] ?? '-']) }}</div>
            </div>

            <div style="{{ $box }}" data-test="progress-levels">
                <strong>{{ __('admin.progress.levels') }}</strong>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr>
                                @foreach (['level', 'minute', 'xp', 'xp_tutorial', 'xp_laps', 'xp_missions', 'cash', 'car', 'track'] as $column)
                                    <th style="{{ $cell }}">{{ __('admin.progress.columns.'.$column) }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($result['levels'] as $row)
                                <tr data-test="progress-level">
                                    <td style="{{ $cell }}"><strong>{{ $row['level'] }}</strong></td>
                                    <td style="{{ $cell }}">{{ $row['minute'] }}</td>
                                    <td style="{{ $cell }}">{{ $row['xp'] }}</td>
                                    <td style="{{ $cell }}">{{ $row['xp_tutorial'] }}</td>
                                    <td style="{{ $cell }}">{{ $row['xp_laps'] }}</td>
                                    <td style="{{ $cell }}">{{ $row['xp_missions'] }}</td>
                                    <td style="{{ $cell }}">{{ number_format((float) $row['cash_usd'], 2, ',', ' ') }}</td>
                                    <td style="{{ $cell }}">{{ $row['car'] }} (L{{ $row['car_level'] }})</td>
                                    <td style="{{ $cell }}">{{ $row['track'] ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div style="{{ $box }}" data-test="progress-purchases">
                <strong>{{ __('admin.progress.purchases') }}</strong>
                @if ($result['purchases'] === [])
                    <div style="{{ $muted }}">{{ __('admin.progress.no_purchases') }}</div>
                @else
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr>
                                @foreach (['minute', 'bought', 'price', 'level', 'cash_after'] as $column)
                                    <th style="{{ $cell }}">{{ __('admin.progress.columns.'.$column) }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($result['purchases'] as $row)
                                <tr>
                                    <td style="{{ $cell }}">{{ $row['minute'] }}</td>
                                    <td style="{{ $cell }}"><code>{{ $row['slug'] }}</code></td>
                                    <td style="{{ $cell }}">{{ number_format((float) $row['price_usd'], 2, ',', ' ') }}</td>
                                    <td style="{{ $cell }}">{{ $row['level'] }}</td>
                                    <td style="{{ $cell }}">{{ number_format((float) $row['cash_after_usd'], 2, ',', ' ') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            <div style="{{ $box }}" data-test="progress-moves">
                <strong>{{ __('admin.progress.moves') }}</strong>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            @foreach (['minute', 'what', 'from', 'to', 'level'] as $column)
                                <th style="{{ $cell }}">{{ __('admin.progress.columns.'.$column) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($result['moves'] as $row)
                            <tr>
                                <td style="{{ $cell }}">{{ $row['minute'] }}</td>
                                <td style="{{ $cell }}">{{ __('admin.progress.kinds.'.$row['kind']) }}</td>
                                <td style="{{ $cell }}">{{ $row['from'] ?? '-' }}</td>
                                <td style="{{ $cell }}">{{ $row['to'] }}</td>
                                <td style="{{ $cell }}">{{ $row['level'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div style="{{ $box }}" data-test="progress-assumptions">
                <strong>{{ __('admin.progress.assumptions_title') }}</strong>
                <ul style="margin: 0; padding-left: 20px; list-style: disc; display: flex; flex-direction: column; gap: 4px;">
                    @foreach ($result['notes'] as $note)
                        <li>{{ __('admin.progress.notes.'.$note['code'], ['slug' => $note['slug'] ?? '']) }}</li>
                    @endforeach
                    @foreach (array_keys($result['assumptions']) as $code)
                        <li style="{{ $muted }}">{{ __('admin.progress.assumptions.'.$code.($code === 'tutorial' && ! $result['plan']['tutorial'] ? '_off' : '').($code === 'missions' && ! $result['plan']['missions'] ? '_off' : ''), ['seconds' => $result['plan']['step_seconds'], 'player' => $result['plan']['style']]) }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</x-filament-panels::page>
