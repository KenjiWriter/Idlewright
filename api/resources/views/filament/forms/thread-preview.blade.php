@php
    /** @var array<string, mixed>|null $preview */
    $locale = str_replace('_', '-', app()->getLocale());
    $nameOf = static fn (array $names): string => (string) ($names[$locale] ?? $names['en'] ?? (reset($names) ?: ''));
    $seconds = static fn (float|int $s): string => \Illuminate\Support\Number::format((float) $s, 1).' s';
    $ms = static fn (int $value): string => \Illuminate\Support\Number::format($value / 1000, 1).' s';
@endphp

<div class="thread-preview" style="display: grid; gap: 1.25rem;">
    @if ($preview === null)
        <p class="fi-fo-field-wrp-hint" style="font-size: 0.875rem; color: rgb(107 114 128);">{{ __('admin.thread.preview_wait') }}</p>
    @else
        @php
            $points = $preview['outline'];
            $xs = array_column($points, 'x');
            $ys = array_column($points, 'y');
            $pad = 24;
            $width = 640;
            $spanX = max(1.0, max($xs) - min($xs));
            $spanY = max(1.0, max($ys) - min($ys));
            $scale = ($width - 2 * $pad) / max($spanX, $spanY);
            $height = (int) round($spanY * $scale + 2 * $pad);
            $px = static fn (float $x): float => $pad + ($x - min($xs)) * $scale;
            $py = static fn (float $y): float => $pad + (max($ys) - $y) * $scale;
            $top = max(1.0, (float) ($preview['peak_kmh'] ?? 1));
            $colour = static function (float $kmh) use ($top): string {
                $t = max(0.0, min(1.0, $kmh / $top));

                return sprintf('rgb(%d,%d,%d)', 220 * (1 - $t) + 30 * $t, 60 * (1 - $t) + 170 * $t, 50 * (1 - $t) + 80 * $t);
            };
            $marked = [];
        @endphp

        <p style="font-size: 0.875rem;">
            {{ __('admin.thread.numbers', ['length' => \Illuminate\Support\Number::format($preview['length_m']), 'sections' => $preview['sections'], 'lap' => $seconds($preview['reference_s']), 'speed' => \Illuminate\Support\Number::format($preview['length_m'] * 3.6 / max(0.001, $preview['reference_s']), 1).' km/h']) }}
        </p>
        <p style="font-size: 0.8125rem; color: rgb(107 114 128);">
            @if ($preview['base_lap_ms'] !== null)
                {{ __('admin.thread.pace_note', ['base' => $ms($preview['base_lap_ms']), 'physical' => $seconds($preview['reference_s']), 'pace' => \Illuminate\Support\Number::format((float) $preview['pace'], 2)]) }}
            @else
                {{ __('admin.thread.pace_free', ['physical' => $seconds($preview['reference_s'])]) }}
            @endif
            @if ($preview['gap_m'] > 1)
                {{ __('admin.thread.gap_note', ['gap' => \Illuminate\Support\Number::format($preview['gap_m'])]) }}
            @endif
        </p>

        <svg viewBox="0 0 {{ $width }} {{ $height }}" role="img" style="width: 100%; max-width: 640px; background: rgba(127,127,127,0.06); border-radius: 0.5rem;">
            @for ($i = 1; $i < count($points); $i++)
                <line x1="{{ round($px($points[$i - 1]['x']), 1) }}" y1="{{ round($py($points[$i - 1]['y']), 1) }}" x2="{{ round($px($points[$i]['x']), 1) }}" y2="{{ round($py($points[$i]['y']), 1) }}" stroke="{{ $colour((float) ($points[$i]['speed_kmh'] ?? 0)) }}" stroke-width="5" stroke-linecap="round" />
                @if (! isset($marked[$points[$i]['section']]))
                    @php $marked[$points[$i]['section']] = true; @endphp
                    <text x="{{ round($px($points[$i]['x']) + 7, 1) }}" y="{{ round($py($points[$i]['y']) - 7, 1) }}" font-size="11" fill="currentColor" opacity="0.7">{{ $points[$i]['section'] + 1 }}</text>
                @endif
            @endfor
            <circle cx="{{ round($px($points[0]['x']), 1) }}" cy="{{ round($py($points[0]['y']), 1) }}" r="6" fill="none" stroke="currentColor" stroke-width="2" />
        </svg>

        @php $split = $preview['reference_split']; $total = max(0.001, array_sum($split)); @endphp
        <div>
            <p style="font-size: 0.875rem; font-weight: 600; margin-bottom: 0.25rem;">{{ __('admin.thread.split_title') }}</p>
            <div style="display: flex; height: 0.75rem; border-radius: 0.375rem; overflow: hidden; max-width: 640px;">
                @foreach (['accelerating' => '#3b82f6', 'holding' => '#10b981', 'braking' => '#ef4444', 'cornering' => '#f59e0b', 'return' => '#9ca3af'] as $part => $hue)
                    @if ($split[$part] > 0)
                        <div title="{{ __('admin.thread.split_'.$part) }}" style="width: {{ 100 * $split[$part] / $total }}%; background: {{ $hue }};"></div>
                    @endif
                @endforeach
            </div>
            <p style="font-size: 0.8125rem; margin-top: 0.25rem;">
                @foreach (['accelerating', 'holding', 'braking', 'cornering', 'return'] as $part)
                    @if ($split[$part] > 0)
                        <span style="margin-right: 0.75rem;">{{ __('admin.thread.split_'.$part) }}: {{ $seconds($split[$part]) }}</span>
                    @endif
                @endforeach
            </p>
        </div>

        @if ($preview['cars'] !== [])
            <div style="overflow-x: auto;">
                <p style="font-size: 0.875rem; font-weight: 600; margin-bottom: 0.25rem;">{{ __('admin.thread.cars_title') }}</p>
                <table style="font-size: 0.8125rem; border-collapse: collapse; min-width: 32rem;">
                    <thead>
                        <tr style="text-align: left;">
                            <th style="padding: 0.2rem 0.75rem 0.2rem 0;">{{ __('admin.thread.col_car') }}</th>
                            <th style="padding: 0.2rem 0.75rem;">{{ __('admin.thread.col_formula') }}</th>
                            <th style="padding: 0.2rem 0.75rem;">{{ __('admin.thread.col_thread') }}</th>
                            <th style="padding: 0.2rem 0.75rem;">{{ __('admin.thread.col_delta') }}</th>
                            <th style="padding: 0.2rem 0.75rem;">{{ __('admin.thread.col_speed') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($preview['cars'] as $car)
                            @php
                                $delta = abs($car['delta_percent']);
                                $tone = $delta <= 5 ? '#10b981' : ($delta <= 10 ? '#f59e0b' : '#ef4444');
                            @endphp
                            <tr>
                                <td style="padding: 0.2rem 0.75rem 0.2rem 0;">{{ $nameOf($car['name']) }} <span style="color: rgb(107 114 128);">T{{ $car['tier'] }}</span></td>
                                <td style="padding: 0.2rem 0.75rem;">{{ $ms($car['formula_ms']) }}</td>
                                <td style="padding: 0.2rem 0.75rem;">{{ $ms($car['thread_ms']) }}</td>
                                <td style="padding: 0.2rem 0.75rem; color: {{ $tone }}; font-weight: 600;">{{ ($car['delta_percent'] > 0 ? '+' : '').\Illuminate\Support\Number::format($car['delta_percent'], 1) }}%</td>
                                <td style="padding: 0.2rem 0.75rem;">{{ \Illuminate\Support\Number::format($car['average_kmh'], 0) }} / {{ \Illuminate\Support\Number::format($car['peak_kmh'], 0) }} km/h</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p style="font-size: 0.8125rem; color: rgb(107 114 128); margin-top: 0.5rem; max-width: 40rem;">{{ __('admin.thread.cars_note') }}</p>
            </div>
        @endif
    @endif
</div>
