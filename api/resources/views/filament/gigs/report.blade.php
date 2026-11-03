{{--
    What the gigs pay, as `gigs:report` prints it: the report of GigReport, worked out for the form as it is now (not as it was saved).
    $report is that report (null before it was asked for), $summary what the rows say of every template.
--}}
@php
    // The comma of the languages that write it so, as the rest of the panel does (the English panel keeps the point).
    $point = app()->getLocale() === 'en' ? '.' : ',';
    $ratio = fn (int|float $value): string => number_format($value, 2, $point, '');
@endphp

<div data-test="gigs-report" style="margin-top: 16px; border: 1px solid rgba(128, 128, 128, .3); border-radius: 12px; padding: 12px 16px; display: grid; gap: 12px;">
    <div style="font-weight: 600;">{{ __('admin.gigs.report.heading') }}</div>

    @if ($report === null)
        <p style="margin: 0; font-size: .875rem; opacity: .75;" data-test="report-empty">{{ __('admin.gigs.report.empty') }}</p>
    @elseif ($report['rows'] === [])
        <p style="margin: 0; font-size: .875rem; color: #b45309;" data-test="report-none">{{ __('admin.gigs.report.none') }}</p>
    @else
        <p style="margin: 0; font-size: .875rem; opacity: .85;">{{ __('admin.gigs.report.explain') }}</p>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: .875rem;" data-test="report-templates">
                <thead>
                    <tr style="text-align: left; opacity: .7;">
                        <th style="padding: 4px 8px;">{{ __('admin.gigs.report.template') }}</th>
                        <th style="padding: 4px 8px;">{{ __('admin.gigs.report.cars') }}</th>
                        <th style="padding: 4px 8px;">{{ __('admin.gigs.report.r1') }}</th>
                        <th style="padding: 4px 8px;">{{ __('admin.gigs.report.r2') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($summary as $template => $line)
                        <tr style="border-top: 1px solid rgba(128, 128, 128, .2);" data-template="{{ $template }}">
                            <td style="padding: 4px 8px; font-weight: 600;">{{ $template }}</td>
                            <td style="padding: 4px 8px;">{{ $line['cars'] }}</td>
                            <td style="padding: 4px 8px;">{{ $ratio($line['r1_min']) }} – {{ $ratio($line['r1_max']) }}</td>
                            <td style="padding: 4px 8px;">{{ $ratio($line['r2_min']) }} – {{ $ratio($line['r2_max']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($report['offences'] !== [])
            <div style="display: grid; gap: 4px;" data-test="report-offences">
                <div style="font-weight: 600; font-size: .875rem;">{{ __('admin.gigs.report.offences', ['count' => count($report['offences'])]) }}</div>
                @foreach (array_slice($report['offences'], 0, 20) as $offence)
                    <div style="font-size: .875rem;" data-offence="{{ $offence['car'] }} {{ $offence['template'] }}">
                        <span style="font-weight: 600; color: #b91c1c;">{{ $offence['car'] }}</span>
                        {{ $offence['template'] }}:
                        {{ $offence['why'] === 'r1' ? __('admin.gigs.report.why_r1') : __('admin.gigs.report.why_r2') }}
                        <span style="opacity: .65;">(R1 {{ $ratio($offence['r1']) }}, R2 {{ $ratio($offence['r2']) }})</span>
                    </div>
                @endforeach
                @if (count($report['offences']) > 20)
                    <div style="font-size: .8125rem; opacity: .65;">{{ __('admin.gigs.report.more', ['count' => count($report['offences']) - 20]) }}</div>
                @endif
            </div>
        @endif

        <p style="margin: 0; font-size: .875rem; font-weight: 600; color: {{ $report['offences'] === [] ? '#15803d' : '#b91c1c' }};" data-test="report-verdict">
            {{ $report['offences'] === [] ? __('admin.gigs.report.gate_passes') : __('admin.gigs.report.gate_stops') }}
        </p>
    @endif
</div>
